import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:geolocator/geolocator.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../theme.dart';
import '../models/treasure.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import 'login_screen.dart';
import 'game_config_screen.dart';
import 'team_detail_screen.dart';
import 'treasure_edit_screen.dart';

/// Tela principal do administrador.
class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  final _apiService = ApiService();
  List<GameTreasure> _treasures = [];
  AdminStatus? _status;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        _apiService.adminTreasures(),
        _apiService.adminStatus(),
      ]);

      if (!mounted) return;

      setState(() {
        _treasures = results[0] as List<GameTreasure>;
        _status = results[1] as AdminStatus;
        _isLoading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isLoading = false;
      });
    } catch (_) {
      setState(() {
        _error = 'Erro ao carregar dados.';
        _isLoading = false;
      });
    }
  }

  Future<void> _disconnectAll() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.navyMedium,
        title: const Text('Desconectar todas as equipes',
            style: TextStyle(color: AppColors.gold)),
        content: const Text(
          'Tem certeza? Todas as equipes serão desconectadas do aplicativo.',
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
            child: const Text('Desconectar'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final result = await _apiService.disconnectAll();
      _showSnackBar(result['message'] ?? 'Equipes desconectadas!', isError: false);
    } on ApiException catch (e) {
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      _showSnackBar('Erro ao desconectar equipes.', isError: true);
    }
  }

  Future<void> _logout() async {
    await _apiService.adminLogout();
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

  /// Rótulo do status do jogo (baseado no novo gameStatus).
  String _gameStatusLabel(AdminStatus status) {
    switch (status.gameStatus) {
      case 'paused':
        return 'Pausado';
      case 'finished':
        return 'Finalizado';
      default:
        return 'Em andamento';
    }
  }

  IconData _gameStatusIcon(AdminStatus status) {
    switch (status.gameStatus) {
      case 'paused':
        return Icons.pause_circle;
      case 'finished':
        return Icons.flag_circle;
      default:
        return Icons.play_circle;
    }
  }

  Color _gameStatusColor(AdminStatus status) {
    switch (status.gameStatus) {
      case 'paused':
        return Colors.orange;
      case 'finished':
        return Colors.redAccent;
      default:
        return Colors.greenAccent;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Admin — Caça ao Tesouro',
          style: TextStyle(fontWeight: FontWeight.w700, letterSpacing: 0.5),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Atualizar',
            onPressed: _loadData,
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomBar(),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold))
          : _error != null
              ? _buildError()
              : _buildContent(),
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
                style: const TextStyle(color: AppColors.ivory, fontSize: 16)),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _loadData,
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

  Widget _buildContent() {
    final bottomInset = MediaQuery.viewPaddingOf(context).bottom;
    return RefreshIndicator(
      onRefresh: _loadData,
      color: AppColors.gold,
      backgroundColor: AppColors.navyMedium,
      child: ListView(
        padding: EdgeInsets.fromLTRB(16, 16, 16, 32 + bottomInset),
        children: [
          // ── Status do Jogo ────────────────────────────
          if (_status != null) _buildGameStatus(),

          const SizedBox(height: 16),

          // ── Enviar mensagem para todas as equipes ────
          _buildBroadcastMessageButton(),

          const SizedBox(height: 16),

          // ── Equipes ───────────────────────────────────
          if (_status != null && _status!.teams.isNotEmpty) _buildTeams(),

          const SizedBox(height: 16),

          // ── Botão Desconectar ─────────────────────────
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _disconnectAll,
              icon: const Icon(Icons.wifi_off, size: 18),
              label: const Text('Desconectar todas as equipes'),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.redAccent,
                side: const BorderSide(color: Colors.redAccent),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),

          const SizedBox(height: 24),

          // ── Lista de tesouros ─────────────────────────
          const Text(
            'TESOUROS',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),
          ..._treasures.map((t) => _buildTreasureCard(t)),
        ],
      ),
    );
  }

  Widget _buildGameStatus() {
    final status = _status!;
    return InkWell(
      onTap: () async {
        final result = await Navigator.push<bool>(
          context,
          MaterialPageRoute(builder: (_) => const GameConfigScreen()),
        );
        if (result == true) {
          await _loadData();
        }
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.navyMedium,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: AppColors.gold.withValues(alpha: 0.2),
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: _gameStatusColor(status).withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                _gameStatusIcon(status),
                color: _gameStatusColor(status),
                size: 28,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _gameStatusLabel(status),
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: _gameStatusColor(status),
                    ),
                  ),
                  if (status.winnerTeamId != null)
                    Text(
                      'Vencedor: Equipe #${status.winnerTeamId}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.gold,
                      ),
                    ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right,
              color: AppColors.ivoryMuted.withValues(alpha: 0.5),
              size: 24,
            ),
          ],
        ),
      ),
    );
  }

  /// Botão para enviar mensagem para TODAS as equipes.
  Widget _buildBroadcastMessageButton() {
    return SizedBox(
      width: double.infinity,
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
              color: AppColors.gold.withValues(alpha: 0.25),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ElevatedButton.icon(
          onPressed: _showBroadcastMessageDialog,
          icon: const Icon(Icons.campaign, size: 20),
          label: const Text(
            'Enviar mensagem para as equipes',
            style: TextStyle(
              fontSize: 15,
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
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
        ),
      ),
    );
  }

  /// Dialog para enviar mensagem para todas as equipes.
  Future<void> _showBroadcastMessageDialog() async {
    final controller = TextEditingController();
    bool isSending = false;

    final sent = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setDialogState) {
            return AlertDialog(
              backgroundColor: AppColors.navyMedium,
              title: const Row(
                children: [
                  Icon(Icons.campaign, color: AppColors.gold, size: 22),
                  SizedBox(width: 10),
                  Text(
                    'Mensagem para todas as equipes',
                    style: TextStyle(
                      color: AppColors.gold,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              content: TextField(
                controller: controller,
                style: const TextStyle(color: AppColors.ivory),
                maxLines: 3,
                maxLength: 500,
                autofocus: true,
                decoration: const InputDecoration(
                  hintText: 'Ex: Parabéns a todas as equipes!',
                  hintStyle: TextStyle(color: AppColors.ivoryMuted),
                  alignLabelWithHint: true,
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isSending
                      ? null
                      : () => Navigator.pop(ctx, false),
                  child: const Text('Cancelar',
                      style: TextStyle(color: AppColors.ivoryMuted)),
                ),
                ElevatedButton(
                  onPressed: isSending
                      ? null
                      : () async {
                          final msg = controller.text.trim();
                          if (msg.isEmpty) {
                            ScaffoldMessenger.of(ctx).showSnackBar(
                              SnackBar(
                                content: const Text('Digite uma mensagem.'),
                                backgroundColor:
                                    Colors.redAccent.withValues(alpha: 0.85),
                                behavior: SnackBarBehavior.floating,
                              ),
                            );
                            return;
                          }
                          setDialogState(() => isSending = true);
                          try {
                            await _apiService.adminTeamMessageAll(msg);
                            if (ctx.mounted) Navigator.pop(ctx, true);
                          } on ApiException catch (e) {
                            if (ctx.mounted) {
                              ScaffoldMessenger.of(ctx).showSnackBar(
                                SnackBar(
                                  content: Text(e.message),
                                  backgroundColor:
                                      Colors.redAccent.withValues(alpha: 0.85),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                            setDialogState(() => isSending = false);
                          } catch (_) {
                            if (ctx.mounted) {
                              ScaffoldMessenger.of(ctx).showSnackBar(
                                SnackBar(
                                  content: const Text(
                                      'Erro ao enviar mensagem.'),
                                  backgroundColor:
                                      Colors.redAccent.withValues(alpha: 0.85),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                            setDialogState(() => isSending = false);
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.gold,
                    foregroundColor: AppColors.navyDark,
                  ),
                  child: isSending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.navyDark,
                          ),
                        )
                      : const Text('Enviar'),
                ),
              ],
            );
          },
        );
      },
    );

    controller.dispose();

    if (sent == true && mounted) {
      _showSnackBar('Mensagem enviada para todas as equipes!', isError: false);
    }
  }

  /// Barra inferior com "História", "Cofre" e "Sair".
  Widget _buildBottomBar() {
    return SafeArea(
      top: false,
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.navyMedium,
          border: Border(
            top: BorderSide(
              color: AppColors.gold,
              width: 0.3,
            ),
          ),
        ),
        child: Row(
          children: [
            // ── História ──────────────────────────────
            Expanded(
              child: InkWell(
                onTap: _showStorySheet,
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.menu_book, color: AppColors.gold, size: 22),
                      SizedBox(height: 4),
                      Text(
                        'História',
                        style: TextStyle(
                          color: AppColors.gold,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            // ── Cofre ────────────────────────────────
            Expanded(
              child: InkWell(
                onTap: _showVaultSheet,
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.lock, color: AppColors.gold, size: 22),
                      SizedBox(height: 4),
                      Text(
                        'Cofre',
                        style: TextStyle(
                          color: AppColors.gold,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            // ── Sair ─────────────────────────────────
            Expanded(
              child: InkWell(
                onTap: _confirmLogout,
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.logout, color: Colors.redAccent, size: 22),
                      SizedBox(height: 4),
                      Text(
                        'Sair',
                        style: TextStyle(
                          color: Colors.redAccent,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Modal de confirmação antes de sair do painel admin.
  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.navyMedium,
        title: const Text('Sair do painel admin?',
            style: TextStyle(color: AppColors.gold)),
        content: const Text(
          'Você será desconectado e o app voltará para a tela de login.',
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

    if (confirmed == true) {
      await _logout();
    }
  }

  /// Abre a história do jogo em um modal inferior (somente leitura).
  Future<void> _showStorySheet() async {
    // Busca fresco: a história pode ter sido editada depois do load inicial
    AdminStatus? fresh;
    try {
      fresh = await _apiService.adminStatus();
      if (mounted) setState(() => _status = fresh);
    } catch (_) {
      // Sem rede: usa o que já está em memória
    }
    final status = fresh ?? _status;
    final story = status?.story; // String? — null = servidor sem o campo

    if (!mounted) return;

    // Determina qual conteúdo mostrar na folha
    Widget content;
    if (story == null) {
      // Servidor NÃO retorna o campo (código antigo / desatualizado)
      content = Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.info_outline, color: AppColors.gold, size: 48),
              const SizedBox(height: 16),
              const Text(
                'História não disponível neste servidor.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'O sistema em uso ainda não envia a história para o aplicativo. '
                'Atualize o sistema na hospedagem (git pull) e tente novamente.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: AppColors.ivoryMuted,
                  height: 1.5,
                ),
              ),
            ],
          ),
        ),
      );
    } else if (story.trim().isEmpty) {
      // Campo existe mas está vazio
      content = const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Text(
            'Nenhuma história cadastrada.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 15,
              fontStyle: FontStyle.italic,
              color: AppColors.ivoryMuted,
            ),
          ),
        ),
      );
    } else {
      // Conteúdo real
      final bottomSafe = MediaQuery.viewPaddingOf(context).bottom;
      content = SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(20, 20, 20, 32 + bottomSafe),
        child: HtmlWidget(
          story,
          textStyle: const TextStyle(
            fontSize: 15,
            height: 1.7,
            color: AppColors.ivory,
          ),
        ),
      );
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => SizedBox(
        height: MediaQuery.sizeOf(ctx).height * 0.85,
        child: Container(
          decoration: const BoxDecoration(
            color: AppColors.navyMedium,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            children: [
              // ── Handle ────────────────────────────────
              Container(
                margin: const EdgeInsets.only(top: 10),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.ivoryMuted.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              // ── Título ────────────────────────────────
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                child: Row(
                  children: [
                    Icon(Icons.menu_book, color: AppColors.gold, size: 22),
                    SizedBox(width: 10),
                    Text(
                      'História',
                      style: TextStyle(
                        color: AppColors.gold,
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: AppColors.ivoryMuted),
              // ── Conteúdo ──────────────────────────────
              Expanded(child: content),
            ],
          ),
        ),
      ),
    );
  }

  /// Abre as informações do cofre em um modal inferior.
  Future<void> _showVaultSheet() async {
    if (!mounted) return;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => SizedBox(
        height: MediaQuery.sizeOf(ctx).height * 0.85,
        child: Container(
          decoration: const BoxDecoration(
            color: AppColors.navyMedium,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: const _VaultSheetContent(),
        ),
      ),
    );
  }

  Widget _buildTeams() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'EQUIPES',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            letterSpacing: 2,
            color: AppColors.gold,
          ),
        ),
        const SizedBox(height: 12),
        ..._status!.teams.map((team) {
          final teamColor = AppColors.teamColor(team.color);

          return InkWell(
            onTap: () async {
              final result = await Navigator.push<bool>(
                context,
                MaterialPageRoute(
                  builder: (_) => TeamDetailScreen(
                    teamId: team.id,
                    teamName: team.name,
                    teamColor: team.color,
                    teamPoints: team.points,
                  ),
                ),
              );
              if (result == true) {
                await _loadData();
              }
            },
            borderRadius: BorderRadius.circular(12),
            child: Card(
              color: AppColors.navyMedium,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: teamColor.withValues(alpha: 0.3)),
              ),
              margin: const EdgeInsets.only(bottom: 8),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Container(
                      width: 4,
                      height: 40,
                      decoration: BoxDecoration(
                        color: teamColor,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            team.name,
                            style: const TextStyle(
                              color: AppColors.ivory,
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Passo ${team.foundCount} · ${team.status}',
                            style: const TextStyle(
                              color: AppColors.ivoryMuted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: teamColor.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '${team.points} pts',
                        style: TextStyle(
                          color: teamColor,
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      Icons.chevron_right,
                      color: AppColors.ivoryMuted.withValues(alpha: 0.5),
                      size: 20,
                    ),
                  ],
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildTreasureCard(GameTreasure treasure) {
    return InkWell(
      onTap: () async {
        final result = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
            builder: (_) => TreasureEditScreen(treasureId: treasure.id),
          ),
        );
        if (result == true) {
          await _loadData();
        }
      },
      borderRadius: BorderRadius.circular(14),
      child: Card(
        color: AppColors.navyMedium,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(
            color: treasure.hasCoord
                ? AppColors.gold.withValues(alpha: 0.3)
                : AppColors.ivoryMuted.withValues(alpha: 0.15),
          ),
        ),
        margin: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.gold.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.account_balance_wallet_outlined,
                        color: AppColors.gold, size: 20),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          treasure.name,
                          style: const TextStyle(
                            color: AppColors.ivory,
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                          ),
                        ),
                        if (treasure.code != null)
                          Text(
                            treasure.code!,
                            style: const TextStyle(
                              color: AppColors.ivoryMuted,
                              fontSize: 12,
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (treasure.hasCoord)
                    Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.green.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        '✓ Coordenada',
                        style: TextStyle(
                          color: Colors.greenAccent,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    )
                  else
                    Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.orange.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        'Pendente',
                        style: TextStyle(
                          color: Colors.orange,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  const SizedBox(width: 8),
                  Icon(
                    Icons.chevron_right,
                    color: AppColors.ivoryMuted.withValues(alpha: 0.5),
                    size: 20,
                  ),
                ],
              ),

              if (treasure.hasCoord && treasure.lat != null) ...[
                const SizedBox(height: 8),
                Text(
                  '${treasure.lat!.toStringAsFixed(6)}, ${treasure.lng!.toStringAsFixed(6)}',
                  style: const TextStyle(
                    color: AppColors.ivoryMuted,
                    fontSize: 12,
                    fontFamily: 'monospace',
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Conteúdo da folha do Cofre (carrega dados via API e exibe situação,
/// link público, QR code e bloqueios ativos).
class _VaultSheetContent extends StatefulWidget {
  const _VaultSheetContent();

  @override
  State<_VaultSheetContent> createState() => _VaultSheetContentState();
}

class _VaultSheetContentState extends State<_VaultSheetContent> {
  final _apiService = ApiService();
  bool _isLoading = true;
  String? _error;
  Map<String, dynamic>? _vaultData;
  bool _isCapturing = false;

  @override
  void initState() {
    super.initState();
    _loadVault();
  }

  /// Captura a coordenada atual (GPS) e grava como o LOCAL do cofre.
  ///
  /// É a coordenada onde o cofre físico está: a página pública só abre
  /// para quem estiver dentro do raio configurado (padrão 100 m).
  Future<void> _captureCoordinate() async {
    setState(() => _isCapturing = true);

    try {
      final permission = await Geolocator.checkPermission();

      if (permission == LocationPermission.denied) {
        await Geolocator.requestPermission();
      }

      if (!mounted) return;

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );

      if (!mounted) return;

      final result = await _apiService.adminVaultSetCoordinate(
        lat: position.latitude,
        lng: position.longitude,
      );

      if (!mounted) return;

      setState(() {
        _isCapturing = false;
        if (_vaultData != null) {
          _vaultData!['lat'] = result['lat'];
          _vaultData!['lng'] = result['lng'];
          _vaultData!['radius'] = result['radius'];
        }
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Coordenada salva: ${position.latitude.toStringAsFixed(6)}, '
            '${position.longitude.toStringAsFixed(6)}',
          ),
          backgroundColor: AppColors.navyMedium,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 3),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _isCapturing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _isCapturing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
              'Não foi possível obter o GPS. Autorize a localização e tente novamente.'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _loadVault() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final data = await _apiService.adminVaultStatus();
      if (!mounted) return;
      setState(() {
        _vaultData = data;
        _isLoading = false;
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
        _error = 'Erro ao carregar dados do cofre.';
        _isLoading = false;
      });
    }
  }

  /// Formata uma string ISO 8601 em d/m/Y H:i.
  String _formatDateTime(String? isoDate) {
    if (isoDate == null || isoDate.isEmpty) return '—';
    try {
      final dt = DateTime.parse(isoDate);
      return '${dt.day}/${dt.month}/${dt.year} '
          '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return isoDate;
    }
  }

  Future<void> _unblockAll() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.navyMedium,
        title: const Text('Liberar bloqueios?',
            style: TextStyle(color: AppColors.gold)),
        content: const Text(
          'Todos os bloqueios ativos serão removidos. '
          'Os IPs poderão tentar novamente.',
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
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
            child: const Text('Liberar'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      await _apiService.adminVaultUnblock();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Todos os bloqueios foram liberados!'),
          backgroundColor: AppColors.navyMedium,
          behavior: SnackBarBehavior.floating,
        ),
      );
      await _loadVault();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.redAccent.withValues(alpha: 0.85),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Erro ao liberar bloqueios.'),
          backgroundColor: Colors.redAccent.withValues(alpha: 0.85),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomSafe = MediaQuery.viewPaddingOf(context).bottom;

    return Column(
      children: [
        // ── Handle ────────────────────────────────
        Container(
          margin: const EdgeInsets.only(top: 10),
          width: 40,
          height: 4,
          decoration: BoxDecoration(
            color: AppColors.ivoryMuted.withValues(alpha: 0.3),
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        // ── Título ────────────────────────────────
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          child: Row(
            children: [
              Icon(Icons.lock, color: AppColors.gold, size: 22),
              SizedBox(width: 10),
              Text(
                'Cofre da Gincana',
                style: TextStyle(
                  color: AppColors.gold,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 1, color: AppColors.ivoryMuted),
        // ── Conteúdo ──────────────────────────────
        Expanded(
          child: _isLoading
              ? const Center(
                  child:
                      CircularProgressIndicator(color: AppColors.gold))
              : _error != null
                  ? _buildError()
                  : _buildVaultBody(bottomSafe),
        ),
      ],
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
                color: Colors.redAccent, size: 40),
            const SizedBox(height: 14),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                  color: AppColors.ivory, fontSize: 15),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: _loadVault,
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

  Widget _buildVaultBody(double bottomSafe) {
    final data = _vaultData!;
    final bool configured = data['configured'] == true;
    final int? codeDigits = data['code_digits'] as int?;
    final int? attempts = data['attempts'] as int?;
    final int? blockMinutes = data['block_minutes'] as int?;
    final bool blockNextDay = data['block_next_day'] == true;
    final String url = (data['url'] as String?) ?? '';
    final List<dynamic> blockedRaw =
        (data['blocked'] as List<dynamic>?) ?? [];

    // Coordenada onde o cofre está (capturada aqui pelo admin).
    final double? vaultLat = (data['lat'] as num?)?.toDouble();
    final double? vaultLng = (data['lng'] as num?)?.toDouble();
    final int vaultRadius = (data['radius'] as num?)?.toInt() ?? 100;
    final bool hasCoordinate = vaultLat != null && vaultLng != null;

    return RefreshIndicator(
      onRefresh: _loadVault,
      color: AppColors.gold,
      backgroundColor: AppColors.navyMedium,
      child: ListView(
        padding: EdgeInsets.fromLTRB(20, 16, 20, 32 + bottomSafe),
        children: [
          // ── 1. Situação ─────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: configured
                  ? Colors.green.withValues(alpha: 0.08)
                  : Colors.orangeAccent.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: configured
                    ? Colors.green.withValues(alpha: 0.25)
                    : Colors.orangeAccent.withValues(alpha: 0.3),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      configured ? Icons.check_circle : Icons.warning_amber,
                      color: configured
                          ? Colors.greenAccent
                          : Colors.orangeAccent,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'SITUAÇÃO',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 2,
                        color: configured
                            ? Colors.greenAccent
                            : Colors.orangeAccent,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  configured
                      ? 'Código de ${codeDigits ?? 9} dígitos configurado'
                      : 'O cofre ainda não foi configurado no painel web',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ivory,
                  ),
                ),
                if (configured) ...[
                  const SizedBox(height: 10),
                  _vaultInfoRow(
                      'Tentativas antes de bloquear', '$attempts'),
                  _vaultInfoRow(
                      'Tempo de bloqueio', '$blockMinutes min'),
                  _vaultInfoRow(
                      'Bloqueia até o dia seguinte',
                      blockNextDay ? 'Sim' : 'Não'),
                ],
              ],
            ),
          ),

          const SizedBox(height: 18),

          // ── 2. Local do cofre (geofence) ───────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.2),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.place, color: AppColors.gold, size: 20),
                    SizedBox(width: 8),
                    Text(
                      'LOCAL DO COFRE',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 2,
                        color: AppColors.gold,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  hasCoordinate
                      ? '${vaultLat.toStringAsFixed(6)}, ${vaultLng.toStringAsFixed(6)}'
                      : 'Nenhuma coordenada configurada.',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    fontFamily: 'monospace',
                    color: hasCoordinate
                        ? AppColors.ivory
                        : Colors.orangeAccent,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  hasCoordinate
                      ? 'A página do cofre só abre num raio de $vaultRadius m deste ponto.'
                      : 'Sem coordenada, a página do cofre abre em qualquer lugar. '
                          'Capture a coordenada no local onde o cofre físico está.',
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.4,
                    color: AppColors.ivoryMuted,
                  ),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _isCapturing ? null : _captureCoordinate,
                    icon: _isCapturing
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.navyDark,
                            ),
                          )
                        : const Icon(Icons.my_location, size: 18),
                    label: Text(
                      _isCapturing
                          ? 'Capturando...'
                          : (hasCoordinate
                              ? 'Atualizar coordenada'
                              : 'Capturar coordenada'),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.gold,
                      foregroundColor: AppColors.navyDark,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // ── 3. Link público + QR ──────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.navyMedium,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.2),
              ),
            ),
            child: Column(
              children: [
                Text(
                  'LINK PÚBLICO DO COFRE',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2,
                    color: AppColors.gold.withValues(alpha: 0.7),
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: AppColors.navyDark,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: AppColors.ivoryMuted.withValues(alpha: 0.15),
                    ),
                  ),
                  child: SelectableText(
                    url,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.ivory,
                      height: 1.4,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: url));
                      if (!mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: const Text('Link copiado!'),
                          backgroundColor: AppColors.navyMedium,
                          behavior: SnackBarBehavior.floating,
                          duration: const Duration(seconds: 2),
                        ),
                      );
                    },
                    icon: const Icon(Icons.copy, size: 18),
                    label: const Text('Copiar link'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.gold,
                      side: BorderSide(
                          color:
                              AppColors.gold.withValues(alpha: 0.4)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding:
                          const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                // QR Code
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color:
                            AppColors.gold.withValues(alpha: 0.12),
                        blurRadius: 12,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      QrImageView(
                        data: url,
                        size: 180,
                        backgroundColor: Colors.white,
                        eyeStyle: const QrEyeStyle(
                          eyeShape: QrEyeShape.square,
                          color: AppColors.navyDark,
                        ),
                        dataModuleStyle: const QrDataModuleStyle(
                          dataModuleShape: QrDataModuleShape.square,
                          color: AppColors.navyDark,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Escaneie para abrir o cofre',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.navyDark,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // ── 3. Bloqueios ativos ────────────────
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
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'BLOQUEIOS ATIVOS',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 2,
                    color: AppColors.gold.withValues(alpha: 0.7),
                  ),
                ),
                const SizedBox(height: 12),
                if (blockedRaw.isEmpty)
                  Row(
                    children: [
                      const Icon(Icons.check_circle,
                          color: Colors.greenAccent, size: 18),
                      const SizedBox(width: 8),
                      const Text(
                        'Nenhum bloqueio ativo',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Colors.greenAccent,
                        ),
                      ),
                    ],
                  )
                else ...[
                  ...blockedRaw.map((b) {
                    final ip = b['ip'] as String? ?? '—';
                    final blockedUntil =
                        b['blocked_until'] as String?;
                    final blocks = b['blocks'] as int? ?? 0;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color:
                            Colors.redAccent.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: Colors.redAccent
                              .withValues(alpha: 0.25),
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.gpp_maybe,
                              color: Colors.redAccent, size: 18),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment:
                                  CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'IP: $ip',
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.ivory,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Bloqueado até: ${_formatDateTime(blockedUntil)} · Tentativas: $blocks',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.ivoryMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 6),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: _unblockAll,
                      icon: const Icon(Icons.lock_open, size: 18),
                      label:
                          const Text('Liberar todos os bloqueios'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.greenAccent,
                        side: const BorderSide(
                            color: Colors.greenAccent),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        padding: const EdgeInsets.symmetric(
                            vertical: 12),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 18),

          // ── 4. Botão Atualizar ──────────────────
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _loadVault,
              icon: const Icon(Icons.refresh, size: 20),
              label: const Text('Atualizar'),
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

          const SizedBox(height: 8),
        ],
      ),
    );
  }

  /// Linha informativa (rótulo: valor).
  Widget _vaultInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Text(
            '$label: ',
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.ivoryMuted,
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.ivory,
            ),
          ),
        ],
      ),
    );
  }
}
