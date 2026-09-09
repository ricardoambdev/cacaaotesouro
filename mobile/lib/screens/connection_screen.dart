import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../theme.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

/// Tela de verificação de conexão e permissões do app.
///
/// Fluxo:
/// 1. Executa 4 checks sequenciais: Internet, Servidor, Câmera, GPS.
/// 2. Se TODOS passam → LoginScreen.
/// 3. Se ALGUM falhar → lista de erros + botão "Tentar novamente".
///    Se o servidor falhou, oferece formulário manual de endereço.
class ConnectionScreen extends StatefulWidget {
  const ConnectionScreen({super.key});

  @override
  State<ConnectionScreen> createState() => _ConnectionScreenState();
}

class _ConnectionScreenState extends State<ConnectionScreen> {
  static const String _keyApiLocked = 'api_locked';

  final _urlController = TextEditingController();
  final _apiService = ApiService();

  // ── Estado dos checks ──────────────────────────────────
  bool _isRunning = false;
  bool _allPassed = false;
  bool _showForm = false;
  bool _isTesting = false;
  String? _errorMessage;

  _CheckItem _internetCheck = _CheckItem(label: 'Internet');
  _CheckItem _serverCheck = _CheckItem(label: 'Servidor');
  _CheckItem _cameraCheck = _CheckItem(label: 'Câmera');
  _CheckItem _gpsCheck = _CheckItem(label: 'GPS');

  @override
  void initState() {
    super.initState();
    _initConnection();
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  /// Inicialização: lê baseUrl e executa os 4 checks.
  Future<void> _initConnection() async {
    await _apiService.loadBaseUrl();
    _urlController.text = ApiService.baseUrl;
    await _runChecks();
  }

  /// Executa os 4 checks sequenciais.
  Future<void> _runChecks() async {
    setState(() {
      _isRunning = true;
      _allPassed = false;
      _showForm = false;
      _errorMessage = null;
      _internetCheck = _CheckItem(label: 'Internet');
      _serverCheck = _CheckItem(label: 'Servidor');
      _cameraCheck = _CheckItem(label: 'Câmera');
      _gpsCheck = _CheckItem(label: 'GPS');
    });

    // 1. Internet
    await _checkInternet();
    if (_internetCheck.status == _Status.failed) {
      if (mounted) setState(() => _isRunning = false);
      return;
    }

    // 2. Servidor (fallback: produção → local)
    await _checkServer();
    if (_serverCheck.status == _Status.failed) {
      if (mounted) setState(() => _isRunning = false);
      return;
    }

    // 3. Câmera
    await _checkCamera();

    // 4. GPS
    await _checkGps();

    // Verificar se todos passaram
    final allOk = _internetCheck.passed &&
        _serverCheck.passed &&
        _cameraCheck.passed &&
        _gpsCheck.passed;

    if (!mounted) return;

    if (allOk) {
      // Verificar lock para devMode
      final config = await _apiService.checkConnection();
      if (!config.devMode) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool(_keyApiLocked, true);
      }
      if (!mounted) return;
      setState(() {
        _allPassed = true;
        _isRunning = false;
      });
      // Navegar após breve delay para mostrar ✓ final
      await Future.delayed(const Duration(milliseconds: 600));
      if (mounted) _navigateToChoice();
    } else {
      setState(() => _isRunning = false);
    }
  }

  // ── Check 1: Internet ──────────────────────────────────

  Future<void> _checkInternet() async {
    setState(() => _internetCheck = _internetCheck.withStatus(_Status.checking));

    try {
      final response = await http
          .get(Uri.parse('https://www.google.com/generate_204'))
          .timeout(const Duration(seconds: 5));

      if (response.statusCode == 204 || response.statusCode == 200) {
        setState(() => _internetCheck = _internetCheck.withStatus(_Status.passed));
      } else {
        setState(() => _internetCheck = _internetCheck.withStatus(
          _Status.failed,
          message: 'Sem acesso à internet.',
        ));
      }
    } catch (_) {
      setState(() => _internetCheck = _internetCheck.withStatus(
        _Status.failed,
        message: 'Sem acesso à internet.',
      ));
    }
  }

  // ── Check 2: Servidor ──────────────────────────────────

  Future<void> _checkServer() async {
    setState(() => _serverCheck = _serverCheck.withStatus(_Status.checking));

    try {
      await _apiService.connectWithFallback();
      setState(() => _serverCheck = _serverCheck.withStatus(_Status.passed));
    } on ApiException catch (e) {
      setState(() => _serverCheck = _serverCheck.withStatus(
        _Status.failed,
        message: e.message,
      ));
    } catch (_) {
      setState(() => _serverCheck = _serverCheck.withStatus(
        _Status.failed,
        message: 'Não foi possível conectar ao servidor.',
      ));
    }
  }

