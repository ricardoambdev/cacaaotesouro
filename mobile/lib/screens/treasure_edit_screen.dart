import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import '../theme.dart';
import '../services/api_service.dart';
import 'qr_scanner_screen.dart';

/// Tela de edição de tesouro (admin).
///
/// Permite editar dados do tesouro e confirmar coordenada.
class TreasureEditScreen extends StatefulWidget {
  final int treasureId;

  const TreasureEditScreen({super.key, required this.treasureId});

  @override
  State<TreasureEditScreen> createState() => _TreasureEditScreenState();
}

class _TreasureEditScreenState extends State<TreasureEditScreen> {
  final _apiService = ApiService();
  final _formKey = GlobalKey<FormState>();

  // ── Controladores ─────────────────────────────────
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _clueController = TextEditingController();
  final _riddle1Controller = TextEditingController();
  final _answer1Controller = TextEditingController();
  final _riddle2Controller = TextEditingController();
  final _answer2Controller = TextEditingController();

  bool _isLoading = true;
  bool _isSaving = false;
  String? _error;

  // ── Dados do tesouro ──────────────────────────────
  String _code = '';
  bool _hasCoord = false;
  double? _lat;
  double? _lng;
  String? _qrSvgPath;

  @override
  void initState() {
    super.initState();
    _loadTreasure();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _clueController.dispose();
    _riddle1Controller.dispose();
    _answer1Controller.dispose();
    _riddle2Controller.dispose();
    _answer2Controller.dispose();
    super.dispose();
  }

  Future<void> _loadTreasure() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final data = await _apiService.adminTreasure(widget.treasureId);
      if (!mounted) return;

