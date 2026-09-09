import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';

/// Tela de configuração do jogo (admin).
///
/// Permite alterar status, data/horário de início e fim do jogo.
class GameConfigScreen extends StatefulWidget {
  const GameConfigScreen({super.key});

  @override
  State<GameConfigScreen> createState() => _GameConfigScreenState();
}

class _GameConfigScreenState extends State<GameConfigScreen> {
  final _apiService = ApiService();
  bool _isLoading = true;
  bool _isSaving = false;
  String? _error;

  // ── Campos do formulário ──────────────────────────
  String _status = 'playing'; // 'playing' | 'paused' | 'finished'
  DateTime? _startDate;
  TimeOfDay? _startTime;
  TimeOfDay? _endTime;

  @override
  void initState() {
    super.initState();
    _loadGame();
  }

  Future<void> _loadGame() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final game = await _apiService.adminGame();
      if (!mounted) return;

      // Parse status
      final rawStatus = (game['status'] as String?) ?? 'playing';

      // Parse start_date (Y-m-d ou '')
      DateTime? parsedDate;
      final dateStr = (game['start_date'] as String?) ?? '';
      if (dateStr.isNotEmpty) {
        parsedDate = DateTime.tryParse(dateStr);
      }

      // Parse start_time (H:i)
      TimeOfDay? parsedStart;
      final startStr = (game['start_time'] as String?) ?? '';
      if (startStr.isNotEmpty) {
        parsedStart = _parseTime(startStr);
      }

      // Parse end_time (H:i)
      TimeOfDay? parsedEnd;
      final endStr = (game['end_time'] as String?) ?? '';
      if (endStr.isNotEmpty) {
        parsedEnd = _parseTime(endStr);
      }

