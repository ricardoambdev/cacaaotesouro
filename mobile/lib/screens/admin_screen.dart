import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import '../theme.dart';
import '../models/treasure.dart';
import '../models/game_state.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'qr_scanner_screen.dart';

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

  Future<void> _confirmCoordinate(GameTreasure treasure) async {
    // 0. Verificar permissão de câmera antes de tudo
    var cameraStatus = await Permission.camera.status;
    if (cameraStatus.isDenied) {
      cameraStatus = await Permission.camera.request();
    }

    if (cameraStatus.isPermanentlyDenied) {
      if (!mounted) return;
      await showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.navyMedium,
          title: const Text('Câmera desabilitada',
              style: TextStyle(color: AppColors.gold)),
          content: const Text(
            'A permissão da câmera foi negada permanentemente. '
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
                openAppSettings();
              },
              child: const Text('Configurações',
                  style: TextStyle(color: AppColors.gold)),
            ),
          ],
        ),
      );
      return;
    }

    if (!cameraStatus.isGranted && !cameraStatus.isLimited) {
      _showSnackBar('Permissão de câmera negada.', isError: true);
      return;
    }

    // 1. Verificar permissão de GPS
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

    // 2. Verificar GPS
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      _showSnackBar('GPS desligado. Ative a localização.', isError: true);
      return;
    }

    // 3. Obter posição
    Position position;
    try {
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } catch (_) {
      _showSnackBar('Não foi possível obter a posição.', isError: true);
      return;
    }

    // 4. Abrir scanner de QR Code
    if (!mounted) return;
    final qrCode = await Navigator.push<String>(
      context,
      MaterialPageRoute(
        builder: (_) => const QrScannerScreen(title: 'Ler QR do local'),
      ),
    );

    if (qrCode == null || !mounted) return; // cancelou

    // 5. Confirmar
    try {
      final result = await _apiService.confirmCoordinate(
        treasureId: treasure.id,
        lat: position.latitude,
        lng: position.longitude,
        qrCode: qrCode,
      );

      _showSnackBar(result['message'] ?? 'Coordenada confirmada!', isError: false);
      await _loadData();
    } on ApiException catch (e) {
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      _showSnackBar('Erro ao confirmar coordenada.', isError: true);
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
    return Container(
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
              color: status.gameActive
                  ? Colors.green.withValues(alpha: 0.2)
                  : Colors.orange.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              status.gameActive ? Icons.play_circle : Icons.pause_circle,
              color: status.gameActive ? Colors.greenAccent : Colors.orange,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  status.gameActive ? 'Jogo Ativo' : 'Jogo Pausado',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: status.gameActive
                        ? Colors.greenAccent
                        : Colors.orange,
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
        ],
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

          return Card(
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
                          style: TextStyle(
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
                ],
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildTreasureCard(GameTreasure treasure) {
    return Card(
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
                          style: TextStyle(
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
              ],
            ),

            if (treasure.hasCoord && treasure.lat != null) ...[
              const SizedBox(height: 8),
              Text(
                '${treasure.lat!.toStringAsFixed(6)}, ${treasure.lng!.toStringAsFixed(6)}',
                style: TextStyle(
                  color: AppColors.ivoryMuted,
                  fontSize: 12,
                  fontFamily: 'monospace',
                ),
              ),
            ],

            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _confirmCoordinate(treasure),
                icon: const Icon(Icons.my_location, size: 16),
                label: const Text(
                  'Confirmar coordenada',
                  style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.gold,
                  side: BorderSide(
                      color: AppColors.gold.withValues(alpha: 0.4)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