  // ── Check 3: Câmera ───────────────────────────────────

  Future<void> _checkCamera() async {
    setState(() => _cameraCheck = _cameraCheck.withStatus(_Status.checking));

    var status = await Permission.camera.status;

    if (status.isGranted || status.isLimited) {
      setState(() => _cameraCheck = _cameraCheck.withStatus(_Status.passed));
      return;
    }

    if (status.isPermanentlyDenied) {
      setState(() => _cameraCheck = _cameraCheck.withStatus(
        _Status.failed,
        message: 'Permissão negada permanentemente.',
        permanentlyDenied: true,
      ));
      return;
    }

    // Solicitar permissão
    status = await Permission.camera.request();

    if (status.isGranted || status.isLimited) {
      setState(() => _cameraCheck = _cameraCheck.withStatus(_Status.passed));
    } else if (status.isPermanentlyDenied) {
      setState(() => _cameraCheck = _cameraCheck.withStatus(
        _Status.failed,
        message: 'Permissão negada permanentemente.',
        permanentlyDenied: true,
      ));
    } else {
      setState(() => _cameraCheck = _cameraCheck.withStatus(
        _Status.failed,
        message: 'Permissão de câmera negada.',
      ));
    }
  }

  // ── Check 4: GPS ───────────────────────────────────────

  Future<void> _checkGps() async {
    setState(() => _gpsCheck = _gpsCheck.withStatus(_Status.checking));

    // Verificar se o serviço de localização está habilitado
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      setState(() => _gpsCheck = _gpsCheck.withStatus(
        _Status.failed,
        message: 'GPS desligado. Ative a localização nas configurações.',
      ));
      return;
    }

