import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart'
    show HtmlWidget;
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import '../services/name_blocklist.dart';
import '../services/sound_service.dart';
import '../widgets/device_name_dialog.dart';
import 'login_screen.dart';
import 'qr_scanner_screen.dart';

/// Tela principal da equipe com NavigationBar de 4 itens.
///
/// Abas: Tesouro (principal), História, Pontos, Sair.
class TeamHomeScreen extends StatefulWidget {
  final Map<String, dynamic> teamData;

  const TeamHomeScreen({super.key, required this.teamData});

  @override
  State<TeamHomeScreen> createState() => _TeamHomeScreenState();
}

class _TeamHomeScreenState extends State<TeamHomeScreen> {
  final _apiService = ApiService();
  final _soundService = SoundService();
  final _deviceService = DeviceService();
  int _currentTab = 0;
  GameState? _gameState;
  bool _isLoading = true;
  String? _error;

  // ── Estado do fluxo do tesouro ──────────────────────────
  TreasureFlowState _flowState = TreasureFlowState.viewingClue;
  CheckinResult? _checkinResult;
  AnswerResult? _answerResult;

  /// Cena sonora atual ('nenhuma' | 'erro' | 'desafio') — evita reiniciar o
  /// som a cada rebuild. Controlada por [_syncSounds].
  String _soundScene = 'nenhuma';

  // ── Timer de envio de localização ──────────────────────
  Timer? _locationTimer;

  // ── Timer de polling de mensagens ──────────────────────
  Timer? _messagesTimer;

  /// Sincronização entre os aparelhos da mesma equipe (5s).
  Timer? _syncTimer;

  /// Assinatura do progresso vista por último — muda quando OUTRO aparelho
  /// da equipe completa um tesouro.
  String? _syncSignature;

  // ── Mensagens não lidas ────────────────────────────────
  List<TeamMessage> _unreadMessages = [];

  /// Controle de fila: impede que um segundo poll abra modais enquanto
  /// o usuário ainda está vendo a fila de mensagens atual.
  bool _showingMessageQueue = false;

  @override
  void initState() {
    super.initState();
    _loadDeviceName();
    _loadState();
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    _messagesTimer?.cancel();
    _syncTimer?.cancel();
    // NUNCA chamar _soundService.dispose() aqui: o SoundService é um
    // singleton e o player seria destruído para o resto da sessão (os sons
    // parariam de tocar). Apenas paramos o que estiver tocando.
    _soundService.stopAll();
    super.dispose();
  }

  /// Cena sonora atual. Muda quando a tela visível muda.
  String _currentSoundScene() {
    // Jogo encerrado: nada de som (a tela é só o vencedor).
    if (_gameState?.gameOver == true) {
      return 'nenhuma';
    }

    final result = _answerResult;

    // Erro da charada: som em loop até sair da tela.
    if (_flowState == TreasureFlowState.answerResult &&
        result != null &&
        !result.correct) {
      return 'erro';
    }

    // Desafio final aberto: música de fundo em loop — inclusive quando o app
    // abre direto nele (sem ter passado pelas telas anteriores).
    if (_isFinalChallengeVisible) {
      return 'desafio';
    }

    return 'nenhuma';
  }

  /// Garante que só o som da cena atual esteja tocando.
  ///
  /// Chamado no `build()` — reage a qualquer mudança de `_flowState`.
  void _syncSounds() {
    final scene = _currentSoundScene();

    if (scene == _soundScene) return;

    _soundScene = scene;

    switch (scene) {
      case 'erro':
        _soundService.playChoroLoop();
        break;
      case 'desafio':
        _soundService.playBackgroundMusic();
        break;
      default:
        _soundService.stopLoop();
        _soundService.stopBackgroundMusic();
    }
  }

  Future<void> _loadState() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final state = await _apiService.teamState();
      if (!mounted) return;

      // ── Retomada: restaurar flow a partir do estado do tesouro ──
      CheckinResult? restoredCheckin;
      TreasureFlowState restoredFlow = TreasureFlowState.viewingClue;

      if (state.currentTreasure != null) {
        final t = state.currentTreasure!;
        if (t.checkedIn) {
          // Montar _checkinResult a partir dos campos do current_treasure
          restoredCheckin = CheckinResult(
            message: 'Checkin já realizado.',
            assignedRiddle: t.assignedRiddle ?? 1,
            riddle: t.riddle ?? '',
            selfieQuestion: false,
            selfieRequired: !t.selfieSent,
            answerLength: t.answerLength ?? 6,
          );
          if (!t.selfieSent) {
            // Checkin feito mas selfie pendente → card de selfie
            restoredFlow = TreasureFlowState.checkinSuccess;
          } else if (!t.riddleAnswered) {
            // Selfie feita, charada pendente → mostrar charada
            restoredFlow = TreasureFlowState.showingRiddle;
          }
          // Se riddleAnswered == true → fica em viewingClue (próximo tesouro
          // ou aguardando)
        }
      }

      setState(() {
        _gameState = state;
        _unreadMessages = List<TeamMessage>.from(state.messages);
        _isLoading = false;
        _flowState = restoredFlow;
        _checkinResult = restoredCheckin;
        _answerResult = null;
      });
      _startLocationTracking();
      _startMessagesPolling();
      _startSyncPolling();

      // ── Detectar mensagens NOVAS (id > última vista) ──────
      await _handleNewTeamMessages(state.messages);

