import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_status_screen.dart';

class PrintSuccessScreen extends StatelessWidget {
  const PrintSuccessScreen({super.key, required this.onDone});

  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return KioskStatusScreen(
      icon: Icons.print_outlined,
      iconColor: KioskTokens.success,
      title: KioskStrings.printSuccessTitle,
      body: KioskStrings.qrWarning,
      actionLabel: KioskStrings.doneCta,
      onAction: onDone,
    );
  }
}
