import 'package:flutter/material.dart';

import 'device/kiosk_controller.dart';
import 'features/shell/kiosk_shell.dart';
import 'shared/l10n/kiosk_strings.dart';
import 'shared/theme/kiosk_theme.dart';

class KioskApp extends StatelessWidget {
  const KioskApp({super.key, required this.controller});

  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: KioskStrings.productName,
      debugShowCheckedModeBanner: false,
      theme: KioskTheme.light(),
      builder: (context, child) {
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.noScaling),
          child: child ?? const SizedBox.shrink(),
        );
      },
      home: PopScope(
        canPop: false,
        child: Scaffold(
          body: KioskShell(controller: controller),
        ),
      ),
    );
  }
}
