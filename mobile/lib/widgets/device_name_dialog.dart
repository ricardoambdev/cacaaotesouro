import 'package:flutter/material.dart';

import '../theme.dart';

/// Pergunta o nome DESTE aparelho.
///
/// Aparece no primeiro login (antes do jogo começar) e também quando a pessoa
/// toca no lápis, ao lado do nome da equipe, para editar.
///
/// O nome fica salvo no dispositivo e é enviado ao servidor: no mapa do
/// painel aparece um marcador por aparelho, com o nome e a cor da equipe.
Future<String?> showDeviceNameDialog(
  BuildContext context, {
  String initial = '',
  bool mandatory = false,
}) async {
  final controller = TextEditingController(text: initial);
  final formKey = GlobalKey<FormState>();

  final result = await showDialog<String>(
    context: context,
    barrierDismissible: !mandatory,
    builder: (ctx) {
      return AlertDialog(
        backgroundColor: AppColors.navyMedium,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Row(
          children: [
            const Icon(Icons.badge_outlined, color: AppColors.gold),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                initial.isEmpty ? 'Qual é o seu nome?' : 'Editar o nome',
                style: const TextStyle(
                  color: AppColors.gold,
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
            ),
          ],
        ),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Este nome identifica o SEU aparelho no mapa da organização.',
                style: TextStyle(
                  color: AppColors.ivoryMuted,
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: controller,
                autofocus: true,
                maxLength: 60,
                textCapitalization: TextCapitalization.words,
                style: const TextStyle(color: AppColors.ivory),
                decoration: const InputDecoration(
                  labelText: 'Seu nome',
                  hintText: 'Ex.: João',
                  prefixIcon: Icon(Icons.person, size: 20),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Informe o seu nome.';
                  }
                  return null;
                },
              ),
            ],
          ),
        ),
        actions: [
          if (!mandatory)
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text(
                'Cancelar',
                style: TextStyle(color: AppColors.ivoryMuted),
              ),
            ),
          ElevatedButton(
            onPressed: () {
              if (formKey.currentState?.validate() != true) return;
              Navigator.pop(ctx, controller.text.trim());
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.gold,
              foregroundColor: AppColors.navyDark,
            ),
            child: const Text('Salvar'),
          ),
        ],
      );
    },
  );

  controller.dispose();

  return result;
}