      // Marcar mensagens como lidas após exibir
      if (_unreadMessages.isNotEmpty) {
        _markMessagesRead();
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Erro ao carregar estado do jogo.';
        _isLoading = false;
      });
    }
  }

  /// Marca as mensagens como lidas no backend e remove da lista local.
  Future<void> _markMessagesRead() async {
    final ids = _unreadMessages.map((m) => m.id).toList();
    try {
      await _apiService.teamMarkMessagesRead(ids);
      if (mounted) {
        setState(() => _unreadMessages.clear());
      }
    } catch (_) {
      // Silencioso — não bloqueia o app
    }
  }

  /// Processa mensagens novas: exibe uma por vez em fila sequencial.
  ///
  /// Cada mensagem abre um modal; ao clicar OK, fecha e abre a próxima.
  /// O som de notificação toca quando CADA modal aparece e é parado ao
  /// fechar (antes do próximo). O `setLastMessageId` e a marcação como
  /// lido no servidor só acontecem DEPOIS de toda a fila ser exibida.
  Future<void> _handleNewTeamMessages(List<TeamMessage> messages) async {
    final lastId = await _deviceService.getLastMessageId();
    final newMsgs = messages.where((m) => m.id > lastId).toList();
    if (newMsgs.isEmpty) return;

    // Já existe uma fila em exibição: deixa para o próximo poll.
    // Nada é marcado como visto, então essas mensagens não se perdem.
    if (_showingMessageQueue) return;

    _showingMessageQueue = true;

    try {
      for (var i = 0; i < newMsgs.length; i++) {
        if (!mounted) break;

        // Cada mensagem toca a notificação quando aparece.
        _soundService.playNotification();

        await showDialog<void>(
          context: context,
          barrierDismissible: true,
          builder: (_) => _buildMessagePopup(
            newMsgs[i],
            index: i,
            total: newMsgs.length,
          ),
        );

        // Ao fechar (OK), para o som atual antes da próxima.
        _soundService.stopEffects();
      }
    } finally {
      _showingMessageQueue = false;
    }

    // Só depois de mostrar tudo, marca como visto/lido.
    final newest = newMsgs.reduce((a, b) => a.id > b.id ? a : b);
    await _deviceService.setLastMessageId(newest.id);

    try {
      await _apiService.teamMarkMessagesRead(newMsgs.map((m) => m.id).toList());
    } catch (_) {
      // Silencioso — não bloqueia o app
    }
  }

  /// Verifica mensagens novas via polling (chamado pelo timer).
  Future<void> _checkMessages() async {
    try {
      final msgs = await _apiService.teamMessages();
      await _handleNewTeamMessages(msgs);
    } catch (_) {
      // Silencioso — erros de rede não devem afetar o usuário
    }
  }

  /// Inicia o polling de mensagens a cada 10 segundos.
  void _startMessagesPolling() {
    _messagesTimer?.cancel();
    _messagesTimer = Timer.periodic(
      const Duration(seconds: 10),
      (_) => _checkMessages(),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ENVIO DE LOCALIZAÇÃO EM BACKGROUND
  // ════════════════════════════════════════════════════════════

  /// Envia a localização atual ao backend de forma silenciosa.
  Future<void> _sendCurrentLocation() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );
      await _apiService.sendLocation(
        lat: position.latitude,
        lng: position.longitude,
        accuracy: position.accuracy,
      );
    } catch (_) {
      // Silencioso — não bloqueia a UI
    }
  }

  /// Inicia o timer de envio de localização a cada 5 segundos.
  /// Também envia imediatamente ao iniciar.
  void _startLocationTracking() {
    // Enviar imediatamente
    _sendCurrentLocation();

    // Iniciar timer periódico
    _locationTimer?.cancel();
    _locationTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _sendCurrentLocation(),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  FLUXO DO TESOURO
  // ════════════════════════════════════════════════════════════

  Future<void> _startCheckin() async {
    final treasure = _gameState?.currentTreasure;
    if (treasure == null) return;

    // 1. Verificar permissão GPS
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        _showSnackBar('Permissão de localização negada.', isError: true);
        return;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      if (!mounted) return;
      await showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.navyMedium,
          title: const Text('Localização desabilitada',
              style: TextStyle(color: AppColors.gold)),
          content: const Text(
            'A permissão de localização foi negada permanentemente. '
            'Abra as configurações do app para ativar.',
            style: TextStyle(color: AppColors.ivory),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancelar',
                  style: TextStyle(color: AppColors.ivoryMuted)),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(ctx);
                Geolocator.openAppSettings();
              },
              child: const Text('Configurações',
                  style: TextStyle(color: AppColors.gold)),
            ),
          ],
        ),
      );
      return;
    }

    // 2. Verificar GPS ligado
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      _showSnackBar('GPS desligado. Ative a localização.', isError: true);
      return;
    }

    // 3. Ler o QR code PRIMEIRO (a localização é conferida depois)
    if (!mounted) return;
    final qrCode = await Navigator.push<String>(
      context,
      MaterialPageRoute(
        builder: (_) => const QrScannerScreen(title: 'Ler QR Code do Tesouro'),
      ),
    );

    if (qrCode == null || !mounted) {
      setState(() => _flowState = TreasureFlowState.viewingClue);
      return;
    }

    // 4. Agora sim: obter a posição (é o que confirma se está no local)
    Position position;
    try {
      setState(() => _flowState = TreasureFlowState.gettingLocation);
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.viewingClue);
      _showSnackBar(
          'Não foi possível obter a posição. Tente novamente.', isError: true);
      return;
    }

    // 5. Enviar checkin
    setState(() => _flowState = TreasureFlowState.checkingIn);
    try {
      final result = await _apiService.checkIn(
        treasureId: treasure.id,
        lat: position.latitude,
        lng: position.longitude,
        qrCode: qrCode,
      );

      if (!mounted) return;
      setState(() {
        _checkinResult = result;
        _flowState = TreasureFlowState.checkinSuccess;
      });
    } on DecoyQrException catch (e) {
      // QR code FALSO ("isca"): mostra a mensagem e volta para a tela inicial.
      if (!mounted) return;

      setState(() => _flowState = TreasureFlowState.viewingClue);

      _soundService.playRisada();

      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.navyMedium,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
            side: const BorderSide(color: AppColors.gold, width: 2),
          ),
          title: Row(
            children: const [
              Icon(Icons.sentiment_very_dissatisfied,
                  color: AppColors.gold, size: 28),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Pegadinha!',
                  style: TextStyle(
                    color: AppColors.gold,
                    fontWeight: FontWeight.w900,
                    fontSize: 20,
                  ),
                ),
              ),
            ],
          ),
          content: Text(
            e.message,
            style: const TextStyle(
              color: AppColors.ivory,
              fontSize: 16,
              height: 1.5,
              fontWeight: FontWeight.w600,
            ),
          ),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.navyDark,
              ),
              child: const Text(
                'Voltar ao início',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      );
    } on TreasurePausedException catch (e) {
      // A organização pausou este tesouro: o jogo espera aqui.
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.viewingClue);
      _showSnackBar(e.message, isError: false);
      await _loadState();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.viewingClue);
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.viewingClue);
      _showSnackBar('Erro ao fazer checkin.', isError: true);
    }
  }

  Future<void> _takeSelfie() async {
    // ── Tesouro que exige responsável: avisa ANTES de abrir a câmera ──
    if (_gameState?.currentTreasure?.withGuardian == true) {
      final ok = await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          backgroundColor: const Color(0xFF7F1D1D),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
            side: const BorderSide(color: Colors.redAccent, width: 2),
          ),
          title: Row(
            children: const [
              Icon(Icons.warning_amber_rounded,
                  color: Colors.white, size: 28),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'RESPONSÁVEL NA SELFIE',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 17,
                  ),
                ),
              ),
            ],
          ),
          content: const Text(
            'Para registrar este tesouro, pelo menos UM RESPONSÁVEL precisa '
            'aparecer na selfie junto com a equipe.\n\n'
            '⚠️ Se a selfie for enviada SEM o responsável, o tesouro pode ser '
            'DESCLASSIFICADO da sua equipe (a organização vai conferir a foto).\n\n'
            'Chame o responsável e tire a foto com ele aparecendo.',
            style: TextStyle(
              color: Colors.white,
              fontSize: 14,
              height: 1.5,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text(
                'Ainda não',
                style: TextStyle(color: Colors.white70),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF7F1D1D),
              ),
              child: const Text(
                'Entendi, tirar foto',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      );

      if (ok != true) return;
    }

    final picker = ImagePicker();
    try {
      final XFile? image = await picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        // Reduz a foto antes de enviar: uma câmera de celular gera 3-5MB,
        // o que deixava o upload lento/frágil. Assim fica ~200-400KB.
        maxWidth: 1280,
        maxHeight: 1280,
        imageQuality: 70,
      );

      if (image == null || !mounted) return;

      final treasure = _gameState?.currentTreasure;

      // Sem tesouro atual não há o que enviar (e o fluxo não pode ficar
      // preso em "Enviando selfie...").
      if (treasure == null) {
        _showSnackBar('Tesouro atual não encontrado. Recarregue a tela.',
            isError: true);
        return;
      }

      setState(() => _flowState = TreasureFlowState.uploadingSelfie);

      try {
        await _apiService.uploadSelfie(
          treasureId: treasure.id,
          filePath: image.path,
        );

        if (!mounted) return;
        _showSnackBar('Selfie enviada! +5 pontos', isError: false);

        // Selfie OK → ir para a charada
        setState(() => _flowState = TreasureFlowState.showingRiddle);
      } on ApiException catch (e) {
        if (!mounted) return;
        _showSnackBar(e.message, isError: true);
        // Voltar ao card de selfie para tentar novamente
        setState(() => _flowState = TreasureFlowState.checkinSuccess);
      } catch (_) {
        if (!mounted) return;
        _showSnackBar('Erro ao enviar selfie.', isError: true);
        setState(() => _flowState = TreasureFlowState.checkinSuccess);
      }
    } catch (_) {
      // Usuário pode ter negado câmera
      if (!mounted) return;
      _showSnackBar('Não foi possível acessar a câmera.', isError: true);
    }
  }

  Future<void> _submitAnswer(String answer) async {
    final treasure = _gameState?.currentTreasure;
    if (treasure == null) return;

    setState(() => _flowState = TreasureFlowState.submittingAnswer);
    try {
      // Obtém a posição atual (bônus de responder no local: +5 pontos).
      double? lat;
      double? lng;
      try {
        final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            timeLimit: Duration(seconds: 6),
          ),
        );
        lat = pos.latitude;
        lng = pos.longitude;
      } catch (_) {
        // Sem GPS no momento — envia sem posição (sem bônus).
      }

      final result = await _apiService.answer(
        treasureId: treasure.id,
        answer: answer,
        lat: lat,
        lng: lng,
      );

      if (!mounted) return;

      // Este aparelho RESPONDEU agora: guarda a assinatura nova na hora,
      // antes de qualquer outra chamada. Assim o aviso de "outro aparelho
      // concluiu o tesouro" nunca aparece nele (é a corrida do polling).
      if (result.signature.isNotEmpty) {
        _syncSignature = result.signature;
      }

      // Som do acerto (o erro entra em loop pelo _syncSounds, ao renderizar
      // a tela de resultado).
      if (result.correct) {
        _soundService.playAcerto();
      }

      setState(() {
        _answerResult = result;
        _flowState = TreasureFlowState.answerResult;
      });

      // Atualizar estado (pontos/placar) sem alterar o fluxo da tela
      _refreshStateKeepFlow();
    } on TreasurePausedException catch (e) {
      // A organização pausou o tesouro: a equipe volta para a tela de espera.
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.viewingClue);
      _showSnackBar(e.message, isError: false);
      await _loadState();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.showingRiddle);
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.showingRiddle);
      _showSnackBar('Erro ao enviar resposta.', isError: true);
    }
  }

  Future<void> _submitFinalAnswer(String answer) async {
    setState(() => _flowState = TreasureFlowState.submittingAnswer);
    try {
      final result = await _apiService.finalAnswer(answer: answer);

      if (!mounted) return;

      // Som do desafio final: acerto = risada comemorativa; erro = choro.
      if (result.correct) {
        _soundService.playRisada();
      } else {
        _soundService.playChoro();
      }

      setState(() {
        _answerResult = result;
        _flowState = TreasureFlowState.finalResult;
      });

      // Atualizar estado (pontos/placar) sem alterar o fluxo da tela
      _refreshStateKeepFlow();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.finalChallenge);
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      if (!mounted) return;
      setState(() => _flowState = TreasureFlowState.finalChallenge);
      _showSnackBar('Erro ao enviar resposta.', isError: true);
    }
  }

  // ════════════════════════════════════════════════════════════
  //  NAVEGAÇÃO
  // ════════════════════════════════════════════════════════════

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.navyMedium,
        title: const Text('Sair do jogo?',
            style: TextStyle(color: AppColors.gold)),
        content: const Text(
          'Você será desconectado da equipe.',
          style: TextStyle(color: AppColors.ivory),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar',
                style: TextStyle(color: AppColors.ivoryMuted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.redAccent,
              foregroundColor: Colors.white,
            ),
            child: const Text('Sair'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    await _apiService.teamLogout();
    await DeviceService().clearCredentials();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  void _showSnackBar(String message, {required bool isError}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError
            ? Colors.redAccent.withValues(alpha: 0.85)
            : AppColors.navyMedium,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  /// Atualiza o estado do jogo (pontos, leaderboard, etc.) SEM alterar
  /// o fluxo da tela de resultado. Chamado após _submitAnswer.
  Future<void> _refreshStateKeepFlow() async {
    try {
      final state = await _apiService.teamState();
      if (!mounted) return;
      setState(() {
        _gameState = state;
        _unreadMessages = List<TeamMessage>.from(state.messages);
        // NÃO altera _flowState
      });
    } catch (_) {
      // Silencioso — falha de rede não deve bloquear a UI
    }

    // A mudança de progresso foi deste aparelho: registra a assinatura para
    // o aviso de sincronização não disparar aqui (só nos outros aparelhos).
    await _rememberSyncSignature();
  }

  /// Guarda a assinatura atual do progresso (sem avisar).
  Future<void> _rememberSyncSignature() async {
    try {
      final data = await _apiService.teamSync();
      final signature = (data['signature'] as String?) ?? '';

      if (signature.isNotEmpty && mounted) {
        _syncSignature = signature;
      }
    } catch (_) {
      // Silencioso
    }
  }

  /// Nome salvo NESTE aparelho (aparece entre parênteses no título).
  String _deviceName = '';

  /// Está atualizando manualmente (tela "Aguardando o Desafio Final").
  bool _refreshing = false;

  /// Botão "Atualizar" das telas de espera (charada pausada / Desafio Final
  /// bloqueado). Recarrega o estado e avisa quando algo foi liberado.
  Future<void> _refreshManual() async {
    if (_refreshing) return;

    // Como estava antes, para saber se algo foi LIBERADO agora.
    final wasTreasurePaused = _gameState?.currentTreasure?.isPaused == true;
    final wasFinalBlocked =
        _gameState?.finalAvailable == true && _gameState?.finalBlocked == true;

    setState(() => _refreshing = true);

    try {
      final state = await _apiService.teamState();
      if (!mounted) return;

      final treasureReleased = wasTreasurePaused &&
          state.currentTreasure != null &&
          !state.currentTreasure!.isPaused;
      final finalReleased =
          wasFinalBlocked && state.finalAvailable && !state.finalBlocked;

      setState(() {
        _gameState = state;

        // Liberou o Desafio Final? Entra no desafio na hora.
        if (finalReleased) {
          _flowState = TreasureFlowState.finalChallenge;
        }

        // Liberou o tesouro? Volta para a dica/normal do tesouro.
        if (treasureReleased) {
          _flowState = TreasureFlowState.viewingClue;
        }
      });

      if (treasureReleased) {
        _soundService.playNotification();
        _showSnackBar('O tesouro foi liberado! Boa sorte!', isError: false);
      } else if (finalReleased) {
        _soundService.playNotification();
        _showSnackBar(
          'O Desafio Final foi liberado! Boa sorte!',
          isError: false,
        );
      }
    } catch (_) {
      if (mounted) {
        _showSnackBar('Não foi possível atualizar. Tente de novo.', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _refreshing = false);
      }
    }
  }

  /// Nome da equipe (ex.: "Equipe Preta").
  String get _teamTitle {
    final name = widget.teamData['name'] as String?;

    return (name != null && name.isNotEmpty) ? name : 'Equipe';
  }

  /// Abre o modal para editar o nome deste aparelho.
  Future<void> _editDeviceName() async {
    final typed = await showDeviceNameDialog(
      context,
      initial: _deviceName,
    );

    if (typed == null || !mounted) return;

    final name = typed.trim();

    if (name.isEmpty || name == _deviceName) return;

    // BLOQUEIO: valida ANTES de gravar. Se o nome não for permitido, não
    // salva aqui nem no aparelho — o modal já avisa e nada é atualizado.
    if (NameBlocklist.isBlocked(name)) {
      _showSnackBar('Esse nome não é permitido. Escolha outro.', isError: true);
      return;
    }

    // Só grava no SERVIDOR primeiro; se ele recusar, nada muda no aparelho.
    try {
      await _apiService.teamSetName(name);
    } catch (e) {
      if (!mounted) return;

      _showSnackBar(
        e is ApiException ? e.message : 'Não foi possível salvar o nome.',
        isError: true,
      );
      return;
    }

    if (!mounted) return;

    await _deviceService.setDeviceName(name);

    setState(() => _deviceName = name);

    _showSnackBar('Nome atualizado: $name', isError: false);
  }

  /// Carrega o nome salvo neste aparelho.
  Future<void> _loadDeviceName() async {
    final name = await _deviceService.getDeviceName();

    if (!mounted || name.isEmpty) return;

    setState(() => _deviceName = name);
  }

  /// Sincronização em tempo real entre os aparelhos da MESMA equipe.
  ///
  /// Quando a assinatura do progresso muda, outro aparelho concluiu um
  /// tesouro: toca a notificação, avisa e recarrega o estado (passando para
  /// o próximo tesouro automaticamente).
  Future<void> _checkSync() async {
    try {
      final data = await _apiService.teamSync();
      final signature = (data['signature'] as String?) ?? '';

      // ── FIM DE JOGO ────────────────────────────────
      // Outro aparelho acertou o desafio final (ou a organização encerrou):
      // recarrega o estado para cair na tela da equipe vencedora.
      if (data['game_over'] == true && _gameState?.gameOver != true) {
        _soundService.stopLoop();
        _soundService.stopBackgroundMusic();
        await _loadState();
        return;
      }

      if (signature.isEmpty) return;

      // Primeira leitura: só guarda (não avisa).
      if (_syncSignature == null) {
        _syncSignature = signature;
        return;
      }

      if (signature == _syncSignature) return;

      _syncSignature = signature;

      // Este aparelho está na TELA DE RESULTADO (pontos + botão para o
      // próximo tesouro / desafio final): quem respondeu foi ele. Aqui não
      // entra o aviso de "outro aparelho concluiu" nem recarrega a tela —
      // senão a equipe perderia os pontos e o botão de avançar.
      if (_flowState == TreasureFlowState.answerResult ||
          _flowState == TreasureFlowState.finalResult) {
        return;
      }

      // Som de notificação — importante para os outros aparelhos perceberem.
      _soundService.playNotification();

      if (!mounted) return;

      showDialog<void>(
        context: context,
        barrierDismissible: true,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.navyMedium,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: Row(
            children: const [
              Icon(Icons.sync, color: AppColors.gold),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Tesouro concluído!',
                  style: TextStyle(
                    color: AppColors.gold,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ),
            ],
          ),
          content: const Text(
            'Outro aparelho da sua equipe completou um tesouro. '
            'O app já foi atualizado para o próximo passo!',
            style: TextStyle(color: AppColors.ivory, height: 1.5),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text(
                'OK',
                style: TextStyle(
                  color: AppColors.gold,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      );

      // Recarrega o estado: avança para o próximo tesouro / desafio final.
      await _loadState();
    } catch (_) {
      // Silencioso — rede não pode atrapalhar o jogo
    }
  }

  /// Inicia o polling de sincronização (a cada 5 segundos).
  void _startSyncPolling() {
    _syncTimer?.cancel();
    _syncTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _checkSync(),
    );
    _checkSync();
  }

  // ════════════════════════════════════════════════════════════
  //  BUILD
  // ════════════════════════════════════════════════════════════

  @override
  Widget build(BuildContext context) {
    // Mantém os sons em sincronia com a tela visível: som de erro em loop na
    // tela de erro da charada e música de fundo no desafio final.
    _syncSounds();

    // ── JOGO ENCERRADO ──────────────────────────────
    // Alguém acertou o Desafio Final (ou a organização encerrou): o jogo
    // fica BLOQUEADO e só mostra a equipe vencedora. Nada de charada, mapa,
    // cofre ou desafio final.
    if (_gameState != null && _gameState!.gameOver) {
      return _buildGameOverScreen();
    }

    // ── Abas dinâmicas ──────────────────────────────
    // A aba Cofre só aparece quando a equipe alcançou o desafio final.
    final bool showCofre =
        _gameState != null && _gameState!.finalAvailable;
    final List<Widget> tabPages = [
      _buildTreasureTab(),
      _buildStoryContent(),
      _buildRulesContent(),
      if (showCofre) _buildVaultContent(),
      _buildLeaderboardContent(),
    ];
    final List<NavigationDestination> tabDestinations = [
      const NavigationDestination(
        icon: Icon(Icons.account_balance_wallet_outlined,
            color: AppColors.ivoryMuted),
        selectedIcon:
            Icon(Icons.account_balance_wallet, color: AppColors.gold),
        label: 'Tesouro',
      ),
      const NavigationDestination(
        icon: Icon(Icons.info_outline, color: AppColors.ivoryMuted),
        selectedIcon: Icon(Icons.info, color: AppColors.gold),
        label: 'História',
      ),
      const NavigationDestination(
        icon: Icon(Icons.gavel_outlined, color: AppColors.ivoryMuted),
        selectedIcon: Icon(Icons.gavel, color: AppColors.gold),
        label: 'Regras',
      ),
      if (showCofre)
        const NavigationDestination(
          icon: Icon(Icons.lock_outline, color: AppColors.ivoryMuted),
          selectedIcon: Icon(Icons.lock, color: AppColors.gold),
          label: 'Cofre',
        ),
      const NavigationDestination(
        icon: Icon(Icons.leaderboard_outlined,
            color: AppColors.ivoryMuted),
        selectedIcon:
            Icon(Icons.leaderboard, color: AppColors.gold),
        label: 'Pontos',
      ),
      const NavigationDestination(
        icon: Icon(Icons.logout, color: AppColors.ivoryMuted),
        label: 'Sair',
      ),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                color: _parseColor(widget.teamData['color'] as String? ?? ''),
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                _deviceName.isEmpty
                    ? _teamTitle
                    : '$_teamTitle ($_deviceName)',
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 17,
                ),
              ),
            ),
            // Lápis: edita o nome deste aparelho (fora dos parênteses).
            IconButton(
              onPressed: _editDeviceName,
              tooltip: 'Editar o meu nome',
              visualDensity: VisualDensity.compact,
              icon: const Icon(Icons.edit, size: 18, color: AppColors.gold),
            ),
          ],
        ),
        actions: [
          if (_gameState != null)
            Center(
              child: Padding(
                padding: const EdgeInsets.only(right: 4),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.gold.withValues(alpha: 0.4),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.star, color: AppColors.gold, size: 15),
                      const SizedBox(width: 4),
                      Text(
                        '${_gameState!.team.points}',
                        style: const TextStyle(
                          color: AppColors.gold,
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          const SizedBox(width: 8),
        ],
      ),

      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold))
          : _error != null
              ? _buildError()
              : tabPages[_currentTab.clamp(0, tabPages.length - 1)],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentTab,
        onDestinationSelected: (index) {
          // Último destino = Sair (sempre o último da lista)
          if (index == tabDestinations.length - 1) {
            _logout();
          } else {
            setState(() => _currentTab = index);
          }
        },
        backgroundColor: AppColors.navyDark,
        indicatorColor: AppColors.gold.withValues(alpha: 0.15),
        height: 65,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: tabDestinations,
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline,
                color: Colors.redAccent, size: 48),
            const SizedBox(height: 16),
            Text(_error!,
                textAlign: TextAlign.center,
                style:
                    const TextStyle(color: AppColors.ivory, fontSize: 16)),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _loadState,
              icon: const Icon(Icons.refresh),
              label: const Text('Tentar novamente'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.navyDark,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ABA TESOURO
  // ════════════════════════════════════════════════════════════

  /// O desafio final está sendo exibido AGORA?
  ///
  /// Fonte única de verdade — usada tanto para renderizar a tela quanto para
  /// tocar a música de fundo (assim nunca divergem).
  ///
  /// Nunca fica visível por cima da tela de resultado: depois de acertar o
  /// ÚLTIMO tesouro a equipe continua vendo o resultado e só vai para o
  /// desafio final ao tocar no botão "Próximo desafio".
  bool get _isFinalChallengeVisible {
    final state = _gameState;

    if (state == null) return false;

    // Só quando não há mais tesouro para caçar E o desafio está liberado.
    if (state.currentTreasure != null || !state.finalAvailable) return false;

    // Nunca por cima das telas de resultado (charada ou desafio final).
    if (_flowState == TreasureFlowState.answerResult) return false;
    if (_flowState == TreasureFlowState.finalResult) return false;

    return true;
  }

  /// TELA DE FIM DE JOGO — o app fica bloqueado e mostra a equipe vencedora.
  ///
  /// Aparece quando alguém acerta o Desafio Final (ou a organização encerra o
  /// jogo). Não dá para voltar para o tesouro, o cofre ou o desafio final.
  Widget _buildGameOverScreen() {
    final winner = _gameState?.winner;
    final teamWon = _gameState?.teamWon == true;
    final winnerColor = _parseColor(winner?.color ?? '');
    final nome = (winner?.name.isNotEmpty ?? false)
        ? winner!.name
        : 'Equipe vencedora';

    return Scaffold(
      backgroundColor: AppColors.navyDark,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.all(26),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.14),
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.gold, width: 3),
                  ),
                  child: const Icon(
                    Icons.emoji_events,
                    color: AppColors.gold,
                    size: 76,
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  teamWon ? 'VOCÊS VENCERAM!' : 'FIM DE JOGO',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: AppColors.gold,
                    fontSize: 34,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1,
                  ),
                ),
                const SizedBox(height: 26),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 22,
                    vertical: 24,
                  ),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        winnerColor.withValues(alpha: 0.95),
                        winnerColor.withValues(alpha: 0.65),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.gold, width: 2),
                  ),
                  child: Column(
                    children: [
                      const Text(
                        'EQUIPE VENCEDORA',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 3,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        nome,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 30,
                          fontWeight: FontWeight.w900,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        '${winner?.points ?? 0} pontos',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 26),
                Text(
                  teamWon
                      ? 'Parabéns! Vocês acertaram o Desafio Final e '
                          'venceram a Caça ao Tesouro. 🎉'
                      : 'A Caça ao Tesouro terminou. '
                          'Obrigado por participar!',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: AppColors.ivory,
                    fontSize: 16,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 34),
                OutlinedButton.icon(
                  onPressed: _logout,
                  icon: const Icon(Icons.logout, size: 18),
                  label: const Text('Sair'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.ivoryMuted,
                    side: BorderSide(
                      color: AppColors.ivoryMuted.withValues(alpha: 0.5),
                    ),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 26,
                      vertical: 12,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildTreasureTab() {
    if (_gameState == null) return const SizedBox();

    // TESOURO PAUSADO pela organização: o jogo para aqui. A equipe espera
    // até liberarem o tesouro (a tela volta no próximo "Atualizar").
    if (_gameState!.currentTreasure?.isPaused == true) {
      return _buildTreasurePausedState();
    }

    // Sem tesouro atual e não é final → aguardando
    if (_gameState!.currentTreasure == null && !_gameState!.finalAvailable) {
      return _buildWaitingState();
    }

    // Desafio final liberado → mostra o desafio (esconde a tela de resultado
    // até a equipe tocar no botão "Próximo desafio").
    if (_isFinalChallengeVisible) {
      return _buildFinalChallenge();
    }

    // Flow state
    Widget content;
    switch (_flowState) {
      case TreasureFlowState.viewingClue:
        content = _buildClueView();
        break;
      case TreasureFlowState.gettingLocation:
        content = _buildLoadingState('Obtendo sua localização...');
        break;
      case TreasureFlowState.checkingIn:
        content = _buildLoadingState('Verificando checkin...');
        break;
      case TreasureFlowState.checkinSuccess:
        content = _buildCheckinSuccess();
        break;
      case TreasureFlowState.uploadingSelfie:
        content = _buildLoadingState('Enviando selfie...');
        break;
      case TreasureFlowState.showingRiddle:
        content = _buildRiddleView();
        break;
      case TreasureFlowState.submittingAnswer:
        content = _buildLoadingState('Verificando resposta...');
        break;
      case TreasureFlowState.answerResult:
        content = _buildAnswerResult();
        break;
      case TreasureFlowState.finalChallenge:
        content = _buildFinalChallenge();
        break;
      case TreasureFlowState.finalResult:
        content = _buildFinalResult();
        break;
    }

    // Envolver com banner de mensagens se houver
    return Column(
      children: [
        // Banner de mensagens existentes
        ..._unreadMessages.map((msg) => _buildMessageBanner(msg)),
        // Conteúdo principal
        Expanded(child: content),
      ],
    );
  }

  Widget _buildMessageBanner(TeamMessage msg) {
    final style = MessageKindStyle.forKind(msg.kind);
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: style.color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: style.color.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: style.color.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Icon(
              style.icon,
              color: style.color,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (msg.title.isNotEmpty
                      ? msg.title
                      : style.fallbackTitle
                  ).toUpperCase(),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.5,
                    color: style.color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  msg.message,
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.4,
                    color: AppColors.ivory,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Popup com UMA mensagem (fila sequencial).
  ///
  /// Exibe o ícone e título coloridos por tipo de mensagem. Se houver mais
  /// de uma mensagem na fila, mostra um contador discreto "X de Y".
  Widget _buildMessagePopup(
    TeamMessage msg, {
    int index = 0,
    int total = 1,
  }) {
    final style = MessageKindStyle.forKind(msg.kind);
    final title = style.effectiveTitle(msg.title);
    final bool showCounter = total > 1;

    return AlertDialog(
      backgroundColor: AppColors.navyMedium,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: style.color.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(style.icon, color: style.color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: style.color,
              ),
            ),
          ),
        ],
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            msg.message,
            style: const TextStyle(
              fontSize: 15,
              height: 1.5,
              color: AppColors.ivory,
            ),
          ),
          if (showCounter) ...[
            const SizedBox(height: 16),
            Center(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.navyDark,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '${index + 1} de $total',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ivoryMuted.withValues(alpha: 0.8),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text(
            'OK',
            style: TextStyle(
              color: AppColors.gold,
              fontWeight: FontWeight.w700,
              fontSize: 15,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildWaitingState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.navyMedium,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.hourglass_top,
                  color: AppColors.gold, size: 40),
            ),
            const SizedBox(height: 20),
            const Text(
              'Aguardando...',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
                color: AppColors.ivory,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'O jogo ainda não começou ou\naguarde o próximo passo.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: AppColors.ivoryMuted,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 24),
            OutlinedButton.icon(
              onPressed: _loadState,
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Atualizar'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.gold,
                side: BorderSide(
                    color: AppColors.gold.withValues(alpha: 0.4)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildClueView() {
    final treasure = _gameState!.currentTreasure!;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // ══════════════════════════════════════════════════
          //  AVISO BEM VISÍVEL: precisa dos responsáveis
          // ══════════════════════════════════════════════════
          if (treasure.withGuardian) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFB91C1C), Color(0xFF7F1D1D)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.redAccent, width: 2),
                boxShadow: [
                  BoxShadow(
                    color: Colors.redAccent.withValues(alpha: 0.4),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: const [
                      Icon(Icons.warning_amber_rounded,
                          color: Colors.white, size: 30),
                      SizedBox(width: 8),
                      Text(
                        'ATENÇÃO!',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Este tesouro deve ser encontrado na companhia dos seus RESPONSÁVEIS.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Pelo menos UM responsável precisa estar com a equipe — '
                    'e aparecer na selfie. Tesouro sem responsável pode ser '
                    'DESCLASSIFICADO.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],

          // ── Card do tesouro ────────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.2),
              ),
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.account_balance_wallet_outlined,
                    color: AppColors.gold,
                    size: 32,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  treasure.name,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ivory,
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.navyDark.withValues(alpha: 0.6),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.gold.withValues(alpha: 0.1),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.lightbulb_outline,
                              color: AppColors.gold.withValues(alpha: 0.7),
                              size: 16),
                          const SizedBox(width: 6),
                          Text(
                            'DICA DO LOCAL',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 2,
                              color: AppColors.gold.withValues(alpha: 0.7),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        treasure.clue ?? 'Procure pelo tesouro...',
                        style: const TextStyle(
                          fontSize: 15,
                          height: 1.5,
                          color: AppColors.ivory,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // ── Botão "Estou no local" ────────────────────
          SizedBox(
            width: double.infinity,
            height: 52,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [AppColors.gold, AppColors.goldDark],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.gold.withValues(alpha: 0.3),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: ElevatedButton.icon(
                onPressed: _startCheckin,
                icon: const Icon(Icons.location_on, size: 20),
                label: const Text(
                  'Estou no local',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  shadowColor: Colors.transparent,
                  foregroundColor: AppColors.navyDark,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCheckinSuccess() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // ── Card: Selfie obrigatória no local ──────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.3),
              ),
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.camera_alt_outlined,
                      color: AppColors.gold, size: 36),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Selfie Obrigatória no Local',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ivory,
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: AppColors.gold.withValues(alpha: 0.3),
                    ),
                  ),
                  child: const Text(
                    'A foto deve mostrar o local e os alunos na foto.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 14,
                      height: 1.5,
                      color: AppColors.ivory,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.orangeAccent.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: Colors.orangeAccent.withValues(alpha: 0.35),
                    ),
                  ),
                  child: const Text(
                    'Se os alunos não saírem na foto, '
                    'os pontos podem ser CANCELADOS.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      height: 1.4,
                      color: Colors.orangeAccent,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(height: 18),

                // ── Aviso: responsáveis na selfie (tesouro exige) ──
                if (_gameState?.currentTreasure?.withGuardian == true) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFB91C1C), Color(0xFF7F1D1D)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.redAccent, width: 2),
                    ),
                    child: Column(
                      children: const [
                        Icon(Icons.family_restroom,
                            color: Colors.white, size: 32),
                        SizedBox(height: 8),
                        Text(
                          'OS RESPONSÁVEIS DEVEM APARECER NA SELFIE',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                            height: 1.35,
                            letterSpacing: 0.5,
                          ),
                        ),
                        SizedBox(height: 8),
                        Text(
                          'Este tesouro só vale se pelo menos UM responsável '
                          'aparecer na foto junto com a equipe. '
                          'Sem o responsável, o tesouro pode ser DESCLASSIFICADO.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                ],

                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _takeSelfie,
                    icon: const Icon(Icons.camera_alt, size: 20),
                    label: const Text(
                      'Tirar selfie',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.gold,
                      foregroundColor: AppColors.navyDark,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRiddleView() {
    final result = _checkinResult;
    if (result == null) return const SizedBox();

    return _RiddleInput(
      riddleText: result.riddle,
      answerLength: result.answerLength,
      onSubmit: _submitAnswer,
    );
  }

  /// Traduz o label da API em texto legível para a UI.
  String _breakdownText(String label, int points) {
    switch (label) {
      case 'Selfie enviada':
        return '+$points pontos pela selfie enviada';
      case 'Resposta correta':
        return '+$points pontos de resposta correta';
      case 'Responder no local':
        return '+$points pontos por responder no local';
      case 'Primeiro a encontrar!':
        return '+$points pontos por ser o primeiro a encontrar';
      default:
        return '+$points pontos';
    }
  }

  Widget _buildAnswerResult() {
    final result = _answerResult;
    if (result == null) return const SizedBox();

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ── GIF do resultado (surpresa/chorando) ────
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Image.asset(
                result.correct
                    ? 'assets/images/surpresa.gif'
                    : 'assets/images/chorando.gif',
                width: double.infinity,
                height: 240,
                fit: BoxFit.contain,
              ),
            ),
            const SizedBox(height: 20),
            Text(
              result.correct ? 'Correto!' : 'Incorreto!',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: result.correct ? Colors.greenAccent : Colors.redAccent,
              ),
            ),

            // ══════════════════════════════════════════════
            //  CASO CORRETO → lista de pontos conquistados
            // ══════════════════════════════════════════════
            if (result.correct) ...[
              const SizedBox(height: 20),

              // ── Lista de breakdown ───────────────────
              _buildBreakdownList(result),

              const SizedBox(height: 20),

              // ── Total em verde ───────────────────────
              Text(
                '+${result.totalEarned} pontos',
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w900,
                  color: Colors.greenAccent,
                ),
              ),

              // ── Botão "Próximo tesouro" ──────────────
              const SizedBox(height: 28),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [AppColors.gold, AppColors.goldDark],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.gold.withValues(alpha: 0.3),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: ElevatedButton.icon(
                    onPressed: _loadState,
                    icon: Icon(
                      result.finalAvailable
                          ? Icons.emoji_events
                          : Icons.arrow_forward,
                      size: 20,
                    ),
                    label: Text(
                      // Era o último tesouro: o próximo passo é o desafio final.
                      result.finalAvailable
                          ? 'Próximo desafio'
                          : 'Próximo tesouro',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      shadowColor: Colors.transparent,
                      foregroundColor: AppColors.navyDark,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
              ),
            ],

            // ══════════════════════════════════════════════
            //  CASO ERRADO → mantém layout original
            // ══════════════════════════════════════════════
            if (!result.correct) ...[
              // ── Erro NÃO tira pontos (sem penalidade) ──
              // Só mostra o selo de perda se o servidor realmente descontou.
              if (result.delta < 0) ...[
                const SizedBox(height: 12),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.redAccent.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: Colors.redAccent.withValues(alpha: 0.4),
                    ),
                  ),
                  child: Text(
                    'perdeu ${result.delta.abs()} pontos',
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: Colors.redAccent,
                    ),
                  ),
                ),
              ] else ...[
                const SizedBox(height: 10),
                const Text(
                  'Sem perda de pontos — tente novamente!',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ivoryMuted,
                  ),
                ),
              ],

              // ── Total atualizado ─────────────────────
              const SizedBox(height: 6),
              Text(
                'Total: ${result.points} pontos',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ivoryMuted,
                ),
              ),

              // ── Botão "Tentar Novamente" ─────────────
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => setState(
                      () => _flowState = TreasureFlowState.showingRiddle),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.gold,
                    foregroundColor: AppColors.navyDark,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: const Text(
                    'Tentar Novamente',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Monta a lista de breakdown de pontos.
  /// Se a API retornou itens, usa eles; caso contrário, monta um fallback.
  Widget _buildBreakdownList(AnswerResult result) {
    List<BreakdownItem> items;

    if (result.breakdown.isNotEmpty) {
      items = result.breakdown;
    } else {
      // Fallback: servidor antigo sem breakdown — montar lista básica
      items = [];

      // Selfie sempre foi enviada neste ponto (o fluxo exige selfie antes
      // da charada), então incluí-la no fallback é razoável.
      items.add(const BreakdownItem(label: 'Selfie enviada', points: 5));

      // Resposta correta: tentar deduzir de delta; senão, 20 padrão.
      final correctPoints = result.delta > 0 ? result.delta : 20;
      items.add(BreakdownItem(
        label: 'Resposta correta',
        points: correctPoints,
      ));
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.navyMedium,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: Colors.greenAccent.withValues(alpha: 0.2),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: items.map((item) {
          final text = _breakdownText(item.label, item.points);
          // Separar "+N" do resto do texto
          final plusMatch = RegExp(r'^\+(\d+)').firstMatch(text);
          final String prefix;
          final String suffix;
          if (plusMatch != null) {
            prefix = plusMatch.group(0)!;
            suffix = text.substring(plusMatch.end);
          } else {
            prefix = '';
            suffix = text;
          }
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Bullet
                const Text('•  ', style: TextStyle(
                  color: Colors.greenAccent,
                  fontSize: 15,
                )),
                // "+N" em verde/dourado negrito
                if (prefix.isNotEmpty)
                  Text(
                    prefix,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Colors.greenAccent,
                    ),
                  ),
                // Texto da conquista em cor clara
                Expanded(
                  child: Text(
                    suffix,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                      color: AppColors.ivory,
                    ),
                  ),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildFinalChallenge() {
    // Desafio Final bloqueado pela organização: fica aguardando a liberação.
    if (_gameState?.finalBlocked == true) {
      return _buildFinalAwaiting();
    }

    // O desafio abre automaticamente quando finalAvailable
    return _FinalAnswerInput(
      clue: _gameState?.finalClue ?? '',
      correctPoints: _gameState?.finalCorrectPoints ?? 100,
      wrongPenalty: _gameState?.finalWrongPenalty ?? 20,
      onSubmit: _submitFinalAnswer,
    );
  }

  /// "Aguardando..." — a CHARADA (ou o tesouro) ainda não foi liberada.
  ///
  /// Pode ser a charada pausada pela organização ou o Desafio Final bloqueado.
  /// Tem o botão de atualizar: quando liberarem, o jogo volta na hora.
  Widget _buildWaitingForRelease({
    required String message,
    required IconData icon,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.12),
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.gold, width: 2),
              ),
              child: Icon(icon, color: AppColors.gold, size: 56),
            ),
            const SizedBox(height: 28),
            const Text(
              'Aguardando...',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: AppColors.gold,
                fontSize: 28,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppColors.ivory,
                fontSize: 17,
                height: 1.5,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Toque em atualizar para verificar.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: AppColors.ivory.withValues(alpha: 0.6),
                fontSize: 13,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 32),
            ElevatedButton.icon(
              onPressed: _refreshing ? null : _refreshManual,
              icon: _refreshing
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.navyDark,
                      ),
                    )
                  : const Icon(Icons.refresh_rounded, size: 22),
              label: Text(
                _refreshing ? 'Atualizando...' : 'Atualizar',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.navyDark,
                padding: const EdgeInsets.symmetric(
                  horizontal: 32,
                  vertical: 16,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Tesouro pausado pela organização — o jogo espera a liberação.
  Widget _buildTreasurePausedState() {
    return _buildWaitingForRelease(
      message: 'Estamos aguardando a liberação do próximo tesouro.',
      icon: Icons.hourglass_top_rounded,
    );
  }

  /// "Aguardando..." — o Desafio Final ainda não foi liberado pela organização.
  Widget _buildFinalAwaiting() {
    return _buildWaitingForRelease(
      message: 'Estamos aguardando a liberação do Desafio Final.',
      icon: Icons.hourglass_top_rounded,
    );
  }

  Widget _buildFinalResult() {
    final result = _answerResult;
    if (result == null) return const SizedBox();

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: result.correct
                    ? AppColors.gold.withValues(alpha: 0.15)
                    : Colors.redAccent.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(
                result.correct ? Icons.emoji_events : Icons.sentiment_dissatisfied,
                color: result.correct ? AppColors.gold : Colors.redAccent,
                size: 52,
              ),
            ),
            const SizedBox(height: 24),
            if (result.correct) ...[
              const Text(
                'VOCÊ VENCEU!',
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 3,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(height: 8),
              if (result.points > 0)
                Text(
                  '+${result.points} pontos',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.gold,
                  ),
                ),
            ] else ...[
              const Text(
                'Resposta Incorreta',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: Colors.redAccent,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                result.message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 15,
                  color: AppColors.ivory,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildLoadingState(String message) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(color: AppColors.gold),
          const SizedBox(height: 16),
          Text(
            message,
            style: const TextStyle(
              fontSize: 15,
              color: AppColors.ivoryMuted,
            ),
          ),
        ],
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ABA COFRE
  // ════════════════════════════════════════════════════════════

  Widget _buildVaultContent() {
    // ── Cofre da Gincana ─────────────────────────────────────
    // O link para abrir o cofre NÃO aparece aqui: só a organização/admin tem
    // acesso a ele. A equipe é orientada a ir até o local do cofre (a página
    // pública só abre num raio de 100 m de onde o cofre está).
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // ── Título ──────────────────────────────────
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.lock,
                color: AppColors.gold, size: 36),
          ),
          const SizedBox(height: 14),
          const Text(
            'COFRE DA GINCANA',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              letterSpacing: 3,
              color: AppColors.gold,
            ),
          ),

          const SizedBox(height: 20),

          // ── Card: Como funciona ────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.15),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'COMO FUNCIONA',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2,
                    color: AppColors.gold.withValues(alpha: 0.8),
                  ),
                ),
                const SizedBox(height: 12),
                _vaultStep(1,
                    'Encontre o código de 9 dígitos escondido no mundo físico.'),
                _vaultStep(2,
                    'Vá até o LOCAL do cofre: a página do cofre só abre para quem estiver perto dele (no raio de 100 m).'),
                _vaultStep(3,
                    'No local, acesse a página do cofre e digite os 9 dígitos.'),
                _vaultStep(4,
                    'O cofre avisa que a senha final será revelada — só continue quando a outra equipe não estiver vendo.'),
                _vaultStep(5,
                    'Se o código estiver certo, o cofre revela a senha do desafio final.'),
                _vaultStep(6,
                    'Volte em Tesouro e use essa senha no desafio final para encerrar a caça ao tesouro!'),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // ── Aviso de privacidade ───────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.orangeAccent.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: Colors.orangeAccent.withValues(alpha: 0.35),
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.visibility_off,
                    color: Colors.orangeAccent, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'A senha aparece na tela! Só abra o cofre quando a outra equipe não estiver por perto.',
                    style: const TextStyle(
                      fontSize: 14,
                      height: 1.4,
                      fontWeight: FontWeight.w600,
                      color: Colors.orangeAccent,
                    ),
                  ),
                ),
              ],
            ),
          ),

        ],
      ),
    );
  }

  /// Passo numerado para a seção "Como funciona".
  Widget _vaultStep(int number, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '$number',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.gold,
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                fontSize: 14,
                height: 1.4,
                color: AppColors.ivory,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ABA HISTÓRIA
  // ════════════════════════════════════════════════════════════

  Widget _buildStoryContent() {
    final story = _gameState?.story ?? '';
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.auto_stories,
                  color: AppColors.gold, size: 32),
            ),
            const SizedBox(height: 16),
            const Text(
              'A HISTÓRIA',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                letterSpacing: 3,
                color: AppColors.gold,
              ),
            ),
            const SizedBox(height: 16),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.navyMedium,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.gold.withValues(alpha: 0.15),
                  ),
                ),
                child: story.isNotEmpty
                    ? SingleChildScrollView(
                        child: HtmlWidget(
                          story,
                          textStyle: const TextStyle(
                            fontSize: 14,
                            height: 1.7,
                            color: AppColors.ivory,
                          ),
                        ),
                      )
                    : const Center(
                        child: Text(
                          'A história será revelada em breve...',
                          style: TextStyle(
                            fontStyle: FontStyle.italic,
                            color: AppColors.ivoryMuted,
                          ),
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ABA REGRAS
  // ════════════════════════════════════════════════════════════

  Widget _buildRulesContent() {
    final rules = _gameState?.rules ?? '';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.gavel, color: AppColors.gold, size: 32),
            ),
            const SizedBox(height: 16),
            const Text(
              'REGRAS DO JOGO',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                letterSpacing: 3,
                color: AppColors.gold,
              ),
            ),
            const SizedBox(height: 16),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.navyMedium,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.gold.withValues(alpha: 0.15),
                  ),
                ),
                child: rules.isNotEmpty
                    ? SingleChildScrollView(
                        child: HtmlWidget(
                          rules,
                          textStyle: const TextStyle(
                            fontSize: 14,
                            height: 1.7,
                            color: AppColors.ivory,
                          ),
                        ),
                      )
                    : const Center(
                        child: Text(
                          'As regras serão publicadas em breve...',
                          style: TextStyle(
                            fontStyle: FontStyle.italic,
                            color: AppColors.ivoryMuted,
                          ),
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════
  //  ABA PONTOS (LEADERBOARD)
  // ════════════════════════════════════════════════════════════

  Widget _buildLeaderboardContent() {
    final leaderboard = _gameState?.leaderboard ?? [];
    final myPoints = _gameState?.team.points ?? 0;
    final myName = (widget.teamData['name'] as String?) ?? '';

    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // ── Meus pontos ──────────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.3),
              ),
            ),
            child: Column(
              children: [
                const Text(
                  'SEUS PONTOS',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 3,
                    color: AppColors.ivoryMuted,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  '$myPoints',
                  style: const TextStyle(
                    fontSize: 40,
                    fontWeight: FontWeight.w900,
                    color: AppColors.gold,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // ── Leaderboard ──────────────────────────────
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'CLASSIFICAÇÃO',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                letterSpacing: 2,
                color: AppColors.gold,
              ),
            ),
          ),
          const SizedBox(height: 12),

          Expanded(
            child: leaderboard.isEmpty
                ? const Center(
                    child: Text(
                      'Nenhuma pontuação ainda.',
                      style: TextStyle(
                          color: AppColors.ivoryMuted, fontSize: 14),
                    ),
                  )
                : ListView.builder(
                    itemCount: leaderboard.length,
                    itemBuilder: (context, index) {
                      final entry = leaderboard[index];
                      final isMe = entry.team == myName;
                      return _LeaderboardTile(
                        rank: index + 1,
                        teamName: entry.team,
                        points: entry.points,
                        status: entry.status,
                        isMe: isMe,
                        teamColor: _leaderboardColor(entry.color, entry.team),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Color _parseColor(String name) {
    return AppColors.teamColor(name);
  }

  /// Cor de fundo da equipe na CLASSIFICAÇÃO.
  ///
  /// Preta = preto e Laranja = laranja (com o texto sempre em branco).
  Color _leaderboardColor(String colorKey, String name) {
    final key = (colorKey.isNotEmpty ? colorKey : name).toLowerCase();

    if (key.contains('laranja')) {
      return const Color(0xFFE67E22);
    }

    if (key.contains('preta') || key.contains('preto')) {
      return Colors.black;
    }

    return AppColors.teamColor(key);
  }
}

// ═══════════════════════════════════════════════════════════════
//  ENUM: Estados do fluxo do tesouro
// ═══════════════════════════════════════════════════════════════
enum TreasureFlowState {
  viewingClue,
  gettingLocation,
  checkingIn,
  checkinSuccess,
  uploadingSelfie,
  showingRiddle,
  submittingAnswer,
  answerResult,
  finalChallenge,
  finalResult,
}

// ═══════════════════════════════════════════════════════════════
//  _RiddleInput — Input de resposta numérica em caixas
// ═══════════════════════════════════════════════════════════════
class _RiddleInput extends StatefulWidget {
  final String riddleText;
  final int answerLength;
  final ValueChanged<String> onSubmit;

  const _RiddleInput({
    required this.riddleText,
    required this.answerLength,
    required this.onSubmit,
  });

  @override
  State<_RiddleInput> createState() => _RiddleInputState();
}

class _RiddleInputState extends State<_RiddleInput> {
  int get _numDigits => widget.answerLength;
  late List<TextEditingController> _controllers;
  late List<FocusNode> _focusNodes;

  @override
  void initState() {
    super.initState();
    _controllers = List.generate(_numDigits, (_) => TextEditingController());
    _focusNodes = List.generate(_numDigits, (_) => FocusNode());
  }

  @override
  void dispose() {
    for (final c in _controllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  String get _fullCode =>
      _controllers.map((c) => c.text).join();

  bool get _isComplete => _fullCode.length == _numDigits;

  void _onDigitChanged(int index, String value) {
    if (value.length > 1) {
      _controllers[index].text = value[value.length - 1];
      _controllers[index].selection = TextSelection.fromPosition(
        TextPosition(offset: 1),
      );
    }

    if (value.isNotEmpty && index < _numDigits - 1) {
      _focusNodes[index + 1].requestFocus();
    }

    if (_isComplete) {
      // Pequeno delay para feedback visual
      Future.delayed(const Duration(milliseconds: 200), () {
        if (mounted) {
          widget.onSubmit(_fullCode);
        }
      });
    }
  }

  void _onKey(int index, KeyEvent event) {
    if (event is KeyDownEvent &&
        event.logicalKey == LogicalKeyboardKey.backspace &&
        _controllers[index].text.isEmpty &&
        index > 0) {
      _controllers[index - 1].clear();
      _focusNodes[index - 1].requestFocus();
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          // ── GIF do Quico (largura total) ─────────────
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: Image.asset(
              'assets/images/olhando.gif',
              width: double.infinity,
              height: 220,
              fit: BoxFit.contain,
            ),
          ),
          const SizedBox(height: 16),

          // ── Pergunta ─────────────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.15),
              ),
            ),
            child: Text(
              widget.riddleText,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 16,
                height: 1.5,
                color: AppColors.ivory,
              ),
            ),
          ),

          const SizedBox(height: 28),

          // ── Caixas de dígitos ────────────────────────
          LayoutBuilder(
            builder: (context, constraints) {
              // Compute box width to fit within available space
              final maxWidth = constraints.maxWidth;
              final maxBoxWidth = 44.0;
              final minBoxWidth = 34.0;
              final spacing = _numDigits <= 6 ? 8.0 : 6.0;
              final idealTotal = _numDigits * maxBoxWidth + (_numDigits - 1) * spacing;
              final boxWidth = idealTotal > maxWidth
                  ? ((maxWidth - (_numDigits - 1) * spacing) / _numDigits).clamp(minBoxWidth, maxBoxWidth)
                  : maxBoxWidth;
              final boxHeight = (boxWidth * 54 / 44).clamp(44.0, 54.0);
              final fontSize = boxWidth < 38 ? 18.0 : 22.0;

              return Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(_numDigits, (index) {
                  return Padding(
                    padding: EdgeInsets.symmetric(horizontal: spacing / 2),
                    child: KeyboardListener(
                      focusNode: FocusNode(),
                      onKeyEvent: (event) => _onKey(index, event),
                      child: SizedBox(
                        width: boxWidth,
                        height: boxHeight,
                        child: TextFormField(
                          controller: _controllers[index],
                          focusNode: _focusNodes[index],
                          keyboardType: TextInputType.number,
                          textAlign: TextAlign.center,
                          maxLength: 1,
                          style: TextStyle(
                            fontSize: fontSize,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ivory,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                          ],
                          decoration: InputDecoration(
                            counterText: '',
                            filled: true,
                            fillColor: AppColors.navyDark,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                              borderSide: BorderSide(
                                color: AppColors.gold.withValues(alpha: 0.3),
                              ),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                              borderSide: BorderSide(
                                color: AppColors.gold.withValues(alpha: 0.3),
                              ),
                            ),
                            focusedBorder: const OutlineInputBorder(
                              borderRadius:
                                  BorderRadius.all(Radius.circular(10)),
                              borderSide:
                                  BorderSide(color: AppColors.gold, width: 2),
                            ),
                            contentPadding: EdgeInsets.zero,
                          ),
                          onChanged: (v) => _onDigitChanged(index, v),
                          onTap: () => _controllers[index].selection =
                              TextSelection(
                            baseOffset: 0,
                            extentOffset:
                                _controllers[index].text.length,
                          ),
                        ),
                      ),
                    ),
                  );
                }),
              );
            },
          ),

          const SizedBox(height: 8),
          Text(
            'Digite o código de $_numDigits dígito${_numDigits > 1 ? 's' : ''}',
            style: TextStyle(
              fontSize: 12,
              color: AppColors.ivoryMuted.withValues(alpha: 0.6),
            ),
          ),

          const SizedBox(height: 24),

          // ── Botão Enviar ─────────────────────────────
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: _isComplete
                  ? () => widget.onSubmit(_fullCode)
                  : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.navyDark,
                disabledBackgroundColor:
                    AppColors.ivoryMuted.withValues(alpha: 0.3),
                disabledForegroundColor:
                    AppColors.navyDark.withValues(alpha: 0.4),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'Enviar Resposta',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  _FinalAnswerInput — Input de texto livre para desafio final
// ═══════════════════════════════════════════════════════════════
class _FinalAnswerInput extends StatefulWidget {
  final String clue;
  final int correctPoints;
  final int wrongPenalty;
  final ValueChanged<String> onSubmit;

  const _FinalAnswerInput({
    required this.clue,
    this.correctPoints = 100,
    this.wrongPenalty = 20,
    required this.onSubmit,
  });

  @override
  State<_FinalAnswerInput> createState() => _FinalAnswerInputState();
}

class _FinalAnswerInputState extends State<_FinalAnswerInput> {
  final _controller = TextEditingController();
  final _focusNode = FocusNode();

  @override
  void dispose() {
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.emoji_events_outlined,
                color: AppColors.gold, size: 36),
          ),
          const SizedBox(height: 16),
          const Text(
            'DESAFIO FINAL',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              letterSpacing: 3,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),

          if (widget.clue.isNotEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.navyMedium,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.15),
                ),
              ),
              child: Text(
                widget.clue,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 15,
                  height: 1.5,
                  color: AppColors.ivory,
                ),
              ),
            ),

          // ── Aviso de pontos ────────────────────────────
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.2),
              ),
            ),
            child: Row(
              children: [
                Icon(Icons.info_outline,
                    color: AppColors.gold.withValues(alpha: 0.8), size: 18),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Acertar vale +${widget.correctPoints} pontos. '
                    'Errar NÃO tira pontos — pode tentar de novo!',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.gold.withValues(alpha: 0.9),
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // ── Campo de senha ──────────────────────────
          TextFormField(
            controller: _controller,
            focusNode: _focusNode,
            style: const TextStyle(
              fontSize: 18,
              color: AppColors.ivory,
              letterSpacing: 2,
            ),
            maxLength: 64,
            decoration: InputDecoration(
              labelText: 'Senha / Resposta',
              prefixIcon: const Icon(Icons.key),
              counterText: '',
              filled: true,
              fillColor: AppColors.navyMedium,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(
                  color: AppColors.gold.withValues(alpha: 0.3),
                ),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(
                  color: AppColors.gold.withValues(alpha: 0.3),
                ),
              ),
              focusedBorder: const OutlineInputBorder(
                borderRadius: BorderRadius.all(Radius.circular(12)),
                borderSide: BorderSide(color: AppColors.gold, width: 2),
              ),
            ),
          ),

          const SizedBox(height: 20),

          // ── Botão Responder (sempre habilitado, valida ao tocar) ──
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                final answer = _controller.text.trim();
                if (answer.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: const Text('Digite a resposta antes de enviar.'),
                      backgroundColor:
                          Colors.redAccent.withValues(alpha: 0.85),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                  return;
                }
                widget.onSubmit(answer);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.navyDark,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: const Text(
                'Responder',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  _LeaderboardTile
// ═══════════════════════════════════════════════════════════════
class _LeaderboardTile extends StatelessWidget {
  final int rank;
  final String teamName;
  final int points;
  final String status;
  final bool isMe;

  /// Cor da equipe (preta = preto, laranja = laranja). O texto vai em branco.
  final Color teamColor;

  const _LeaderboardTile({
    required this.rank,
    required this.teamName,
    required this.points,
    required this.status,
    required this.isMe,
    required this.teamColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        // Fundo na cor da equipe, com o texto todo em branco.
        color: teamColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          // "Minha equipe" ganha uma borda branca destacada.
          color: isMe ? Colors.white : Colors.white24,
          width: isMe ? 2.5 : 1,
        ),
      ),
      child: Row(
        children: [
          // Ranking
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: rank == 1 ? 0.3 : 0.16),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '$rank',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                  color: Colors.white,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  teamName,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
                Text(
                  status,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.white.withValues(alpha: 0.8),
                  ),
                ),
              ],
            ),
          ),
          // "VOCÊ" quando é a própria equipe
          if (isMe) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              margin: const EdgeInsets.only(right: 10),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.25),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Text(
                'VOCÊ',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.5,
                  color: Colors.white,
                ),
              ),
            ),
          ],
          Text(
            '$points pts',
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}