      setState(() {
        _status = rawStatus;
        _startDate = parsedDate;
        _startTime = parsedStart;
        _endTime = parsedEnd;
        _isLoading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isLoading = false;
      });
    } catch (_) {
      setState(() {
        _error = 'Erro ao carregar configurações do jogo.';
        _isLoading = false;
      });
    }
  }

  TimeOfDay _parseTime(String hhmm) {
    final parts = hhmm.split(':');
    if (parts.length == 2) {
      final h = int.tryParse(parts[0]) ?? 0;
      final m = int.tryParse(parts[1]) ?? 0;
      return TimeOfDay(hour: h, minute: m);
    }
    return const TimeOfDay(hour: 8, minute: 0);
  }

  String _formatDate(DateTime d) {
    return '${d.year.toString().padLeft(4, '0')}-'
        '${d.month.toString().padLeft(2, '0')}-'
        '${d.day.toString().padLeft(2, '0')}';
  }

  String _formatTime(TimeOfDay t) {
    return '${t.hour.toString().padLeft(2, '0')}:'
        '${t.minute.toString().padLeft(2, '0')}';
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _startDate ?? now,
      firstDate: DateTime(2024),
      lastDate: DateTime(2030),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppColors.gold,
              onPrimary: AppColors.navyDark,
              surface: AppColors.navyMedium,
              onSurface: AppColors.ivory,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _startDate = picked);
    }
  }

  Future<void> _pickStartTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _startTime ?? const TimeOfDay(hour: 8, minute: 0),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppColors.gold,
              onPrimary: AppColors.navyDark,
              surface: AppColors.navyMedium,
              onSurface: AppColors.ivory,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _startTime = picked);
    }
  }

  Future<void> _pickEndTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _endTime ?? const TimeOfDay(hour: 17, minute: 0),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppColors.gold,
              onPrimary: AppColors.navyDark,
              surface: AppColors.navyMedium,
              onSurface: AppColors.ivory,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _endTime = picked);
    }
  }

  Future<void> _save() async {
    setState(() => _isSaving = true);

    try {
      await _apiService.adminUpdateGame(
        status: _status,
        startDate: _startDate != null ? _formatDate(_startDate!) : '',
        startTime: _startTime != null ? _formatTime(_startTime!) : '',
        endTime: _endTime != null ? _formatTime(_endTime!) : '',
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Configurações salvas com sucesso!'),
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
          content: const Text('Erro ao salvar configurações.'),
          backgroundColor: Colors.redAccent.withValues(alpha: 0.85),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Configuração do Jogo',
          style: TextStyle(fontWeight: FontWeight.w700),
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
              onPressed: _loadGame,
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Explicação ─────────────────────────────────
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
            child: Row(
              children: [
                Icon(Icons.info_outline,
                    color: AppColors.gold.withValues(alpha: 0.7), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Os horários definem a janela em que os tesouros podem ser encontrados.',
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.ivoryMuted,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // ── Status do Jogo ─────────────────────────────
          const Text(
            'STATUS DO JOGO',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),
          _buildStatusSelector(),

          const SizedBox(height: 24),

          // ── Data de Início ─────────────────────────────
          const Text(
            'DATA DE INÍCIO',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildDateField(),
              ),
              if (_startDate != null) ...[
                const SizedBox(width: 8),
                IconButton(
                  onPressed: () => setState(() => _startDate = null),
                  icon: const Icon(Icons.close, color: AppColors.ivoryMuted),
                  tooltip: 'Limpar data',
                ),
              ],
            ],
          ),

          const SizedBox(height: 24),

          // ── Horários ──────────────────────────────────
          const Text(
            'JANDELA DE HORÁRIOS',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _buildTimeField(
                label: 'Início',
                time: _startTime,
                onTap: _pickStartTime,
              )),
              const SizedBox(width: 16),
              Expanded(child: _buildTimeField(
                label: 'Fim',
                time: _endTime,
                onTap: _pickEndTime,
              )),
            ],
          ),

          const SizedBox(height: 32),

          // ── Botão Salvar ───────────────────────────────
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
        ],
      ),
    );
  }

  Widget _buildStatusSelector() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.navyMedium,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: AppColors.ivoryMuted.withValues(alpha: 0.15),
        ),
      ),
      child: Column(
        children: [
          _buildStatusOption(
            value: 'playing',
            label: 'Em andamento',
            icon: Icons.play_circle_outline,
            color: Colors.greenAccent,
          ),
          _buildStatusOption(
            value: 'paused',
            label: 'Pausado',
            icon: Icons.pause_circle_outline,
            color: Colors.orange,
          ),
          _buildStatusOption(
            value: 'finished',
            label: 'Finalizado',
            icon: Icons.stop_circle_outlined,
            color: Colors.redAccent,
          ),
        ],
      ),
    );
  }

  Widget _buildStatusOption({
    required String value,
    required String label,
    required IconData icon,
    required Color color,
  }) {
    final isSelected = _status == value;
    return InkWell(
      onTap: () => setState(() => _status = value),
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: isSelected ? color.withValues(alpha: 0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Icon(
              icon,
              color: isSelected ? color : AppColors.ivoryMuted,
              size: 22,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  color: isSelected ? color : AppColors.ivory,
                ),
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: color, size: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildDateField() {
    final displayText = _startDate != null
        ? '${_startDate!.day.toString().padLeft(2, '0')}/'
            '${_startDate!.month.toString().padLeft(2, '0')}/'
            '${_startDate!.year}'
        : 'Não definida';

    return InkWell(
      onTap: _pickDate,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: AppColors.navyMedium,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: _startDate != null
                ? AppColors.gold.withValues(alpha: 0.3)
                : AppColors.ivoryMuted.withValues(alpha: 0.15),
          ),
        ),
        child: Row(
          children: [
            Icon(
              Icons.calendar_today,
              color: _startDate != null ? AppColors.gold : AppColors.ivoryMuted,
              size: 20,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Selecionar data',
                    style: TextStyle(
                      fontSize: 11,
                      color: AppColors.ivoryMuted.withValues(alpha: 0.7),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    displayText,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: _startDate != null
                          ? AppColors.ivory
                          : AppColors.ivoryMuted,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right,
              color: AppColors.ivoryMuted.withValues(alpha: 0.5),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTimeField({
    required String label,
    required TimeOfDay? time,
    required VoidCallback onTap,
  }) {
    final displayText = time != null ? _formatTime(time) : '--:--';

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.navyMedium,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: time != null
                ? AppColors.gold.withValues(alpha: 0.3)
                : AppColors.ivoryMuted.withValues(alpha: 0.15),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                color: AppColors.ivoryMuted.withValues(alpha: 0.7),
              ),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(
                  Icons.access_time,
                  color: time != null ? AppColors.gold : AppColors.ivoryMuted,
                  size: 18,
                ),
                const SizedBox(width: 8),
                Text(
                  displayText,
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: time != null ? AppColors.ivory : AppColors.ivoryMuted,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
