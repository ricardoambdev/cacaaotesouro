import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/device_service.dart';
import 'team_home_screen.dart';

/// Tela de história — exibida na primeira entrada do jogo.
class StoryScreen extends StatefulWidget {
  final String story;
  final Map<String, dynamic> teamData;
  final int storyVersion;

  const StoryScreen({
    super.key,
    required this.story,
    required this.teamData,
    this.storyVersion = 0,
  });

  @override
  State<StoryScreen> createState() => _StoryScreenState();
}

class _StoryScreenState extends State<StoryScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 1200),
      vsync: this,
    );
    _fadeAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeInOut,
    );
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _startGame() async {
    // Salvar versão da história exibida
    await DeviceService().setStoryVersion(widget.storyVersion);

    if (!mounted) return;

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => TeamHomeScreen(teamData: widget.teamData),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    final hasStory = widget.story.trim().isNotEmpty;

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
                    AppColors.navyDark.withValues(alpha: 0.6),
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
              child: FadeTransition(
                opacity: _fadeAnimation,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    children: [
                      const SizedBox(height: 40),

                      // Ícone decorativo
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: AppColors.gold.withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppColors.gold.withValues(alpha: 0.3),
                          ),
                        ),
                        child: const Icon(
                          Icons.auto_stories,
                          color: AppColors.gold,
                          size: 36,
                        ),
                      ),
                      const SizedBox(height: 20),

                      Text(
                        'A HISTÓRIA',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 4,
                          color: AppColors.gold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Tudo começa com um mistério...',
                        style: TextStyle(
                          fontSize: 13,
                          color: AppColors.ivoryMuted,
                        ),
                      ),

                      const SizedBox(height: 24),

                      // ── Texto da história ────────────────
                      Expanded(
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            color: AppColors.cardDark,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: AppColors.gold.withValues(alpha: 0.15),
                            ),
                          ),
                          child: hasStory
                              ? SingleChildScrollView(
                                  child: Text(
                                    widget.story,
                                    style: const TextStyle(
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

                      const SizedBox(height: 24),

                      // ── Botão Começar ───────────────────
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
                                blurRadius: 16,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: ElevatedButton(
                            onPressed: _startGame,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.transparent,
                              shadowColor: Colors.transparent,
                              foregroundColor: AppColors.navyDark,
                              textStyle: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.5,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            child: const Text('Começar a Caça'),
                          ),
                        ),
                      ),

                      SizedBox(height: padding.bottom + 16),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
