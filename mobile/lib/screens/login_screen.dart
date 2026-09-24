import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import '../services/name_blocklist.dart';
import '../widgets/device_name_dialog.dart';
import 'admin_screen.dart';
import 'story_screen.dart';
import 'team_busy_screen.dart';
import 'team_home_screen.dart';

/// Tela unificada de login — auto-detecção admin/equipe pelo usuário/senha.
///
/// Fluxo:
/// 1. Tenta adminLogin(username, password).
/// 2. Se sucesso → AdminScreen.
/// 3. Se falha → tenta teamLogin(username, password, deviceId):
///    - Sucesso → StoryScreen ou TeamHomeScreen.
///    - 409 → TeamBusyScreen (outra conta logada).
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
  bool _autoLogin = true;

  @override
  void initState() {
    super.initState();
    _loadSavedCredentials();
  }

  Future<void> _loadSavedCredentials() async {
    final deviceService = DeviceService();
    final credentials = await deviceService.getSavedCredentials();

    if (!mounted) return;

    // Auto-login é o PADRÃO do app (o checkbox foi removido).
    setState(() {
      _autoLogin = true;
    });

    if (credentials != null) {
      _usuarioController.text = credentials['username']!;
      _senhaController.text = credentials['password']!;
      // Pequeno delay para garantir que a tela foi renderizada
      Future.delayed(const Duration(milliseconds: 300), () {
        if (mounted && !_isLoading) {
          _onEntrar();
        }
      });
    }
  }

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
    final deviceService = DeviceService();

    // ── 1. Tentar login admin ───────────────────────────
    try {
      await _apiService.adminLogin(username, password);

      if (!mounted) return;

      // Login com sucesso — salva sempre (auto-login é o padrão).
      await deviceService.saveCredentials(username, password);
      await deviceService.setAutoLogin(true);

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
      // Reenvia o nome já salvo neste aparelho (se houver).
      final savedName = await deviceService.getDeviceName();
      final teamData = await _apiService.teamLogin(username, password,
          deviceName: savedName);

      // Lista negra de nomes (bloqueia palavrões/xingamentos na hora).
      NameBlocklist.setWords(teamData['name_blacklist'] as List<dynamic>?);
      NameBlocklist.setWhitelist(teamData['name_whitelist'] as List<dynamic>?);

      if (!mounted) return;

      // Login com sucesso — SEMPRE salva as credenciais: o padrão do app é
      // entrar automaticamente. Só troca de usuário quem tocar em "Sair".
      await deviceService.saveCredentials(username, password);
      await deviceService.setAutoLogin(true);

      // ══════════════════════════════════════════════════════
      //  PRIMEIRO ACESSO NESTE APARELHO
      //  1) mensagem de boas-vindas
      //  2) história aberta (com o modal do nome por cima)
      // ══════════════════════════════════════════════════════
      final welcomeSeen = await deviceService.getWelcomeSeen();

      if (!welcomeSeen) {
        if (!mounted) return;

        await showDialog<void>(
          context: context,
          barrierDismissible: false,
          builder: (ctx) => AlertDialog(
            backgroundColor: AppColors.navyMedium,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            title: const Row(
              children: [
                Icon(Icons.celebration, color: AppColors.gold, size: 26),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Boas-vindas!',
                    style: TextStyle(
                      color: AppColors.gold,
                      fontWeight: FontWeight.w900,
                      fontSize: 20,
                    ),
                  ),
                ),
              ],
            ),
            content: const Text(
              'Bem-vindo à Caça ao Tesouro da Gincana 2026 do Colégio Helena!',
              style: TextStyle(
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
                  'OK',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        );

        await deviceService.setWelcomeSeen();

        if (!mounted) return;

        // Abre a HISTÓRIA (é nela que o modal do nome aparece).
        final firstState = await _apiService.teamState();
        final savedDeviceName = await deviceService.getDeviceName();

        if (!mounted) return;

        final serverName = (teamData['device_name'] as String?) ?? '';

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => StoryScreen(
              story: firstState.story,
              teamData: teamData,
              storyVersion: firstState.storyVersion,
              askDeviceName:
                  serverName.isEmpty && savedDeviceName.trim().isEmpty,
            ),
          ),
        );
        return;
      }

      // ── NOME DO APARELHO ────────────────────────────────
      // guarda no dispositivo: nos próximos logins não pergunta de novo.
      var deviceName = (teamData['device_name'] as String?) ?? '';

      if (deviceName.isEmpty) {
        deviceName = await deviceService.getDeviceName();

        if (deviceName.isEmpty) {
          if (!mounted) return;

          final typed = await showDeviceNameDialog(context, mandatory: true);
          deviceName = (typed ?? '').trim();
        }
      }

      if (deviceName.isNotEmpty) {
        await deviceService.setDeviceName(deviceName);

        try {
          await _apiService.teamSetName(deviceName);
        } catch (_) {
          // Sem rede: o nome fica salvo localmente e é reenviado no próximo
          // login (vai junto do /api/team/login).
        }

        teamData['device_name'] = deviceName;
      }

      if (!mounted) return;

      // Obter estado do jogo (inclui storyVersion e story)
      final state = await _apiService.teamState();
      final storedVersion = await deviceService.getStoryVersion();

      if (!mounted) return;

      if (state.storyVersion > storedVersion) {
        // Versão nova → mostrar história antes da home
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
        // Mesma versão → ir direto para home
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => TeamHomeScreen(teamData: teamData),
          ),
        );
      }
      return;
    } on TeamBusyException catch (e) {
      if (!mounted) return;

      // Salva as credenciais (a tela de espera usa para tentar de novo).
      await deviceService.saveCredentials(username, password);
      await deviceService.setAutoLogin(true);

      // Navegar para tela de espera — NÃO mostrar erro
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => TeamBusyScreen(
            teamData: e.team,
            story: e.story,
            storyVersion: e.storyVersion,
            username: username,
            password: password,
          ),
        ),
      );
      return;
    } on ApiException catch (_) {
      if (!mounted) return;

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
                    AppColors.navyDark.withValues(alpha: 0.4),
                    AppColors.navyDark.withValues(alpha: 0.92),
                    AppColors.navyDark,
                  ],
                  stops: const [0.0, 0.35, 0.65, 0.85],
                ),
              ),
            ),
          ),

          // ── 3. Formulário no terço inferior ──────────────
          Positioned.fill(
            child: SafeArea(
              child: Column(
                children: [
                  const Spacer(flex: 3),
                  _LoginCard(
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
                  SizedBox(height: padding.bottom + 12),
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
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.bottomCenter,
          end: Alignment.topCenter,
          colors: [
            AppColors.navyDark,
            AppColors.navyDark.withValues(alpha: 0.85),
            Colors.transparent,
          ],
          stops: const [0.0, 0.6, 1.0],
        ),
      ),
      child: Form(
        key: formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ── Título compacto ──────────────────────────
            const Text(
              'Entrar no Jogo',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.ivory,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Use suas credenciais para acessar',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.ivoryMuted,
              ),
            ),
            const SizedBox(height: 16),

            // ── Mensagem de erro ────────────────────────
            if (errorMessage != null) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.redAccent.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: Colors.redAccent.withValues(alpha: 0.4),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.error_outline,
                      color: Colors.redAccent,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        errorMessage!,
                        style: const TextStyle(
                          color: Colors.redAccent,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
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
                contentPadding:
                    EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Usuário é obrigatório';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),

            // ── Campo Senha + Botão Entrar ──────────────
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Campo senha (expandido)
                Expanded(
                  child: TextFormField(
                    controller: senhaController,
                    style: const TextStyle(
                        color: AppColors.ivory, fontSize: 16),
                    obscureText: !senhaVisivel,
                    keyboardType: TextInputType.visiblePassword,
                    textInputAction: TextInputAction.done,
                    enabled: !isLoading,
                    onFieldSubmitted: (_) => onEntrar(),
                    decoration: InputDecoration(
                      labelText: 'Senha',
                      prefixIcon: const Icon(Icons.lock_outline, size: 22),
                      suffixIcon: IconButton(
                        iconSize: 22,
                        icon: Icon(
                          senhaVisivel
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                          color: AppColors.ivoryMuted,
                        ),
                        onPressed: onToggleSenha,
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 12),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Senha é obrigatória';
                      }
                      return null;
                    },
                  ),
                ),
                const SizedBox(width: 10),

                // Botão Entrar
                Padding(
                  padding: const EdgeInsets.only(top: 0),
                  child: SizedBox(
                    height: 48,
                    width: 48,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: isLoading
                              ? [
                                  AppColors.ivoryMuted,
                                  AppColors.ivoryMuted
                                ]
                              : [AppColors.gold, AppColors.goldDark],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: (isLoading
                                    ? AppColors.ivoryMuted
                                    : AppColors.gold)
                                .withValues(alpha: 0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
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
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          padding: EdgeInsets.zero,
                        ),
                        child: isLoading
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: AppColors.navyDark,
                                ),
                              )
                            : const Icon(Icons.login, size: 22),
                      ),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}
