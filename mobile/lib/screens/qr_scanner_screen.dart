import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';
import '../theme.dart';

/// Tela de leitura de QR Code usando mobile_scanner.
///
/// Retorna o conteúdo lido como String via Navigator.pop(context, qrCode).
class QrScannerScreen extends StatefulWidget {
  final String? title;

  const QrScannerScreen({super.key, this.title});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  MobileScannerController? _controller;
  bool _isProcessing = false;
  bool _hasError = false;
  String? _errorMessage;
  bool _permissionGranted = false;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  /// Inicializa a câmera após verificar permissão.
  Future<void> _initCamera() async {
    // Verificar/gerenciar permissão
    var status = await Permission.camera.status;
    if (!status.isGranted && !status.isLimited) {
      status = await Permission.camera.request();
    }

    if (!status.isGranted && !status.isLimited) {
      setState(() {
        _hasError = true;
        _errorMessage = status.isPermanentlyDenied
            ? 'Permissão de câmera negada permanentemente.'
            : 'Permissão de câmera negada.';
      });
      return;
    }

    setState(() => _permissionGranted = true);

    try {
      _controller = MobileScannerController(
        detectionSpeed: DetectionSpeed.normal,
        facing: CameraFacing.back,
        torchEnabled: false,
      );
      if (mounted) setState(() {});
    } catch (e) {
      setState(() {
        _hasError = true;
        _errorMessage = 'Não foi possível inicializar a câmera.';
      });
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  /// Reinicia o controller da câmera.
  void _retryCamera() {
    _controller?.dispose();
    _controller = null;
    setState(() {
      _hasError = false;
      _errorMessage = null;
      _isProcessing = false;
      _permissionGranted = false;
    });
    _initCamera();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isProcessing) return;

    final barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;

    final code = barcodes.first.rawValue;
    if (code == null || code.isEmpty) return;

    _isProcessing = true;

    if (mounted) {
      Navigator.pop(context, code);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: AppColors.navyDark,
        title: Text(
          widget.title ?? 'Ler QR Code',
          style: const TextStyle(
            color: AppColors.ivory,
            fontWeight: FontWeight.w600,
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close, color: AppColors.ivory),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          if (_permissionGranted && _controller != null)
            IconButton(
              icon: const Icon(Icons.flash_off, color: AppColors.ivory),
              onPressed: () => _controller?.toggleTorch(),
            ),
        ],
      ),
      body: _hasError
          ? _buildErrorView()
          : (_permissionGranted && _controller != null)
              ? _buildScannerView()
              : _buildLoadingView(),
    );
  }

  /// Tela de carregamento enquanto inicializa a câmera.
  Widget _buildLoadingView() {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircularProgressIndicator(
            color: AppColors.gold,
            strokeWidth: 3,
          ),
          SizedBox(height: 16),
          Text(
            'Inicializando câmera...',
            style: TextStyle(color: AppColors.ivoryMuted, fontSize: 14),
          ),
        ],
      ),
    );
  }

  /// Tela de erro quando a câmera falha.
  Widget _buildErrorView() {
    final isPermDenied = _errorMessage?.contains('permanentemente') ?? false;
    final isPermission = _errorMessage?.contains('Permissão') ?? false;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.videocam_off_rounded,
              size: 64,
              color: Colors.redAccent.withValues(alpha: 0.8),
            ),
            const SizedBox(height: 20),
            const Text(
              'Câmera indisponível',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
                color: AppColors.ivory,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _errorMessage ?? 'Não foi possível acessar a câmera.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.ivoryMuted,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 28),

            // Botão Tentar novamente
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
                ),
                child: ElevatedButton.icon(
                  onPressed: _retryCamera,
                  icon: const Icon(Icons.refresh_rounded, size: 20),
                  label: const Text(
                    'Tentar novamente',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.transparent,
                    shadowColor: Colors.transparent,
                    foregroundColor: AppColors.navyDark,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ),

            // Botão Abrir configurações (se negado permanentemente)
            if (isPermDenied || isPermission) ...[
              const SizedBox(height: 12),
              TextButton.icon(
                onPressed: () => openAppSettings(),
                icon: const Icon(Icons.settings, size: 16),
                label: const Text('Abrir configurações'),
                style: TextButton.styleFrom(
                  foregroundColor: AppColors.gold,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Tela do scanner com moldura.
  Widget _buildScannerView() {
    return Stack(
      children: [
        // ── Scanner ──────────────────────────────────────
        MobileScanner(
          controller: _controller!,
          onDetect: _onDetect,
          errorBuilder: (context, error) {
            // Captura o erro real do MobileScanner para diagnóstico.
            final code = error.errorCode.name;
            final details = error.errorDetails?.message ?? '';
            _errorMessage = details.isNotEmpty
                ? 'Erro ($code): $details'
                : 'Erro da câmera ($code).';
            _hasError = true;
            return _buildErrorView();
          },
        ),

        // ── Overlay com moldura ──────────────────────────
        Center(
          child: Container(
            width: 280,
            height: 280,
            decoration: BoxDecoration(
              border: Border.all(
                color: AppColors.gold.withValues(alpha: 0.8),
                width: 3,
              ),
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),

        // ── Instrução ────────────────────────────────────
        Positioned(
          bottom: 80,
          left: 0,
          right: 0,
          child: Center(
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              decoration: BoxDecoration(
                color: AppColors.navyDark.withValues(alpha: 0.8),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                'Aponte para o QR Code do tesouro',
                style: TextStyle(
                  color: AppColors.ivory,
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
