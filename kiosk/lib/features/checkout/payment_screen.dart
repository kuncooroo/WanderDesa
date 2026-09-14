import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/utils/money_format.dart';
import '../../network/dto/payment_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

class PaymentScreen extends StatelessWidget {
  const PaymentScreen({
    super.key,
    required this.amount,
    this.nextAction,
    this.onCancel,
    this.onCheckStatus,
  });

  final int amount;
  final NextActionDto? nextAction;
  final VoidCallback? onCancel;
  final VoidCallback? onCheckStatus;

  @override
  Widget build(BuildContext context) {
    final qr = nextAction?.qrContent;
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      KioskStrings.paymentTitle,
                      style: KioskTokens.headline,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      formatIdr(amount),
                      style: KioskTokens.display.copyWith(
                        color: KioskTokens.ink,
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      KioskStrings.paymentHint,
                      style: KioskTokens.bodyMuted,
                    ),
                    const Spacer(),
                    if (onCheckStatus != null)
                      OutlinedButton(
                        onPressed: onCheckStatus,
                        child: const Text(KioskStrings.checkStatus),
                      ),
                    if (onCancel != null) ...[
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: onCancel,
                        child: const Text(KioskStrings.cancelUnpaid),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 32),
              if (qr != null && qr.isNotEmpty)
                ColoredBox(
                  color: Colors.white,
                  child: QrImageView(data: qr, size: 280),
                )
              else
                const SizedBox(
                  width: 280,
                  height: 280,
                  child: Center(child: CircularProgressIndicator()),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
