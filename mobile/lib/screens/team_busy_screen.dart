import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart'
    show HtmlWidget;
import '../theme.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import 'login_screen.dart';
import 'story_screen.dart';
import 'team_home_screen.dart';

/// Tela exibida quando outro aparelho já está logado com a mesma equipe (409).
///
/// Faz polling a cada 5 segundos tentando novamente o login. Quando a vaga
/// liberar, navega automaticamente para o jogo.
class TeamBusyScreen extends StatefulWidget {
  final Map<String, dynamic> teamData;
  final String story;
  final int storyVersion;
  final String username;
  final String password;

  const TeamBusyScreen({
    super.key,
    required this.teamData,
    required this.story,
    required this.storyVersion,
    required this.username,
    required this.password,
  });

  @override
  State<TeamBusyScreen> createState() => _TeamBusyScreenState();
}

class _TeamBusyScreenState extends State<TeamBusyScreen> {
  final _apiService = ApiService();
  Timer? _pollTimer;
  bool _isChecking = false;

  // Estado local — atualizado a cada 409 com dados frescos
  late int _teamPoints;
  late String _story;

  @override
  void initState() {
    super.initState();
    _teamPoints = widget.teamData['points'] as int? ?? 0;
    _story = widget.story;
    _startPolling();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _tryLogin(),
    );
    // Tentar imediatamente
    _tryLogin();
  }

  Future<void> _tryLogin() async {
    if (_isChecking) return;
    if (!mounted) return;

    setState(() => _isChecking = true);

    try {
      final teamData = await _apiService.teamLogin(
        widget.username,
        widget.password,
      );

      if (!mounted) return;

      // Sucesso! Cancelar timer e navegar
      _pollTimer?.cancel();

      final deviceService = DeviceService();
      final state = await _apiService.teamState();
      final storedVersion = await deviceService.getStoryVersion();

      if (!mounted) return;

      if (state.storyVersion > storedVersion) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => StoryScreen(
              story: state.story,
              teamData: teamData,
              storyVersion: state.storyVersion,
            ),
          ),
        );
      } else {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => TeamHomeScreen(teamData: teamData),
          ),
        );
      }
    } on TeamBusyException catch (e) {
      if (!mounted) return;
      // Continua na tela — atualiza dados com payload novo
      setState(() {
        _teamPoints = e.team['points'] as int? ?? _teamPoints;
        if (e.story.isNotEmpty) _story = e.story;
      });
    } catch (_) {
      // Falha de rede — silencioso, tenta de novo no próximo ciclo
    } finally {
      if (mounted) {
        setState(() => _isChecking = false);
      }
    }
  }

  void _onVerHistoria() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.navyDark,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) => Column(
          children: [
            // Handle
            Container(
              margin: const EdgeInsets.only(top: 12),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.ivoryMuted.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.auto_stories,
                        color: AppColors.gold, size: 22),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'A HISTÓRIA',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 3,
                        color: AppColors.gold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppColors.ivoryMuted),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: AppColors.navyMedium),
            // Story content
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: _story.trim().isNotEmpty
                    ? SingleChildScrollView(
                        controller: scrollController,
                        child: HtmlWidget(
                          _story,
                          textStyle: const TextStyle(
                            fontSize: 15,
                            height: 1.7,
                            color: AppColors.ivory,
                          ),
                        ),
                      )
                    : const Center(
                        child: Text(
                          'A história será revelada em breve...',
                          style: TextStyle(
                            fontSize: 15,
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

  Future<void> _onSair() async {
    _pollTimer?.cancel();
    await DeviceService().clearCredentials();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  Color _parseColor(String name) {
    return AppColors.teamColor(name);
  }

  @override
  Widget build(BuildContext context) {
    final teamName = widget.teamData['name'] as String? ?? 'Equipe';
    final teamColor = widget.teamData['color'] as String? ?? '';

    return Scaffold(
      backgroundColor: AppColors.navyDark,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Fundo com overlay ────────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/login_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.topCenter,
            ),
          ),
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    AppColors.navyDark.withValues(alpha: 0.5),
                    AppColors.navyDark.withValues(alpha: 0.8),
                    AppColors.navyDark,
                  ],
                  stops: const [0.0, 0.3, 0.6],
                ),
              ),
            ),
          ),

          // ── Conteúdo ─────────────────────────────────────
          Positioned.fill(
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 28),
                child: Column(
                  children: [
                    const Spacer(flex: 2),

                    // ── Ícone de aviso ──────────────────────
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: Colors.orangeAccent.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.orangeAccent.withValues(alpha: 0.3),
                        ),
                      ),
                      child: const Icon(
                        Icons.phonelink_lock,
                        color: Colors.orangeAccent,
                        size: 42,
                      ),
                    ),
                    const SizedBox(height: 24),

                    // ── Título ──────────────────────────────
                    const Text(
                      'Outra conta está logada',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ivory,
                      ),
                    ),
                    const SizedBox(height: 10),

                    // ── Subtítulo ───────────────────────────
                    Text(
                      'Esta equipe já está conectada em outro aparelho.\nAguardando liberar...',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 14,
                        height: 1.5,
                        color: AppColors.ivoryMuted,
                      ),
                    ),

                    const SizedBox(height: 32),

                    // ── Card da equipe ──────────────────────
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: AppColors.navyMedium,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: _parseColor(teamColor).withValues(alpha: 0.3),
                        ),
                      ),
                      child: Column(
                        children: [
                          // Barra de cor da equipe
                          Container(
                            width: 40,
                            height: 4,
                            decoration: BoxDecoration(
                              color: _parseColor(teamColor),
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            teamName,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w700,
                              color: AppColors.ivory,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 8),
                            decoration: BoxDecoration(
                              color: AppColors.gold.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(
                                color: AppColors.gold.withValues(alpha: 0.3),
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.star,
                                    color: AppColors.gold, size: 18),
                                const SizedBox(width: 8),
                                Text(
                                  '$_teamPoints pontos',
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 32),

                    // ── Indicador de polling ────────────────
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.ivoryMuted.withValues(alpha: 0.5),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          'Verificando a cada 5 segundos...',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.ivoryMuted.withValues(alpha: 0.7),
                          ),
                        ),
                      ],
                    ),

                    const Spacer(flex: 2),

                    // ── Botões ──────────────────────────────
                    // Ver História
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: OutlinedButton.icon(
                        onPressed: _onVerHistoria,
                        icon: const Icon(Icons.auto_stories, size: 20),
                        label: const Text(
                          'Ver História',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.gold,
                          side: BorderSide(
                              color: AppColors.gold.withValues(alpha: 0.4)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Sair
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: Colors.redAccent.withValues(alpha: 0.3),
                          ),
                        ),
                        child: ElevatedButton.icon(
                          onPressed: _onSair,
                          icon: const Icon(Icons.logout, size: 20),
                          label: const Text(
                            'Sair',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.transparent,
                            shadowColor: Colors.transparent,
                            foregroundColor: Colors.redAccent,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                        ),
                      ),
                    ),

                    SizedBox(height: MediaQuery.paddingOf(context).bottom + 16),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
