import 'package:flutter/material.dart';
import '../theme.dart';
import '../models/treasure.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
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
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Sair',
            onPressed: _logout,
          ),
        ],
      ),
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
    return RefreshIndicator(
      onRefresh: _loadData,
      color: AppColors.gold,
      backgroundColor: AppColors.navyMedium,
      child: ListView(
        padding: const EdgeInsets.all(16),
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