    // Verificar permissão
    var permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      setState(() => _gpsCheck = _gpsCheck.withStatus(
        _Status.failed,
        message: 'Permissão de localização negada.',
      ));
      return;
    }

    if (permission == LocationPermission.deniedForever) {
      setState(() => _gpsCheck = _gpsCheck.withStatus(
        _Status.failed,
        message: 'Permissão de localização negada permanentemente.',
        permanentlyDenied: true,
      ));
      return;
    }

    setState(() => _gpsCheck = _gpsCheck.withStatus(_Status.passed));
  }

  // ── Navegação ──────────────────────────────────────────

  void _navigateToChoice() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  /// Testa o endereço digitado pelo usuário (formulário manual).
  Future<void> _testAndConnect() async {
    final rawUrl = _urlController.text.trim();
    if (rawUrl.isEmpty) {
      setState(() {
        _errorMessage = 'Informe um endereço válido.';
      });
      return;
    }

    final normalizedUrl = ApiService.normalizeApiUrl(rawUrl);

    setState(() {
      _isTesting = true;
      _errorMessage = null;
    });

    await ApiService.saveBaseUrl(normalizedUrl);

    try {
      final config = await _apiService.checkConnection();

      if (!config.devMode) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool(_keyApiLocked, true);
      }

      if (mounted) _navigateToChoice();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _isTesting = false;
          _errorMessage = e.message;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _isTesting = false;
          _errorMessage = 'Não foi possível conectar a este endereço.';
        });
      }
    }
  }

  // ── UI ─────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Fundo: preto + poster centralizado (50% da largura) ──
          Positioned.fill(
            child: ColoredBox(
              color: Colors.black,
            ),
          ),
          Center(
            child: Image.asset(
              'assets/images/poster.png',
              width: MediaQuery.of(context).size.width * 0.5,
              fit: BoxFit.contain,
            ),
          ),

          // ── Conteúdo: só aparece em caso de ERRO ────────
          Positioned.fill(
            child: SafeArea(
              child: _showForm
                  ? _buildForm()
                  : (!_isRunning && !_allPassed)
                      ? _buildCheckList()
                      : const SizedBox.shrink(),
            ),
          ),
        ],
      ),
    );
  }

  /// Lista dos 4 checks com progresso (exibida apenas em caso de erro).
  Widget _buildCheckList() {
    final padding = MediaQuery.paddingOf(context);
    final hasFailed = _internetCheck.failed ||
        _serverCheck.failed ||
        _cameraCheck.failed ||
        _gpsCheck.failed;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        children: [
          const Spacer(flex: 3),

          // ── Card central (semi-transparente sobre o poster) ──
          Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.75),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: AppColors.ivoryMuted.withValues(alpha: 0.15),
                width: 1,
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Ícone
                Icon(
                  hasFailed ? Icons.error_outline_rounded : Icons.wifi_find_rounded,
                  size: 48,
                  color: hasFailed
                      ? Colors.redAccent.withValues(alpha: 0.8)
                      : AppColors.gold.withValues(alpha: 0.8),
                ),
                const SizedBox(height: 16),

                // Título
                Text(
                  hasFailed ? 'Falha na inicialização' : 'Verificando sistema...',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ivory,
                  ),
                ),
                const SizedBox(height: 24),

                // Lista de checks
                _buildCheckTile(_internetCheck),
                const SizedBox(height: 12),
                _buildCheckTile(_serverCheck),
                const SizedBox(height: 12),
                _buildCheckTile(_cameraCheck),
                const SizedBox(height: 12),
                _buildCheckTile(_gpsCheck),

                // Mensagem de erro do servidor
                if (_serverCheck.failed) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.orange.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: Colors.orange.withValues(alpha: 0.3),
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline_rounded,
                            size: 18, color: Colors.orange),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _serverCheck.message ?? 'Servidor indisponível.',
                            style: const TextStyle(
                              fontSize: 12,
                              color: Colors.orange,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // Botões de ação quando há falha
                if (hasFailed && !_isRunning) ...[
                  const SizedBox(height: 20),

                  // Tentar novamente
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [AppColors.gold, AppColors.goldDark],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.gold.withValues(alpha: 0.3),
                            blurRadius: 12,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: ElevatedButton(
                        onPressed: _runChecks,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.transparent,
                          shadowColor: Colors.transparent,
                          foregroundColor: AppColors.navyDark,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: const Text(
                          'Tentar novamente',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ),

                  // Botão de configurações do servidor (se servidor falhou)
                  if (_serverCheck.failed) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: OutlinedButton.icon(
                        onPressed: () {
                          setState(() {
                            _showForm = true;
                            _errorMessage = null;
                          });
                        },
                        icon: const Icon(Icons.settings_rounded, size: 18),
                        label: const Text(
                          'Configurar endereço do servidor',
                          style: TextStyle(fontSize: 14),
                        ),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.ivoryMuted,
                          side: BorderSide(
                            color: AppColors.ivoryMuted.withValues(alpha: 0.3),
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      ),
                    ),
                  ],

                  // Botão para abrir configurações do app (se permissão negada permanentemente)
                  if (_cameraCheck.failed &&
                      (_cameraCheck.permanentlyDenied ?? false)) ...[
                    const SizedBox(height: 12),
                    TextButton.icon(
                      onPressed: () => openAppSettings(),
                      icon: const Icon(Icons.settings, size: 16),
                      label: const Text('Abrir configurações do app'),
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.gold,
                      ),
                    ),
                  ],
                  if (_gpsCheck.failed &&
                      (_gpsCheck.permanentlyDenied ?? false)) ...[
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: () => Geolocator.openAppSettings(),
                      icon: const Icon(Icons.settings, size: 16),
                      label: const Text('Abrir configurações do app'),
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.gold,
                      ),
                    ),
                  ],
                ],

                // Spinner quando rodando
                if (_isRunning) ...[
                  const SizedBox(height: 20),
                  SizedBox(
                    width: 32,
                    height: 32,
                    child: CircularProgressIndicator(
                      strokeWidth: 3,
                      valueColor: AlwaysStoppedAnimation<Color>(AppColors.gold),
                    ),
                  ),
                ],
              ],
            ),
          ),

          const Spacer(flex: 3),

          // ── Rodapé sutil ─────────────────────────────
          Padding(
            padding: EdgeInsets.only(bottom: padding.bottom + 16),
            child: Text(
              'Caça ao Tesouro',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.ivoryMuted.withValues(alpha: 0.4),
                letterSpacing: 2,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Tile individual de cada check.
  Widget _buildCheckTile(_CheckItem item) {
    IconData icon;
    Color iconColor;
    Widget? trailing;

    switch (item.status) {
      case _Status.idle:
        icon = Icons.circle_outlined;
        iconColor = AppColors.ivoryMuted.withValues(alpha: 0.3);
        break;
      case _Status.checking:
        icon = Icons.hourglass_top_rounded;
        iconColor = AppColors.gold;
        trailing = SizedBox(
          width: 16,
          height: 16,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.gold),
          ),
        );
        break;
      case _Status.passed:
        icon = Icons.check_circle_rounded;
        iconColor = Colors.greenAccent;
        break;
      case _Status.failed:
        icon = Icons.cancel_rounded;
        iconColor = Colors.redAccent;
        break;
    }

    return Row(
      children: [
        Icon(icon, size: 22, color: iconColor),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            item.label,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w500,
              color: item.status == _Status.passed
                  ? Colors.greenAccent
                  : item.status == _Status.failed
                      ? Colors.redAccent
                      : AppColors.ivory,
            ),
          ),
        ),
        ?trailing,
        if (item.status == _Status.failed && item.message != null)
          Flexible(
            child: Padding(
              padding: const EdgeInsets.only(left: 8),
              child: Text(
                item.message!,
                style: TextStyle(
                  fontSize: 11,
                  color: Colors.redAccent.withValues(alpha: 0.8),
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
      ],
    );
  }

  /// Formulário de configuração manual do endereço da API.
  Widget _buildForm() {
    final padding = MediaQuery.paddingOf(context);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        children: [
          const Spacer(flex: 3),

          // ── Card central (semi-transparente sobre o poster) ──
          Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.75),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: AppColors.ivoryMuted.withValues(alpha: 0.15),
                width: 1,
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Ícone de aviso
                Icon(
                  Icons.cloud_off_rounded,
                  size: 48,
                  color: AppColors.gold.withValues(alpha: 0.8),
                ),
                const SizedBox(height: 16),

                // Título
                const Text(
                  'Sem conexão',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ivory,
                  ),
                ),
                const SizedBox(height: 8),

                // Descrição
                Text(
                  'Não foi possível conectar ao servidor.\nInforme o endereço da API.',
                  style: TextStyle(
                    fontSize: 14,
                    color: AppColors.ivoryMuted,
                    height: 1.5,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 24),

                // Campo de texto
                TextField(
                  controller: _urlController,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.ivory,
                  ),
                  decoration: InputDecoration(
                    hintText: 'http://exemplo.com/api',
                    prefixIcon: const Icon(Icons.link_rounded, size: 20),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  keyboardType: TextInputType.url,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _testAndConnect(),
                ),
                const SizedBox(height: 16),

                // Erro inline
                if (_errorMessage != null) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.redAccent.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: Colors.redAccent.withValues(alpha: 0.4),
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.error_outline_rounded,
                          size: 18,
                          color: Colors.redAccent,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _errorMessage!,
                            style: const TextStyle(
                              fontSize: 13,
                              color: Colors.redAccent,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Botão "Conectar"
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [AppColors.gold, AppColors.goldDark],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.gold.withValues(alpha: 0.3),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: ElevatedButton(
                      onPressed: _isTesting ? null : _testAndConnect,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        foregroundColor: AppColors.navyDark,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: _isTesting
                          ? SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  AppColors.navyDark,
                                ),
                              ),
                            )
                          : const Text(
                              'Conectar',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                    ),
                  ),
                ),

                // Voltar para checks
                const SizedBox(height: 12),
                TextButton.icon(
                  onPressed: () {
                    setState(() {
                      _showForm = false;
                      _errorMessage = null;
                    });
                    _runChecks();
                  },
                  icon: const Icon(Icons.arrow_back_rounded, size: 16),
                  label: const Text('Voltar'),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.ivoryMuted,
                  ),
                ),
              ],
            ),
          ),

          const Spacer(flex: 3),

          // ── Rodapé sutil ─────────────────────────────
          Padding(
            padding: EdgeInsets.only(bottom: padding.bottom + 16),
            child: Text(
              'Caça ao Tesouro',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.ivoryMuted.withValues(alpha: 0.4),
                letterSpacing: 2,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════
//  MODELOS AUXILIARES
// ════════════════════════════════════════════════════════════

enum _Status { idle, checking, passed, failed }

class _CheckItem {
  final String label;
  final _Status status;
  final String? message;
  final bool? permanentlyDenied;

  const _CheckItem({
    required this.label,
    this.status = _Status.idle,
    this.message,
    this.permanentlyDenied,
  });

  bool get passed => status == _Status.passed;
  bool get failed => status == _Status.failed;

  _CheckItem withStatus(_Status newStatus, {String? message, bool? permanentlyDenied}) {
    return _CheckItem(
      label: label,
      status: newStatus,
      message: message ?? this.message,
      permanentlyDenied: permanentlyDenied ?? this.permanentlyDenied,
    );
  }
}
