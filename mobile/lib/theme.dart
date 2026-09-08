import 'package:flutter/material.dart';

/// Paleta de cores do Caça ao Tesouro
/// Azul-marinho profundo, dourado e marfim
class AppColors {
  AppColors._();

  // ── Fundo principal ──────────────────────────────────
  static const Color navyDark = Color(0xFF050B12);
  static const Color navyMedium = Color(0xFF0A1724);

  // ── Dourado / Âmbar → Laranja ───────────────────────
  static const Color gold = Color(0xFFF97316);
  static const Color goldDark = Color(0xFFEA580C);

  // ── Texto ────────────────────────────────────────────
  static const Color ivory = Color(0xFFF7ECD4);
  static const Color ivoryMuted = Color(0xFFBEB5A0);

  // ── Overlay / Transparências ─────────────────────────
  static const Color cardDark = Color(0xB3050B12); // ~70% opacidade
  static const Color overlayBottom = Color(0x99050B12); // 60% opacidade

  /// Converte o nome da cor da equipe (vindo da API) em uma cor.
  /// Aceita 'laranja', 'preta', 'vermelha' ou um hex (#RRGGBB).
  static Color teamColor(String name) {
    switch (name.toLowerCase()) {
      case 'laranja':
        return const Color(0xFFE67E22);
      case 'preta':
        return const Color(0xFF2C3E50);
      case 'vermelha':
        return const Color(0xFFC0392B);
    }

    final cleaned = name.replaceFirst('#', '');
    final value = int.tryParse(cleaned, radix: 16);
    if (cleaned.length == 6 && value != null) {
      return Color(0xFF000000 | value);
    }

    return gold;
  }
}

/// Tema escuro customizado do app
ThemeData appTheme() {
  return ThemeData(
    brightness: Brightness.dark,
    scaffoldBackgroundColor: AppColors.navyDark,
    colorScheme: const ColorScheme.dark(
      primary: AppColors.gold,
      onPrimary: AppColors.navyDark,
      secondary: AppColors.goldDark,
      onSecondary: AppColors.navyDark,
      surface: AppColors.navyMedium,
      onSurface: AppColors.ivory,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.navyDark,
      foregroundColor: AppColors.ivory,
      elevation: 0,
    ),
    snackBarTheme: const SnackBarThemeData(
      backgroundColor: AppColors.navyMedium,
      contentTextStyle: TextStyle(color: AppColors.ivory),
      behavior: SnackBarBehavior.floating,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.navyMedium.withValues(alpha: 0.8),
      labelStyle: const TextStyle(color: AppColors.ivoryMuted),
      hintStyle: TextStyle(color: AppColors.ivoryMuted.withValues(alpha: 0.6)),
      prefixIconColor: AppColors.gold,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: AppColors.ivoryMuted.withValues(alpha: 0.3)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: AppColors.ivoryMuted.withValues(alpha: 0.3)),
      ),
      focusedBorder: const OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(12)),
        borderSide: BorderSide(color: AppColors.gold, width: 2),
      ),
      errorBorder: const OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(12)),
        borderSide: BorderSide(color: Colors.redAccent),
      ),
      focusedErrorBorder: const OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(12)),
        borderSide: BorderSide(color: Colors.redAccent, width: 2),
      ),
      errorStyle: const TextStyle(color: Colors.redAccent),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
  );
}