      setState(() {
        _code = (data['code'] as String?) ?? '';
        _nameController.text = (data['name'] as String?) ?? '';
        _descriptionController.text = (data['description'] as String?) ?? '';
        _clueController.text = (data['clue'] as String?) ?? '';
        _riddle1Controller.text = (data['riddle1'] as String?) ?? '';
        _answer1Controller.text = (data['answer1'] as String?) ?? '';
        _riddle2Controller.text = (data['riddle2'] as String?) ?? '';
        _answer2Controller.text = (data['answer2'] as String?) ?? '';
        _hasCoord = data['active'] == true &&
            data['lat'] != null &&
            data['lng'] != null;
        _lat = (data['lat'] != null) ? (data['lat'] as num).toDouble() : null;
        _lng = (data['lng'] != null) ? (data['lng'] as num).toDouble() : null;
        _qrSvgPath = data['qr_svg_path'] as String?;
        _isLoading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isLoading = false;
      });
    } catch (_) {
      setState(() {
        _error = 'Erro ao carregar tesouro.';
        _isLoading = false;
      });
    }
  }

  /// Validação client-side de respostas (4-8 dígitos).
  String? _validateAnswer(String? value, String fieldName) {
    if (value == null || value.trim().isEmpty) {
      return '$fieldName é obrigatório';
    }
    final trimmed = value.trim();
    if (!RegExp(r'^\d+$').hasMatch(trimmed)) {
      return '$fieldName deve conter apenas dígitos';
    }
    if (trimmed.length < 4 || trimmed.length > 8) {
      return '$fieldName deve ter 4 a 8 dígitos';
    }
    return null;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    try {
      await _apiService.adminUpdateTreasure(widget.treasureId, {
        'name': _nameController.text.trim(),
        'description': _descriptionController.text.trim(),
        'clue': _clueController.text.trim(),
        'riddle1': _riddle1Controller.text.trim(),
        'answer1': _answer1Controller.text.trim(),
        'riddle2': _riddle2Controller.text.trim(),
        'answer2': _answer2Controller.text.trim(),
      });

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Tesouro salvo com sucesso!'),
          backgroundColor: AppColors.navyMedium,
          behavior: SnackBarBehavior.floating,
        ),
      );
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => _isSaving = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.redAccent.withValues(alpha: 0.85),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      setState(() => _isSaving = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Erro ao salvar tesouro.'),
          backgroundColor: Colors.redAccent.withValues(alpha: 0.85),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _confirmCoordinate() async {
    // 0. Verificar permissão de câmera
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
            'A permissão de localização foi negada permanentemente.',
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

    // 4. Abrir scanner QR
    if (!mounted) return;
    final qrCode = await Navigator.push<String>(
      context,
      MaterialPageRoute(
        builder: (_) => const QrScannerScreen(title: 'Ler QR do local'),
      ),
    );

    if (qrCode == null || !mounted) return;

    // 5. Confirmar
    try {
      final result = await _apiService.confirmCoordinate(
        treasureId: widget.treasureId,
        lat: position.latitude,
        lng: position.longitude,
        qrCode: qrCode,
      );

      // Atualizar coordenadas locais
      setState(() {
        _hasCoord = true;
        _lat = position.latitude;
        _lng = position.longitude;
      });

      _showSnackBar(
          result['message'] ?? 'Coordenada confirmada!', isError: false);

      // Recarregar dados do tesouro
      await _loadTreasure();
    } on ApiException catch (e) {
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      _showSnackBar('Erro ao confirmar coordenada.', isError: true);
    }
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
        title: Text(
          _code.isNotEmpty ? 'Tesouro $_code' : 'Tesouro',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.gold))
          : _error != null
              ? _buildError()
              : _buildForm(),
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
              onPressed: _loadTreasure,
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

  Widget _buildForm() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Status da Coordenada ──────────────────────
            _buildCoordinateStatus(),

            const SizedBox(height: 24),

            // ── Nome ─────────────────────────────────────
            TextFormField(
              controller: _nameController,
              style: const TextStyle(color: AppColors.ivory),
              decoration: const InputDecoration(
                labelText: 'Nome do tesouro',
                prefixIcon: Icon(Icons.title, size: 20),
              ),
              validator: (v) {
                if (v == null || v.trim().isEmpty) {
                  return 'Nome é obrigatório';
                }
                return null;
              },
            ),

            const SizedBox(height: 16),

            // ── Descrição ────────────────────────────────
            TextFormField(
              controller: _descriptionController,
              style: const TextStyle(color: AppColors.ivory),
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Descrição',
                prefixIcon: Icon(Icons.description, size: 20),
                alignLabelWithHint: true,
              ),
            ),

            const SizedBox(height: 16),

            // ── Dica ─────────────────────────────────────
            TextFormField(
              controller: _clueController,
              style: const TextStyle(color: AppColors.ivory),
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Dica do local',
                prefixIcon: Icon(Icons.lightbulb_outline, size: 20),
                alignLabelWithHint: true,
              ),
            ),

            const SizedBox(height: 28),

            // ── Charada 1 ───────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.2),
                ),
              ),
              child: const Text(
                'CHARADA 1',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 2,
                  color: AppColors.gold,
                ),
              ),
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _riddle1Controller,
              style: const TextStyle(color: AppColors.ivory),
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Texto da charada 1',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _answer1Controller,
              style: const TextStyle(color: AppColors.ivory),
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Resposta 1 (4-8 dígitos)',
                prefixIcon: Icon(Icons.key, size: 20),
              ),
              validator: (v) => _validateAnswer(v, 'Resposta 1'),
            ),

            const SizedBox(height: 28),

            // ── Charada 2 ───────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.2),
                ),
              ),
              child: const Text(
                'CHARADA 2',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 2,
                  color: AppColors.gold,
                ),
              ),
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _riddle2Controller,
              style: const TextStyle(color: AppColors.ivory),
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Texto da charada 2',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _answer2Controller,
              style: const TextStyle(color: AppColors.ivory),
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Resposta 2 (4-8 dígitos)',
                prefixIcon: Icon(Icons.key, size: 20),
              ),
              validator: (v) => _validateAnswer(v, 'Resposta 2'),
            ),

            const SizedBox(height: 32),

            // ── Botão Confirmar Coordenada ──────────────
            SizedBox(
              width: double.infinity,
              height: 52,
              child: OutlinedButton.icon(
                onPressed: _confirmCoordinate,
                icon: const Icon(Icons.my_location, size: 20),
                label: const Text(
                  'Confirmar Coordenada',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.gold,
                  side: BorderSide(
                    color: AppColors.gold.withValues(alpha: 0.5),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ),

            const SizedBox(height: 16),

            // ── Botão Salvar ─────────────────────────────
            SizedBox(
              width: double.infinity,
              height: 52,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: _isSaving
                        ? [AppColors.ivoryMuted, AppColors.ivoryMuted]
                        : [AppColors.gold, AppColors.goldDark],
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
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _save,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.transparent,
                    shadowColor: Colors.transparent,
                    foregroundColor: AppColors.navyDark,
                    disabledBackgroundColor: Colors.transparent,
                    disabledForegroundColor:
                        AppColors.navyDark.withValues(alpha: 0.5),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: AppColors.navyDark,
                          ),
                        )
                      : const Text(
                          'Salvar',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildCoordinateStatus() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _hasCoord
            ? Colors.green.withValues(alpha: 0.08)
            : Colors.orange.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: _hasCoord
              ? Colors.green.withValues(alpha: 0.3)
              : Colors.orange.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                _hasCoord ? Icons.check_circle : Icons.warning_amber,
                color: _hasCoord ? Colors.greenAccent : Colors.orange,
                size: 22,
              ),
              const SizedBox(width: 10),
              Text(
                _hasCoord ? 'Coordenada Confirmada' : 'Coordenada Pendente',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: _hasCoord ? Colors.greenAccent : Colors.orange,
                ),
              ),
            ],
          ),
          if (_hasCoord && _lat != null && _lng != null) ...[
            const SizedBox(height: 8),
            Text(
              '${_lat!.toStringAsFixed(6)}, ${_lng!.toStringAsFixed(6)}',
              style: const TextStyle(
                fontSize: 13,
                fontFamily: 'monospace',
                color: AppColors.ivoryMuted,
              ),
            ),
          ],
          if (_qrSvgPath != null && _qrSvgPath!.isNotEmpty) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.network(
                _qrSvgPath!,
                height: 120,
                width: 120,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Container(
                  height: 120,
                  width: 120,
                  decoration: BoxDecoration(
                    color: AppColors.navyDark,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.qr_code_2,
                      color: AppColors.ivoryMuted, size: 40),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
