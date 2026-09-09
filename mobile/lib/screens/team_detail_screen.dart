import 'package:flutter/material.dart';
import '../theme.dart';
import '../services/api_service.dart';

/// Tela de detalhes de uma equipe (admin).
///
/// Permite ajustar pontos e enviar mensagens.
class TeamDetailScreen extends StatefulWidget {
  final int teamId;
  final String teamName;
  final String teamColor;
  final int teamPoints;

  const TeamDetailScreen({
    super.key,
    required this.teamId,
    required this.teamName,
    required this.teamColor,
    required this.teamPoints,
  });

  @override
  State<TeamDetailScreen> createState() => _TeamDetailScreenState();
}

class _TeamDetailScreenState extends State<TeamDetailScreen> {
  final _apiService = ApiService();
  final _deltaController = TextEditingController();
  final _reasonController = TextEditingController();
  final _messageController = TextEditingController();
  bool _isApplying = false;
  bool _isSending = false;
  int _currentPoints = 0;

  @override
  void initState() {
    super.initState();
    _currentPoints = widget.teamPoints;
  }

  @override
  void dispose() {
    _deltaController.dispose();
    _reasonController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Color get _teamColor => AppColors.teamColor(widget.teamColor);

  Future<void> _applyDelta(int delta) async {
    // Confirmação para delta negativo
    if (delta < 0) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.navyMedium,
          title: const Text('Confirmar redução',
              style: TextStyle(color: Colors.redAccent)),
          content: Text(
            'Deseja remover ${delta.abs()} pontos de "${widget.teamName}"?',
            style: const TextStyle(color: AppColors.ivory),
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
              child: const Text('Remover'),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
    }

    final reason = _reasonController.text.trim();
    final deltaValue = _deltaController.text.trim();

    int effectiveDelta = delta;
    if (deltaValue.isNotEmpty) {
      effectiveDelta = int.tryParse(deltaValue) ?? delta;
    }

    if (effectiveDelta == 0) {
      _showSnackBar('Informe um valor diferente de zero.', isError: true);
      return;
    }

    setState(() => _isApplying = true);

    try {
      final result = await _apiService.adminTeamPoints(
        widget.teamId,
        effectiveDelta,
        reason: reason.isNotEmpty ? reason : null,
      );

      final newPoints = (result['points'] as num?)?.toInt();
      if (newPoints != null) {
        setState(() => _currentPoints = newPoints);
      }

      _deltaController.clear();
      _reasonController.clear();

      _showSnackBar(
        '${effectiveDelta >= 0 ? '+' : ''}$effectiveDelta pontos aplicados!',
        isError: false,
      );
    } on ApiException catch (e) {
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      _showSnackBar('Erro ao ajustar pontos.', isError: true);
    } finally {
      setState(() => _isApplying = false);
    }
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty) {
      _showSnackBar('Digite uma mensagem.', isError: true);
      return;
    }

    if (message.length > 500) {
      _showSnackBar('Mensagem deve ter no máximo 500 caracteres.', isError: true);
      return;
    }

    setState(() => _isSending = true);

    try {
      await _apiService.adminTeamMessage(widget.teamId, message);
      _messageController.clear();

      _showSnackBar('Mensagem enviada!', isError: false);
    } on ApiException catch (e) {
      _showSnackBar(e.message, isError: true);
    } catch (_) {
      _showSnackBar('Erro ao enviar mensagem.', isError: true);
    } finally {
      setState(() => _isSending = false);
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
        title: Row(
          children: [
            Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                color: _teamColor,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                widget.teamName,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Pontos atuais ────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: _teamColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _teamColor.withValues(alpha: 0.3),
                ),
              ),
              child: Column(
                children: [
                  const Text(
                    'PONTOS ATUAIS',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 2,
                      color: AppColors.ivoryMuted,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '$_currentPoints',
                    style: TextStyle(
                      fontSize: 42,
                      fontWeight: FontWeight.w900,
                      color: _teamColor,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 28),

            // ── Ajustar Pontos ───────────────────────────
            const Text(
              'AJUSTAR PONTOS',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                letterSpacing: 2,
                color: AppColors.gold,
              ),
            ),
            const SizedBox(height: 12),

            // Botões rápidos
            Row(
              children: [
                _buildQuickDeltaButton(-20, Colors.redAccent),
                const SizedBox(width: 8),
                _buildQuickDeltaButton(-10, Colors.redAccent),
                const SizedBox(width: 8),
                _buildQuickDeltaButton(10, Colors.greenAccent),
                const SizedBox(width: 8),
                _buildQuickDeltaButton(20, Colors.greenAccent),
              ],
            ),

            const SizedBox(height: 12),

            // Campo delta customizado
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _deltaController,
                    keyboardType: TextInputType.number,
                    style: const TextStyle(color: AppColors.ivory),
                    decoration: InputDecoration(
                      labelText: 'Valor personalizado',
                      hintText: 'Ex: -5 ou +15',
                      prefixIcon: const Icon(Icons.numbers, size: 20),
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _isApplying
                        ? null
                        : () => _applyDelta(
                            int.tryParse(_deltaController.text) ?? 0),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.gold,
                      foregroundColor: AppColors.navyDark,
                      disabledBackgroundColor:
                          AppColors.ivoryMuted.withValues(alpha: 0.3),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: _isApplying
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.navyDark,
                            ),
                          )
                        : const Text('Aplicar'),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            // Campo motivo
            TextField(
              controller: _reasonController,
              style: const TextStyle(color: AppColors.ivory),
              decoration: InputDecoration(
                labelText: 'Motivo (opcional)',
                prefixIcon: const Icon(Icons.notes, size: 20),
                contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14, vertical: 12),
              ),
              maxLength: 200,
            ),

            const SizedBox(height: 28),

            // ── Enviar Mensagem ──────────────────────────
            const Text(
              'ENVIAR MENSAGEM',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                letterSpacing: 2,
                color: AppColors.gold,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'A mensagem aparecerá como aviso no app da equipe.',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.ivoryMuted.withValues(alpha: 0.7),
              ),
            ),
            const SizedBox(height: 12),

            TextField(
              controller: _messageController,
              style: const TextStyle(color: AppColors.ivory),
              maxLines: 3,
              maxLength: 500,
              decoration: InputDecoration(
                labelText: 'Mensagem',
                hintText: 'Ex: Parabéns! Ponto bônus...',
                alignLabelWithHint: true,
                contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14, vertical: 12),
              ),
            ),

            const SizedBox(height: 12),

            SizedBox(
              width: double.infinity,
              height: 48,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: _isSending
                        ? [AppColors.ivoryMuted, AppColors.ivoryMuted]
                        : [AppColors.gold, AppColors.goldDark],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: ElevatedButton.icon(
                  onPressed: _isSending ? null : _sendMessage,
                  icon: const Icon(Icons.send, size: 18),
                  label: Text(
                    _isSending ? 'Enviando...' : 'Enviar Mensagem',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
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
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickDeltaButton(int delta, Color color) {
    return Expanded(
      child: SizedBox(
        height: 44,
        child: OutlinedButton(
          onPressed: _isApplying ? null : () => _applyDelta(delta),
          style: OutlinedButton.styleFrom(
            foregroundColor: color,
            side: BorderSide(color: color.withValues(alpha: 0.5)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            padding: EdgeInsets.zero,
          ),
          child: Text(
            delta >= 0 ? '+$delta' : '$delta',
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 14,
            ),
          ),
        ),
      ),
    );
  }
}
