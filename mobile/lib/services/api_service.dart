import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/api_config.dart';
import '../models/game_state.dart';
import '../models/treasure.dart';
import 'device_service.dart';

/// Cliente HTTP para a API do Caça ao Tesouro.
///
/// CONFIGURACAO DO baseUrl:
///
/// - Emulador Android : http://10.0.2.2/api
/// - Celular fisico   : http://IP_DA_MAQUINA/api
/// - Producao         : https://cacaaotesouro.sentapua/api
///
/// O baseUrl é dinâmico: pode ser alterado pelo usuário na tela de conexão
/// e é persistido em SharedPreferences.
class ApiService {
  // ════════════════════════════════════════════════════════════
  //  URL BASE — DINÂMICA (persistida em SharedPreferences)
  // ════════════════════════════════════════════════════════════
  static String baseUrl = 'http://cacaaotesouro.sentapua/api';

  /// Servidor de produção (fallback automático).
  static const String serverBaseUrl =
      'https://cacaaotesouro.colegiohelena.com.br/api';

  static const String _keyBaseUrl = 'api_base_url';

  // ── Singleton ──────────────────────────────────────────────
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  // ── Cookie de sessão (PHPSESSID) ───────────────────────────
  String? _sessionCookie;

  // ════════════════════════════════════════════════════════════
  //  PERSISTÊNCIA DO baseUrl
  // ════════════════════════════════════════════════════════════

