import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

class PaymentProcessingScreen extends StatelessWidget {
  const PaymentProcessingScreen({
    super.key,
    required this.onCheckStatus,
    this.countdownLabel,
  });

  final VoidCallback onCheckStatus;
  final String? countdownLabel;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(color: KioskTokens.primary),
              const SizedBox(height: 24),
              const Text(
                KioskStrings.processingTitle,
                style: KioskTokens.headline,
              ),
              const SizedBox(height: 8),
              const Text(
                KioskStrings.processingHint,
                style: KioskTokens.bodyMuted,
              ),
              if (countdownLabel != null) ...[
                const SizedBox(height: 8),
                Text(countdownLabel!, style: KioskTokens.body),
              ],
              const SizedBox(height: 24),
              OutlinedButton(
                onPressed: onCheckStatus,
                child: const Text(KioskStrings.checkStatus),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
