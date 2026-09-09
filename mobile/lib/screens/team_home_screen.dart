import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import '../services/sound_service.dart';
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

  // ── Timer de envio de localização ──────────────────────
  Timer? _locationTimer;

  // ── Mensagens não lidas ────────────────────────────────
  List<TeamMessage> _unreadMessages = [];

  // ── Novas mensagens (para notificação com som) ─────────
  List<TeamMessage> _newMessages = [];
  bool _showNewMessageBanner = false;

  @override
  void initState() {
    super.initState();
    _loadState();
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    _soundService.dispose();
    super.dispose();
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

      // ── Detectar mensagens NOVAS (id > última vista) ──────
      final lastId = await _deviceService.getLastMessageId();
      final newMsgs = state.messages.where((m) => m.id > lastId).toList();
      if (newMsgs.isNotEmpty) {
        // Tocar som de notificação
        _soundService.playNotification();
        // Atualizar banner com a mensagem mais recente
        final newest = newMsgs.reduce(
          (a, b) => a.id > b.id ? a : b,
        );
        setState(() {
          _newMessages = newMsgs;
          _showNewMessageBanner = true;
        });
        // Salvar o maior ID visto
        await _deviceService.setLastMessageId(newest.id);
        // Auto-dismiss do banner após 6 segundos
        Future.delayed(const Duration(seconds: 6), () {
          if (mounted) {
            setState(() => _showNewMessageBanner = false);
          }
        });
      }

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

    // 3. Obter posição
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

    // 4. Abrir scanner QR
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
    final picker = ImagePicker();
    try {
      final XFile? image = await picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 80,
      );

      if (image == null || !mounted) return;

      // Enviar selfie
      setState(() => _flowState = TreasureFlowState.uploadingSelfie);
      final treasure = _gameState?.currentTreasure;
      if (treasure == null) return;

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
      final result = await _apiService.answer(
        treasureId: treasure.id,
        answer: answer,
      );

      if (!mounted) return;

      // Tocar som de acordo com o resultado
      if (result.correct) {
        _soundService.playAcerto();
      } else {
        _soundService.playChoro();
      }

      setState(() {
        _answerResult = result;
        _flowState = TreasureFlowState.answerResult;
      });
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

      // Tocar som de acordo com o resultado
      if (result.correct) {
        _soundService.playAcerto();
      } else {
        _soundService.playChoro();
      }

      setState(() {
        _answerResult = result;
        _flowState = TreasureFlowState.finalResult;
      });
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

  // ════════════════════════════════════════════════════════════
  //  BUILD
  // ════════════════════════════════════════════════════════════

  @override
  Widget build(BuildContext context) {
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
            Text(
              (widget.teamData['name'] as String?)?.isNotEmpty == true
                  ? (widget.teamData['name'] as String)
                  : 'Equipe',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 17,
              ),
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
              : _currentTab == 0
                  ? _buildTreasureTab()
                  : _currentTab == 1
                      ? _buildStoryContent()
                      : _currentTab == 2
                          ? _buildLeaderboardContent()
                          : const SizedBox(),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentTab,
        onDestinationSelected: (index) {
          if (index == 3) {
            _logout();
          } else {
            setState(() => _currentTab = index);
          }
        },
        backgroundColor: AppColors.navyDark,
        indicatorColor: AppColors.gold.withValues(alpha: 0.15),
        height: 65,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.account_balance_wallet_outlined,
                color: AppColors.ivoryMuted),
            selectedIcon:
                Icon(Icons.account_balance_wallet, color: AppColors.gold),
            label: 'Tesouro',
          ),
          NavigationDestination(
            icon: Icon(Icons.info_outline, color: AppColors.ivoryMuted),
            selectedIcon: Icon(Icons.info, color: AppColors.gold),
            label: 'História',
          ),
          NavigationDestination(
            icon: Icon(Icons.leaderboard_outlined,
                color: AppColors.ivoryMuted),
            selectedIcon:
                Icon(Icons.leaderboard, color: AppColors.gold),
            label: 'Pontos',
          ),
          NavigationDestination(
            icon: Icon(Icons.logout, color: AppColors.ivoryMuted),
            label: 'Sair',
          ),
        ],
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

  Widget _buildTreasureTab() {
    if (_gameState == null) return const SizedBox();

    // Sem tesouro atual e não é final → aguardando
    if (_gameState!.currentTreasure == null && !_gameState!.finalAvailable) {
      return _buildWaitingState();
    }

    // Final disponível → desafio final
    if (_gameState!.currentTreasure == null && _gameState!.finalAvailable &&
        _flowState != TreasureFlowState.finalResult) {
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
        // Banner de nova mensagem (destacado + som)
        if (_showNewMessageBanner && _newMessages.isNotEmpty)
          _buildNewMessageBanner(),
        // Banner de mensagens existentes
        ..._unreadMessages.map((msg) => _buildMessageBanner(msg)),
        // Conteúdo principal
        Expanded(child: content),
      ],
    );
  }

  Widget _buildMessageBanner(TeamMessage msg) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: AppColors.gold.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(6),
            ),
            child: const Icon(
              Icons.notifications_active,
              color: AppColors.gold,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'MENSAGEM DO ADMIN',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.5,
                    color: AppColors.gold,
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

  /// Banner destacado para mensagens NOVAS (com som + toque para dispensar).
  Widget _buildNewMessageBanner() {
    return GestureDetector(
      onTap: () {
        setState(() => _showNewMessageBanner = false);
      },
      child: Container(
        width: double.infinity,
        margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              AppColors.gold.withValues(alpha: 0.2),
              AppColors.gold.withValues(alpha: 0.08),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: AppColors.gold.withValues(alpha: 0.6),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.gold.withValues(alpha: 0.15),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.25),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(
                Icons.notifications_active,
                color: AppColors.gold,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'NOVA MENSAGEM',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1.5,
                      color: AppColors.gold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  // Mostra a mensagem mais recente
                  Text(
                    _newMessages.last.message,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      height: 1.4,
                      color: AppColors.ivory,
                    ),
                  ),
                  if (_newMessages.length > 1) ...[
                    const SizedBox(height: 4),
                    Text(
                      '+${_newMessages.length - 1} outra(s) mensagem(ns)',
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.gold.withValues(alpha: 0.7),
                      ),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Text(
                    'Toque para dispensar',
                    style: TextStyle(
                      fontSize: 10,
                      color: AppColors.ivoryMuted.withValues(alpha: 0.5),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
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
            const SizedBox(height: 8),
            Text(
              result.message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 15,
                color: AppColors.ivory,
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.navyMedium,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '${result.points >= 0 ? '+' : ''}${result.points} pontos',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: result.points >= 0
                      ? AppColors.gold
                      : Colors.redAccent,
                ),
              ),
            ),
            if (!result.correct) ...[
              const SizedBox(height: 16),
              Text(
                'Tentativa ${result.attempts}',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.ivoryMuted,
                ),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () =>
                    setState(() => _flowState = TreasureFlowState.showingRiddle),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.gold,
                  foregroundColor: AppColors.navyDark,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Tentar Novamente'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildFinalChallenge() {
    // O desafio abre automaticamente quando finalAvailable
    return _FinalAnswerInput(
      clue: _gameState?.finalClue ?? '',
      correctPoints: _gameState?.finalCorrectPoints ?? 100,
      wrongPenalty: _gameState?.finalWrongPenalty ?? 20,
      onSubmit: _submitFinalAnswer,
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
                        child: Text(
                          story,
                          style: const TextStyle(
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
                    'Errar custa -${widget.wrongPenalty} pontos.',
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

  const _LeaderboardTile({
    required this.rank,
    required this.teamName,
    required this.points,
    required this.status,
    required this.isMe,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: isMe
            ? AppColors.gold.withValues(alpha: 0.1)
            : AppColors.navyMedium,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isMe
              ? AppColors.gold.withValues(alpha: 0.4)
              : AppColors.ivoryMuted.withValues(alpha: 0.1),
        ),
      ),
      child: Row(
        children: [
          // Ranking
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: rank == 1
                  ? AppColors.gold.withValues(alpha: 0.2)
                  : AppColors.navyDark,
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '$rank',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                  color: rank == 1 ? AppColors.gold : AppColors.ivoryMuted,
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
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: isMe ? AppColors.gold : AppColors.ivory,
                  ),
                ),
                Text(
                  status,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.ivoryMuted,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '$points pts',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: isMe ? AppColors.gold : AppColors.ivory,
            ),
          ),
        ],
      ),
    );
  }
}
