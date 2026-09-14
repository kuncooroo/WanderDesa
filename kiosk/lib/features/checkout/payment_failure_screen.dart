import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class PaymentFailureScreen extends StatelessWidget {
  const PaymentFailureScreen({
    super.key,
    required this.onRetryPay,
    required this.onChangeTickets,
    required this.onCancel,
    this.message,
  });

  final VoidCallback onRetryPay;
  final VoidCallback onChangeTickets;
  final VoidCallback onCancel;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 88, color: KioskTokens.danger),
              const SizedBox(height: 16),
              const Text(
                KioskStrings.failureTitle,
                style: KioskTokens.headline,
              ),
              const SizedBox(height: 8),
              Text(
                message ?? KioskStrings.homeHelp,
                style: KioskTokens.bodyMuted,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: 420,
                child: KioskPrimaryButton(
                  label: KioskStrings.retryPay,
                  onPressed: onRetryPay,
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: onChangeTickets,
                child: const Text(KioskStrings.changeTickets),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: onCancel,
                child: const Text(KioskStrings.cancelUnpaid),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
