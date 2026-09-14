import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_status_screen.dart';

class DisabledScreen extends StatelessWidget {
  const DisabledScreen({super.key, this.onActivate});

  final VoidCallback? onActivate;

  @override
  Widget build(BuildContext context) {
    return KioskStatusScreen(
      icon: Icons.block,
      iconColor: KioskTokens.danger,
      title: KioskStrings.disabledTitle,
      body: KioskStrings.disabledBody,
      actionLabel: KioskStrings.activateCta,
      onAction: onActivate,
    );
  }
}
