import 'package:flutter/material.dart';

abstract final class KioskTokens {
  static const Color primary = Color(0xFF0F6B4D);
  static const Color primaryPressed = Color(0xFF0B513A);
  static const Color surface = Color(0xFFF4F1EA);
  static const Color surfaceRaised = Color(0xFFFFFFFF);
  static const Color ink = Color(0xFF1A1A1A);
  static const Color inkMuted = Color(0xFF4B5563);
  static const Color success = Color(0xFF027A48);
  static const Color warning = Color(0xFFB54708);
  static const Color danger = Color(0xFFB42318);
  static const Color overlay = Color(0xE61A1A1A);

  static const double radius = 16;
  static const double primaryCtaMin = 64;
  static const double secondaryCtaMin = 48;
  static const double gutter = 32;

  static const TextStyle display = TextStyle(
    fontSize: 48,
    fontWeight: FontWeight.w700,
    height: 1.15,
    color: Colors.white,
  );

  static const TextStyle headline = TextStyle(
    fontSize: 36,
    fontWeight: FontWeight.w700,
    height: 1.2,
    color: ink,
  );

  static const TextStyle title = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w600,
    height: 1.3,
    color: ink,
  );

  static const TextStyle body = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w400,
    height: 1.4,
    color: ink,
  );

  static const TextStyle bodyMuted = TextStyle(
    fontSize: 18,
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
      colorScheme: ColorScheme.fromSeed(
        seedColor: KioskTokens.primary,
        primary: KioskTokens.primary,
        surface: KioskTokens.surface,
      ),
      scaffoldBackgroundColor: KioskTokens.surface,
      fontFamily: 'Roboto',
    );

    return base.copyWith(
      textTheme: base.textTheme.apply(
        bodyColor: KioskTokens.ink,
        displayColor: KioskTokens.ink,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(88, KioskTokens.primaryCtaMin),
          backgroundColor: KioskTokens.primary,
          foregroundColor: Colors.white,
          textStyle: const TextStyle(
            fontSize: 22,
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
          textStyle: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(KioskTokens.radius),
          ),
        ),
      ),
    );
  }
}
