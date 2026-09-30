import 'package:flutter/material.dart';

/// Minimalist kiosk palette — white / black / gray across all screens.
abstract final class KioskTokens {
  static const Color primary = Color(0xFF111111);
  static const Color primaryPressed = Color(0xFF000000);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceRaised = Color(0xFFF3F4F6);
  static const Color ink = Color(0xFF111111);
  static const Color inkMuted = Color(0xFF6B7280);
  static const Color border = Color(0xFFE5E7EB);
  static const Color success = Color(0xFF111111);
  static const Color warning = Color(0xFF6B7280);
  static const Color danger = Color(0xFFB91C1C);
  static const Color overlay = Color(0xCC111111);

  static const double radius = 12;
  static const double primaryCtaMin = 64;
  static const double secondaryCtaMin = 48;
  static const double gutter = 32;

  static const TextStyle display = TextStyle(
    fontSize: 48,
    fontWeight: FontWeight.w700,
    height: 1.15,
    color: ink,
    letterSpacing: -0.5,
  );

  static const TextStyle headline = TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.w700,
    height: 1.2,
    color: ink,
  );

  static const TextStyle title = TextStyle(
    fontSize: 22,
    fontWeight: FontWeight.w600,
    height: 1.3,
    color: ink,
  );

  static const TextStyle body = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w400,
    height: 1.4,
    color: ink,
  );

  static const TextStyle bodyMuted = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w400,
    height: 1.4,
    color: inkMuted,
  );
}

abstract final class KioskTheme {
  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: const ColorScheme.light(
        primary: KioskTokens.primary,
        onPrimary: Colors.white,
        secondary: KioskTokens.inkMuted,
        onSecondary: Colors.white,
        surface: KioskTokens.surface,
        onSurface: KioskTokens.ink,
        error: KioskTokens.danger,
        onError: Colors.white,
        outline: KioskTokens.border,
      ),
      scaffoldBackgroundColor: KioskTokens.surface,
      fontFamily: 'Roboto',
    );

    return base.copyWith(
      textTheme: base.textTheme.apply(
        bodyColor: KioskTokens.ink,
        displayColor: KioskTokens.ink,
      ),
      dividerColor: KioskTokens.border,
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: KioskTokens.primary,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(88, KioskTokens.primaryCtaMin),
          backgroundColor: KioskTokens.primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: KioskTokens.surfaceRaised,
          disabledForegroundColor: KioskTokens.inkMuted,
          textStyle: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(KioskTokens.radius),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(88, KioskTokens.secondaryCtaMin),
          foregroundColor: KioskTokens.ink,
          backgroundColor: KioskTokens.surfaceRaised,
          side: const BorderSide(color: KioskTokens.border),
          textStyle: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(KioskTokens.radius),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: KioskTokens.surfaceRaised,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(KioskTokens.radius),
          borderSide: const BorderSide(color: KioskTokens.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(KioskTokens.radius),
          borderSide: const BorderSide(color: KioskTokens.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(KioskTokens.radius),
          borderSide: const BorderSide(color: KioskTokens.ink, width: 1.5),
        ),
      ),
    );
  }
}
