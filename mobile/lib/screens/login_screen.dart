import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import 'admin_screen.dart';
import 'story_screen.dart';
import 'team_home_screen.dart';

/// Tela unificada de login — auto-detecção admin/equipe pelo usuário/senha.
///
/// Fluxo:
/// 1. Tenta adminLogin(username, password).
/// 2. Se sucesso → AdminScreen.
/// 3. Se falha → tenta teamLogin(username, password, deviceId):
///    - Sucesso → StoryScreen ou TeamHomeScreen.
///    - 409 → mensagem "Outro membro...".
///    - 401 → "Usuário ou senha inválidos."
/// 4. Se ambos falharem → "Usuário ou senha inválidos."
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usuarioController = TextEditingController();
  final _senhaController = TextEditingController();
  final _apiService = ApiService();
  bool _senhaVisivel = false;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _usuarioController.dispose();
    _senhaController.dispose();
    super.dispose();
  }

  Future<void> _onEntrar() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final username = _usuarioController.text.trim();
    final password = _senhaController.text;

    // ── 1. Tentar login admin ───────────────────────────
    try {
      await _apiService.adminLogin(username, password);

      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const AdminScreen()),
      );
      return;
    } on ApiException catch (_) {
      // Admin falhou — tentar equipe abaixo
    } catch (_) {
      // Erro de conexão no admin — continuar para equipe
    }

    // ── 2. Tentar login equipe ──────────────────────────
    try {
      final teamData = await _apiService.teamLogin(username, password);

      if (!mounted) return;

      // Verificar se a história já foi mostrada
      final deviceService = DeviceService();
      final storyShown = await deviceService.isStoryShown();

      if (!mounted) return;

      if (!storyShown) {
        // Buscar história e mostrar antes da home
        String story = '';
        try {
          story = await _apiService.getStory();
        } catch (_) {
          // Se falhar, tenta pegar do state
        }

        if (!mounted) return;

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => StoryScreen(
              story: story,
              teamData: teamData,
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
      return;
    } on ApiException catch (e) {
      if (!mounted) return;

      // Verificar se é erro 409 (outro membro logado)
      final msg = e.message.toLowerCase();
      if (msg.contains('outro membro') || msg.contains('já está logado')) {
        setState(() {
          _errorMessage = e.message;
          _isLoading = false;
        });
        return;
      }

      // Credenciais erradas no team → ambos falharam
      setState(() {
        _errorMessage = 'Usuário ou senha inválidos.';
        _isLoading = false;
      });
      return;
    } catch (_) {
      // Erro de conexão
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Erro de conexão. Verifique sua internet.';
        _isLoading = false;
      });
      return;
    }
  }

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── 1. Imagem de fundo ───────────────────────────
          Positioned.fill(
            child: Image.asset(
              'assets/images/login_bg.png',
              fit: BoxFit.cover,
              alignment: Alignment.topCenter,
            ),
          ),

          // ── 2. Overlay gradiente ─────────────────────────
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.transparent,
                    AppColors.navyDark.withValues(alpha: 0.3),
                    AppColors.navyDark.withValues(alpha: 0.9),
                  ],
                  stops: const [0.0, 0.4, 0.7],
                ),
              ),
            ),
          ),

          // ── 3. Formulário no terço inferior ──────────────
          Positioned.fill(
            child: SafeArea(
              child: Column(
                children: [
                  const Spacer(flex: 6),
                  Flexible(
                    flex: 4,
                    child: _LoginCard(
                      formKey: _formKey,
                      usuarioController: _usuarioController,
                      senhaController: _senhaController,
                      senhaVisivel: _senhaVisivel,
                      isLoading: _isLoading,
                      errorMessage: _errorMessage,
                      onToggleSenha: () {
                        setState(() {
                          _senhaVisivel = !_senhaVisivel;
                        });
                      },
                      onEntrar: _onEntrar,
                    ),
                  ),
                  SizedBox(height: padding.bottom + 16),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LoginCard extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController usuarioController;
  final TextEditingController senhaController;
  final bool senhaVisivel;
  final bool isLoading;
  final String? errorMessage;
  final VoidCallback onToggleSenha;
  final VoidCallback onEntrar;

  const _LoginCard({
    required this.formKey,
    required this.usuarioController,
    required this.senhaController,
    required this.senhaVisivel,
    required this.isLoading,
    required this.errorMessage,
    required this.onToggleSenha,
    required this.onEntrar,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
        decoration: BoxDecoration(
          color: AppColors.cardDark,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: AppColors.gold.withValues(alpha: 0.2),
            width: 1,
          ),
        ),
        child: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // ── Título ──────────────────────────────────
              const Text(
                'Entrar no Jogo',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ivory,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Use suas credenciais para acessar',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.ivoryMuted,
                ),
              ),
              const SizedBox(height: 24),

              // ── Mensagem de erro ────────────────────────
              if (errorMessage != null) ...[
                Container(
                  width: double.infinity,
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
                        Icons.error_outline,
                        color: Colors.redAccent,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          errorMessage!,
                          style: const TextStyle(
                            color: Colors.redAccent,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // ── Campo Usuário ───────────────────────────
              TextFormField(
                controller: usuarioController,
                style: const TextStyle(color: AppColors.ivory),
                keyboardType: TextInputType.text,
                textInputAction: TextInputAction.next,
                enabled: !isLoading,
                decoration: const InputDecoration(
                  labelText: 'Usuário',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Usuário é obrigatório';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              // ── Campo Senha ─────────────────────────────
              TextFormField(
                controller: senhaController,
                style: const TextStyle(color: AppColors.ivory),
                obscureText: !senhaVisivel,
                keyboardType: TextInputType.visiblePassword,
                textInputAction: TextInputAction.done,
                enabled: !isLoading,
                onFieldSubmitted: (_) => onEntrar(),
                decoration: InputDecoration(
                  labelText: 'Senha',
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    icon: Icon(
                      senhaVisivel
                          ? Icons.visibility_off_outlined
                          : Icons.visibility_outlined,
                      color: AppColors.ivoryMuted,
                    ),
                    onPressed: onToggleSenha,
                  ),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Senha é obrigatória';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),

              // ── Botão Entrar ────────────────────────────
              SizedBox(
                width: double.infinity,
                height: 52,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: isLoading
                          ? [AppColors.ivoryMuted, AppColors.ivoryMuted]
                          : [AppColors.gold, AppColors.goldDark],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: (isLoading
                                ? AppColors.ivoryMuted
                                : AppColors.gold)
                            .withValues(alpha: 0.3),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: ElevatedButton(
                    onPressed: isLoading ? null : onEntrar,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      shadowColor: Colors.transparent,
                      foregroundColor: AppColors.navyDark,
                      disabledBackgroundColor: Colors.transparent,
                      disabledForegroundColor:
                          AppColors.navyDark.withValues(alpha: 0.5),
                      textStyle: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: isLoading
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.5,
                              color: AppColors.navyDark,
                            ),
                          )
                        : const Text('Entrar'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
