import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_status_screen.dart';

class NetworkErrorScreen extends StatelessWidget {
  const NetworkErrorScreen({
    super.key,
    required this.onRetry,
    this.message,
  });

  final VoidCallback onRetry;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return KioskStatusScreen(
      icon: Icons.wifi_off,
      iconColor: KioskTokens.danger,
      title: KioskStrings.networkTitle,
      body: message ?? KioskStrings.networkBody,
      actionLabel: KioskStrings.networkRetry,
      onAction: onRetry,
      secondaryLabel: KioskStrings.networkHelp,
    );
  }
}