  /// Lê o baseUrl salvo em SharedPreferences e atualiza a variável estática.
  Future<void> loadBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_keyBaseUrl);
    if (saved != null && saved.isNotEmpty) {
      baseUrl = saved;
    }
  }

  /// Salva o url em SharedPreferences e atualiza a variável estática.
  static Future<void> saveBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyBaseUrl, url);
    baseUrl = url;
  }

  /// Normaliza o endereço digitado pelo usuário:
  /// - remove barra final
  /// - garante que termina com '/api'
  static String normalizeApiUrl(String input) {
    var url = input.trim();
    // Remover barra final
    if (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    // Garantir que termina com '/api'
    if (!url.endsWith('/api')) {
      if (url.endsWith('/')) {
        url = '${url}api';
      } else {
        url = '$url/api';
      }
    }
    return url;
  }

  // ════════════════════════════════════════════════════════════
  //  CHECK DE CONEXÃO
  // ════════════════════════════════════════════════════════════

  /// Tenta conectar à API via GET $url/config.
  /// Se [url] não for informada, usa [baseUrl].
  /// Retorna o [ApiConfig] em caso de sucesso.
  /// Lança [ApiException] em caso de falha.
  Future<ApiConfig> checkConnection({String? url}) async {
    final targetUrl = url ?? baseUrl;
    try {
      final response = await http
          .get(
            Uri.parse('$targetUrl/config'),
            headers: {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 8));

      if (response.statusCode != 200) {
        throw ApiException('Não foi possível conectar à API.');
      }

      final body = json.decode(response.body) as Map<String, dynamic>;
      if (body['success'] != true) {
        throw ApiException('Não foi possível conectar à API.');
      }

      final configData = body['config'] as Map<String, dynamic>?;
      if (configData == null) {
        throw ApiException('Resposta inválida da API.');
      }

      return ApiConfig.fromJson(configData);
    } on SocketException {
      throw ApiException('Não foi possível conectar à API.');
    } on TimeoutException {
      throw ApiException('Não foi possível conectar à API.');
    } on FormatException {
      throw ApiException('Não foi possível conectar à API.');
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException('Não foi possível conectar à API.');
    }
  }

  /// Tenta conectar com fallback: primeiro o servidor de produção,
  /// depois a URL local salva. Lança ApiException se ambos falharem.
  Future<ApiConfig> connectWithFallback() async {
    // 1. Tentar servidor de produção
    try {
      final config = await checkConnection(url: serverBaseUrl);
      await saveBaseUrl(serverBaseUrl);
      return config;
    } on ApiException {
      // Servidor de produção falhou — tentar URL local
    }

    // 2. Tentar URL local salva
    try {
      final config = await checkConnection(url: baseUrl);
      await saveBaseUrl(baseUrl);
      return config;
    } on ApiException {
      // Ambos falharam
    }

    throw ApiException('Não foi possível conectar ao servidor.');
  }

  // ── Cookie de sessão (PHPSESSID) ───────────────────────────

  /// Extrai e armazena o cookie de sessão do header Set-Cookie.
  void _extractCookie(http.Response response) {
    final setCookie = response.headers['set-cookie'];
    if (setCookie != null) {
      final cookies = setCookie.split(',');
      for (final c in cookies) {
        final trimmed = c.trim();
        if (trimmed.startsWith('cacaaotesouro_session=') ||
            trimmed.startsWith('PHPSESSID=')) {
          _sessionCookie = trimmed.split(';').first;
          break;
        }
      }
    }
  }

  /// Extrai cookie de uma response de multipart request.
  void _extractCookieFromStream(http.StreamedResponse response) {
    final setCookie = response.headers['set-cookie'];
    if (setCookie != null) {
      final cookies = setCookie.split(',');
      for (final c in cookies) {
        final trimmed = c.trim();
        if (trimmed.startsWith('cacaaotesouro_session=') ||
            trimmed.startsWith('PHPSESSID=')) {
          _sessionCookie = trimmed.split(';').first;
          break;
        }
      }
    }
  }

  /// Headers comuns para requests autenticados (admin).
  Map<String, String> get _adminHeaders {
    final h = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_sessionCookie != null) {
      h['Cookie'] = _sessionCookie!;
    }
    return h;
  }

  /// Headers para chamadas de equipe (com X-Device-Id).
  Future<Map<String, String>> _teamHeaders() async {
    final deviceId = await DeviceService().getDeviceId();
    final h = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Device-Id': deviceId,
    };
    if (_sessionCookie != null) {
      h['Cookie'] = _sessionCookie!;
    }
    return h;
  }

  /// Decodifica o body JSON de uma response.
  Map<String, dynamic> _parseBody(http.Response response) {
    try {
      return json.decode(response.body) as Map<String, dynamic>;
    } catch (_) {
      return <String, dynamic>{};
    }
  }

  /// Verifica se a resposta indica sessão expirada (401).
  void _checkUnauthorized(http.Response response) {
    if (response.statusCode == 401) {
      final body = _parseBody(response);
      throw ApiException(
        body['error'] as String? ?? 'Sessão expirada. Faça login novamente.',
      );
    }
  }

  /// Verifica erro 400.
  void _checkBadRequest(http.Response response) {
    if (response.statusCode == 400) {
      final body = _parseBody(response);
      throw ApiException(
        body['error'] as String? ?? 'Requisição inválida.',
        distanceM: body['distance_m'] as num?,
      );
    }
  }

  // ════════════════════════════════════════════════════════════
  //  MÉTODOS DE EQUIPE
  // ════════════════════════════════════════════════════════════

  /// POST /api/team/login
  Future<Map<String, dynamic>> teamLogin(
      String username, String password) async {
    final deviceId = await DeviceService().getDeviceId();
    final response = await http.post(
      Uri.parse('$baseUrl/team/login'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: json.encode({
        'username': username,
        'password': password,
        'device_id': deviceId,
      }),
    );

    _extractCookie(response);
    final body = _parseBody(response);

    if (response.statusCode == 200 && body['success'] == true) {
      return body['team'] as Map<String, dynamic>;
    }

    if (response.statusCode == 409) {
      throw ApiException(
        body['error'] as String? ??
            'Outro membro da equipe já está logado no aplicativo.',
      );
    }

    if (response.statusCode == 401) {
      throw ApiException(
        body['error'] as String? ?? 'Usuário ou senha inválidos.',
      );
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao fazer login.');
  }

  /// POST /api/team/logout
  Future<void> teamLogout() async {
    try {
      final headers = await _teamHeaders();
      await http.post(
        Uri.parse('$baseUrl/team/logout'),
        headers: headers,
      );
    } finally {
      _sessionCookie = null;
    }
  }

  /// GET /api/team/state
  Future<GameState> teamState() async {
    final headers = await _teamHeaders();
    final response = await http.get(
      Uri.parse('$baseUrl/team/state'),
      headers: headers,
    );

    _extractCookie(response);

    if (response.statusCode == 401) {
      final body = _parseBody(response);
      throw ApiException(
        body['error'] as String? ?? 'Sessão expirada. Faça login novamente.',
      );
    }

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return GameState.fromJson(body);
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao obter estado do jogo.');
  }

  /// GET /api/story (público)
  Future<String> getStory() async {
    final response = await http.get(
      Uri.parse('$baseUrl/story'),
      headers: {'Accept': 'application/json'},
    );

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return (body['story'] as String?) ?? '';
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao carregar história.');
  }

  /// POST /api/team/checkin
  Future<CheckinResult> checkIn({
    required int treasureId,
    required double lat,
    required double lng,
    required String qrCode,
  }) async {
    final headers = await _teamHeaders();
    final response = await http.post(
      Uri.parse('$baseUrl/team/checkin'),
      headers: headers,
      body: json.encode({
        'treasure_id': treasureId,
        'lat': lat,
        'lng': lng,
        'qr_code': qrCode,
      }),
    );

    _extractCookie(response);
    _checkBadRequest(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return CheckinResult.fromJson(body);
    }

    throw ApiException(body['error'] as String? ?? 'Erro no checkin.');
  }

  /// POST /api/team/selfie (multipart)
  Future<Map<String, dynamic>> uploadSelfie({
    required int treasureId,
    required String filePath,
  }) async {
    final deviceId = await DeviceService().getDeviceId();
    final uri = Uri.parse('$baseUrl/team/selfie');

    final request = http.MultipartRequest('POST', uri);
    request.headers['Accept'] = 'application/json';
    request.headers['X-Device-Id'] = deviceId;
    if (_sessionCookie != null) {
      request.headers['Cookie'] = _sessionCookie!;
    }
    request.fields['treasure_id'] = treasureId.toString();
    request.files.add(await http.MultipartFile.fromPath('image', filePath));

    final streamedResponse = await request.send();
    _extractCookieFromStream(streamedResponse);

    final response = await http.Response.fromStream(streamedResponse);
    final body = _parseBody(response);

    if (response.statusCode == 200 && body['success'] == true) {
      return body;
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao enviar selfie.');
  }

  /// POST /api/team/answer
  Future<AnswerResult> answer({
    required int treasureId,
    required String answer,
  }) async {
    final headers = await _teamHeaders();
    final response = await http.post(
      Uri.parse('$baseUrl/team/answer'),
      headers: headers,
      body: json.encode({
        'treasure_id': treasureId,
        'answer': answer,
      }),
    );

    _extractCookie(response);
    _checkBadRequest(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return AnswerResult.fromJson(body);
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao enviar resposta.');
  }

  /// POST /api/team/final-answer
  Future<AnswerResult> finalAnswer({required String answer}) async {
    final headers = await _teamHeaders();
    final response = await http.post(
      Uri.parse('$baseUrl/team/final-answer'),
      headers: headers,
      body: json.encode({'answer': answer}),
    );

    _extractCookie(response);
    _checkBadRequest(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return AnswerResult.fromJson(body);
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao enviar resposta final.');
  }

  /// GET /api/team/points
  Future<Map<String, dynamic>> teamPoints() async {
    final headers = await _teamHeaders();
    final response = await http.get(
      Uri.parse('$baseUrl/team/points'),
      headers: headers,
    );

    _extractCookie(response);
    final body = _parseBody(response);

    if (response.statusCode == 200 && body['success'] == true) {
      return body;
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao obter pontos.');
  }

  /// POST /api/team/location
  Future<void> sendLocation({
    required double lat,
    required double lng,
    double? accuracy,
  }) async {
    try {
      final headers = await _teamHeaders();
      await http.post(
        Uri.parse('$baseUrl/team/location'),
        headers: headers,
        body: json.encode({
          'lat': lat,
          'lng': lng,
          if (accuracy != null) 'accuracy': accuracy,
        }),
      );
    } catch (_) {
      // Silencioso — falha de rede não deve afetar o usuário
    }
  }

  // ════════════════════════════════════════════════════════════
  //  MÉTODOS DE ADMIN
  // ════════════════════════════════════════════════════════════

  /// POST /api/admin/login
  Future<Map<String, dynamic>> adminLogin(
      String username, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/admin/login'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: json.encode({
        'username': username,
        'password': password,
      }),
    );

    _extractCookie(response);
    final body = _parseBody(response);

    if (response.statusCode == 200 && body['success'] == true) {
      return body['user'] as Map<String, dynamic>;
    }

    throw ApiException(
      body['error'] as String? ?? 'Usuário ou senha inválidos.',
    );
  }

  /// POST /api/admin/logout
  Future<void> adminLogout() async {
    try {
      await http.post(
        Uri.parse('$baseUrl/admin/logout'),
        headers: _adminHeaders,
      );
    } finally {
      _sessionCookie = null;
    }
  }

  /// GET /api/admin/treasures
  Future<List<GameTreasure>> adminTreasures() async {
    final response = await http.get(
      Uri.parse('$baseUrl/admin/treasures'),
      headers: _adminHeaders,
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      final list = body['treasures'] as List<dynamic>;
      return list
          .map((t) => GameTreasure.fromJson(t as Map<String, dynamic>))
          .toList();
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao carregar tesouros.');
  }

  /// POST /api/admin/confirm-coordinate
  Future<Map<String, dynamic>> confirmCoordinate({
    required int treasureId,
    required double lat,
    required double lng,
    required String qrCode,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/admin/confirm-coordinate'),
      headers: _adminHeaders,
      body: json.encode({
        'treasure_id': treasureId,
        'lat': lat,
        'lng': lng,
        'qr_code': qrCode,
      }),
    );

    _extractCookie(response);
    _checkBadRequest(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body;
    }

    throw ApiException(body['error'] as String? ?? 'Erro ao confirmar coordenada.');
  }

  /// POST /api/admin/disconnect-all
  Future<Map<String, dynamic>> disconnectAll() async {
    final response = await http.post(
      Uri.parse('$baseUrl/admin/disconnect-all'),
      headers: _adminHeaders,
    );

    _extractCookie(response);
    final body = _parseBody(response);

    if (response.statusCode == 200 && body['success'] == true) {
      return body;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao desconectar equipes.');
  }

  /// GET /api/admin/status
  Future<AdminStatus> adminStatus() async {
    final response = await http.get(
      Uri.parse('$baseUrl/admin/status'),
      headers: _adminHeaders,
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return AdminStatus.fromJson(body);
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao obter status do jogo.');
  }

  // ════════════════════════════════════════════════════════════
  //  ADMIN: GAME CONFIG
  // ════════════════════════════════════════════════════════════

  /// GET /api/admin/game → dados do jogo.
  Future<Map<String, dynamic>> adminGame() async {
    final response = await http.get(
      Uri.parse('$baseUrl/admin/game'),
      headers: _adminHeaders,
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body['game'] as Map<String, dynamic>;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao obter dados do jogo.');
  }

  /// PUT /api/admin/game → atualiza dados do jogo.
  Future<Map<String, dynamic>> adminUpdateGame({
    String? status,
    String? startDate,
    String? startTime,
    String? endTime,
  }) async {
    final payload = <String, dynamic>{};
    if (status != null) payload['status'] = status;
    if (startDate != null) payload['start_date'] = startDate;
    if (startTime != null) payload['start_time'] = startTime;
    if (endTime != null) payload['end_time'] = endTime;

    final response = await http.put(
      Uri.parse('$baseUrl/admin/game'),
      headers: _adminHeaders,
      body: json.encode(payload),
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body['game'] as Map<String, dynamic>;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao atualizar jogo.');
  }

  // ════════════════════════════════════════════════════════════
  //  ADMIN: TEAM POINTS
  // ════════════════════════════════════════════════════════════

  /// POST /api/admin/team-points → ajusta pontos de uma equipe.
  /// Retorna o novo total de pontos (int).
  Future<int> adminTeamPoints(
    int teamId,
    int delta, {
    String? reason,
  }) async {
    final payload = <String, dynamic>{
      'team_id': teamId,
      'delta': delta,
    };
    if (reason != null && reason.isNotEmpty) {
      payload['reason'] = reason;
    }

    final response = await http.post(
      Uri.parse('$baseUrl/admin/team-points'),
      headers: _adminHeaders,
      body: json.encode(payload),
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return (body['points'] as num).toInt();
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao ajustar pontos.');
  }

  // ════════════════════════════════════════════════════════════
  //  ADMIN: TEAM MESSAGE
  // ════════════════════════════════════════════════════════════

  /// POST /api/admin/team-message → envia mensagem para uma equipe.
  Future<Map<String, dynamic>> adminTeamMessage(
    int teamId,
    String message,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/admin/team-message'),
      headers: _adminHeaders,
      body: json.encode({
        'team_id': teamId,
        'message': message,
      }),
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body['message'] as Map<String, dynamic>;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao enviar mensagem.');
  }

  /// POST /api/admin/team-message-all → envia mensagem para TODAS as equipes.
  Future<void> adminTeamMessageAll(String message) async {
    final response = await http.post(
      Uri.parse('$baseUrl/admin/team-message-all'),
      headers: _adminHeaders,
      body: json.encode({'message': message}),
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode != 200 || body['success'] != true) {
      throw ApiException(
          body['error'] as String? ?? 'Erro ao enviar mensagem para as equipes.');
    }
  }

  // ════════════════════════════════════════════════════════════
  //  ADMIN: TREASURE DETAIL / UPDATE
  // ════════════════════════════════════════════════════════════

  /// GET /api/admin/treasures/{id} → dados detalhados do tesouro.
  Future<Map<String, dynamic>> adminTreasure(int id) async {
    final response = await http.get(
      Uri.parse('$baseUrl/admin/treasures/$id'),
      headers: _adminHeaders,
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body['treasure'] as Map<String, dynamic>;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao carregar tesouro.');
  }

  /// PUT /api/admin/treasures/{id} → atualiza campos do tesouro.
  Future<Map<String, dynamic>> adminUpdateTreasure(
    int id,
    Map<String, dynamic> fields,
  ) async {
    final response = await http.put(
      Uri.parse('$baseUrl/admin/treasures/$id'),
      headers: _adminHeaders,
      body: json.encode(fields),
    );

    _extractCookie(response);
    _checkUnauthorized(response);

    final body = _parseBody(response);
    if (response.statusCode == 200 && body['success'] == true) {
      return body['treasure'] as Map<String, dynamic>;
    }

    throw ApiException(
        body['error'] as String? ?? 'Erro ao atualizar tesouro.');
  }

  // ════════════════════════════════════════════════════════════
  //  TEAM: MARK MESSAGES READ
  // ════════════════════════════════════════════════════════════

  /// POST /api/team/messages/read → marca mensagens como lidas.
  Future<void> teamMarkMessagesRead(List<int> ids) async {
    final headers = await _teamHeaders();
    final response = await http.post(
      Uri.parse('$baseUrl/team/messages/read'),
      headers: headers,
      body: json.encode({'ids': ids}),
    );

    _extractCookie(response);
    // Silencioso — não precisa de feedback detalhado
    if (response.statusCode != 200) {
      final body = _parseBody(response);
      throw ApiException(
          body['error'] as String? ?? 'Erro ao marcar mensagens.');
    }
  }
}

/// Exceção lançada quando a API retorna erro.
class ApiException implements Exception {
  final String message;
  final num? distanceM;

  ApiException(this.message, {this.distanceM});

  @override
  String toString() => 'ApiException: $message';
}
