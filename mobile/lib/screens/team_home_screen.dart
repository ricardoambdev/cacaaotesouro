import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../theme.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
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
  int _currentTab = 0;
  GameState? _gameState;
  bool _isLoading = true;
  String? _error;

  // ── Estado do fluxo do tesouro ──────────────────────────
  TreasureFlowState _flowState = TreasureFlowState.viewingClue;
  CheckinResult? _checkinResult;
  AnswerResult? _answerResult;

  @override
  void initState() {
    super.initState();
    _loadState();
  }

  Future<void> _loadState() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final state = await _apiService.teamState();
      if (!mounted) return;
      setState(() {
        _gameState = state;
        _isLoading = false;
        _flowState = TreasureFlowState.viewingClue;
        _checkinResult = null;
        _answerResult = null;
      });
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
        final result = await _apiService.uploadSelfie(
          treasureId: treasure.id,
          filePath: image.path,
        );

        if (!mounted) return;
        final points = result['points'] ?? 0;
        _showSnackBar('Selfie enviada! +$points pontos', isError: false);

        // Avançar direto para a charada
        setState(() => _flowState = TreasureFlowState.showingRiddle);
      } on ApiException catch (e) {
        if (!mounted) return;
        _showSnackBar(e.message, isError: true);
        // Mesmo com erro, continua para a charada
        setState(() => _flowState = TreasureFlowState.showingRiddle);
      } catch (_) {
        if (!mounted) return;
        _showSnackBar('Erro ao enviar selfie.', isError: true);
        setState(() => _flowState = TreasureFlowState.showingRiddle);
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
      setState(() {
        _answerResult = result;
        _flowState = TreasureFlowState.answerResult;
      });

      // Se correto e tem próximo tesouro, recarregar estado após 2s
      if (result.correct) {
        Future.delayed(const Duration(seconds: 2), () async {
          if (!mounted) return;
          await _loadState();
        });
      }
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
    switch (_flowState) {
      case TreasureFlowState.viewingClue:
        return _buildClueView();
      case TreasureFlowState.gettingLocation:
        return _buildLoadingState('Obtendo sua localização...');
      case TreasureFlowState.checkingIn:
        return _buildLoadingState('Verificando checkin...');
      case TreasureFlowState.checkinSuccess:
        return _buildCheckinSuccess();
      case TreasureFlowState.uploadingSelfie:
        return _buildLoadingState('Enviando selfie...');
      case TreasureFlowState.showingRiddle:
        return _buildRiddleView();
      case TreasureFlowState.submittingAnswer:
        return _buildLoadingState('Verificando resposta...');
      case TreasureFlowState.answerResult:
        return _buildAnswerResult();
      case TreasureFlowState.finalChallenge:
        return _buildFinalChallengeInput();
      case TreasureFlowState.finalResult:
        return _buildFinalResult();
    }
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
    final result = _checkinResult;
    if (result == null) return const SizedBox();

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // ── Checkin OK ───────────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: Colors.green.withValues(alpha: 0.3),
              ),
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.green.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.check_circle_outline,
                      color: Colors.greenAccent, size: 36),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Checkin Realizado!',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ivory,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  result.message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.ivoryMuted,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // ── Selfie obrigatória ──────────────────────────
          if (result.selfieRequired || result.selfieQuestion) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.navyMedium,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: Colors.orangeAccent.withValues(alpha: 0.4),
                ),
              ),
              child: Column(
                children: [
                  const Icon(Icons.camera_alt_outlined,
                      color: AppColors.gold, size: 28),
                  const SizedBox(height: 10),
                  const Text(
                    'Selfie obrigatória',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ivory,
                    ),
                  ),
                  const SizedBox(height: 6),
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
                      'Todos os integrantes devem aparecer na foto. '
                      'Se os alunos não saírem na foto, os pontos podem ser CANCELADOS.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        height: 1.4,
                        color: Colors.orangeAccent,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Ganhe +5 pontos',
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.gold.withValues(alpha: 0.8),
                    ),
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _takeSelfie,
                      icon: const Icon(Icons.camera_alt, size: 18),
                      label: const Text('Tirar selfie'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.gold,
                        foregroundColor: AppColors.navyDark,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ] else ...[
            // Sem selfie, ir direto para charada
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () =>
                    setState(() => _flowState = TreasureFlowState.showingRiddle),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.gold,
                  foregroundColor: AppColors.navyDark,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text(
                  'Ver Charada',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildRiddleView() {
    final result = _checkinResult;
    if (result == null) return const SizedBox();

    return _RiddleInput(
      riddleNumber: result.assignedRiddle,
      riddleText: result.riddle,
      onSubmit: _submitAnswer,
    );
  }

  Widget _buildAnswerResult() {
    final result = _answerResult;
    if (result == null) return const SizedBox();

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: result.correct
                    ? Colors.green.withValues(alpha: 0.15)
                    : Colors.redAccent.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(
                result.correct ? Icons.check : Icons.close,
                color:
                    result.correct ? Colors.greenAccent : Colors.redAccent,
                size: 48,
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
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.emoji_events_outlined,
                  color: AppColors.gold, size: 40),
            ),
            const SizedBox(height: 20),
            const Text(
              'DESAFIO FINAL',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                letterSpacing: 3,
                color: AppColors.gold,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              _gameState!.finalClue.isNotEmpty
                  ? _gameState!.finalClue
                  : 'Resolva o último mistério para vencer!',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 15,
                color: AppColors.ivory,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => setState(
                    () => _flowState = TreasureFlowState.finalChallenge),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.gold,
                  foregroundColor: AppColors.navyDark,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text(
                  'Iniciar Desafio',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFinalChallengeInput() {
    return _FinalAnswerInput(
      clue: _gameState?.finalClue ?? '',
      onSubmit: _submitFinalAnswer,
      onCancel: () =>
          setState(() => _flowState = TreasureFlowState.viewingClue),
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
  final int riddleNumber;
  final String riddleText;
  final ValueChanged<String> onSubmit;

  const _RiddleInput({
    required this.riddleNumber,
    required this.riddleText,
    required this.onSubmit,
  });

  @override
  State<_RiddleInput> createState() => _RiddleInputState();
}

class _RiddleInputState extends State<_RiddleInput> {
  static const int _numDigits = 6;
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
          // ── Título ───────────────────────────────────
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.help_outline,
                color: AppColors.gold, size: 32),
          ),
          const SizedBox(height: 16),
          Text(
            'CHARADA #${widget.riddleNumber}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              letterSpacing: 3,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),

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
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(_numDigits, (index) {
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: KeyboardListener(
                  focusNode: FocusNode(),
                  onKeyEvent: (event) => _onKey(index, event),
                  child: SizedBox(
                    width: 44,
                    height: 54,
                    child: TextFormField(
                      controller: _controllers[index],
                      focusNode: _focusNodes[index],
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      maxLength: 1,
                      style: const TextStyle(
                        fontSize: 22,
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
          ),

          const SizedBox(height: 8),
          Text(
            'Digite o código de $_numDigits dígitos',
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
  final ValueChanged<String> onSubmit;
  final VoidCallback onCancel;

  const _FinalAnswerInput({
    required this.clue,
    required this.onSubmit,
    required this.onCancel,
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

          const SizedBox(height: 24),

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

          // ── Botões ───────────────────────────────────
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: widget.onCancel,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.ivoryMuted,
                    side: BorderSide(
                      color: AppColors.ivoryMuted.withValues(alpha: 0.3),
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: const Text('Voltar'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton(
                  onPressed: _controller.text.trim().isNotEmpty
                      ? () =>
                          widget.onSubmit(_controller.text.trim())
                      : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.gold,
                    foregroundColor: AppColors.navyDark,
                    disabledBackgroundColor:
                        AppColors.ivoryMuted.withValues(alpha: 0.3),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: const Text(
                    'Enviar',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
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
