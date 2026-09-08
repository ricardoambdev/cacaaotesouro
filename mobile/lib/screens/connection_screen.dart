import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

/// Tela de verificação de conexão com a API.
///
/// Fluxo:
/// 1. Carrega baseUrl salva e verifica se está "locked".
/// 2. Se locked → vai direto para LoginScreen.
/// 3. Se não locked → tenta conectar. Sucesso → LoginScreen.
/// 4. Falha → mostra tela de configuração do endereço da API.
class ConnectionScreen extends StatefulWidget {
  const ConnectionScreen({super.key});

  @override
  State<ConnectionScreen> createState() => _ConnectionScreenState();
}

class _ConnectionScreenState extends State<ConnectionScreen> {
  static const String _keyApiLocked = 'api_locked';

  final _urlController = TextEditingController();
  final _apiService = ApiService();

  bool _isLoading = true;
  bool _isTesting = false;
  String? _errorMessage;

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

  /// Inicialização: lê baseUrl salva e verifica lock.
  Future<void> _initConnection() async {
    await _apiService.loadBaseUrl();

    final prefs = await SharedPreferences.getInstance();
    final locked = prefs.getBool(_keyApiLocked) ?? false;

    if (locked) {
      _navigateToChoice();
      return;
    }

    // Preenche o controller com o baseUrl atual
    _urlController.text = ApiService.baseUrl;

    // Tenta conectar automaticamente
    await _tryConnect();
  }

  /// Tenta conectar à API com o baseUrl atual.
  Future<void> _tryConnect() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final config = await _apiService.checkConnection();

      // Salva o endereço atual
      await ApiService.saveBaseUrl(ApiService.baseUrl);

      // Se devMode está desligado, trava a configuração
      if (!config.devMode) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool(_keyApiLocked, true);
      }

      if (mounted) {
        _navigateToChoice();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = e.message;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Não foi possível conectar à API.';
        });
      }
    }
  }

  /// Testa o endereço digitado pelo usuário.
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

    // Salva o endereço normalizado antes de testar
    await ApiService.saveBaseUrl(normalizedUrl);

    try {
      final config = await _apiService.checkConnection();

      // Sucesso: salva e verifica lock
      if (!config.devMode) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool(_keyApiLocked, true);
      }

      if (mounted) {
        _navigateToChoice();
      }
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

  void _navigateToChoice() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // ── Fundo gradiente navy ──────────────────────
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    AppColors.navyDark,
                    AppColors.navyMedium,
                    AppColors.navyDark,
                  ],
                  stops: const [0.0, 0.5, 1.0],
                ),
              ),
            ),
          ),

          // ── Conteúdo ──────────────────────────────────
          Positioned.fill(
            child: SafeArea(
              child: _isLoading ? _buildLoading() : _buildForm(),
            ),
          ),
        ],
      ),
    );
  }

  /// Estado de carregamento ("Conectando ao servidor...").
  Widget _buildLoading() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Ícone de conexão
          Icon(
            Icons.wifi_find_rounded,
            size: 64,
            color: AppColors.gold.withValues(alpha: 0.8),
          ),
          const SizedBox(height: 24),
          // Spinner
          SizedBox(
            width: 40,
            height: 40,
            child: CircularProgressIndicator(
              strokeWidth: 3,
              valueColor: AlwaysStoppedAnimation<Color>(AppColors.gold),
            ),
          ),
          const SizedBox(height: 24),
          // Texto
          Text(
            'Conectando ao servidor...',
            style: TextStyle(
              fontSize: 16,
              color: AppColors.ivoryMuted,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            ApiService.baseUrl,
            style: TextStyle(
              fontSize: 12,
              color: AppColors.ivoryMuted.withValues(alpha: 0.5),
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  /// Formulário de configuração do endereço da API.
  Widget _buildForm() {
    final padding = MediaQuery.paddingOf(context);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        children: [
          const Spacer(flex: 3),

          // ── Card central ─────────────────────────────
          Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: AppColors.cardDark,
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
                Text(
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
                        Icon(
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
