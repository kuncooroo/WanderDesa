import 'dart:async';

import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/utils/money_format.dart';
import '../../network/dto/payment_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

/// Payment QR with a countdown from the server expiry.
class PaymentScreen extends StatefulWidget {
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
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  Timer? _timer;
  Duration? _remaining;

  @override
  void initState() {
    super.initState();
    _remaining = _computeRemaining();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _syncRemaining());
  }

  @override
  void didUpdateWidget(PaymentScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    _syncRemaining();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _syncRemaining() {
    final next = _computeRemaining();
    if (!mounted || next == _remaining) {
      return;
    }
    setState(() => _remaining = next);
  }

  Duration? _computeRemaining() {
    final raw = widget.nextAction?.expiresAt;
    final end = raw == null ? null : DateTime.tryParse(raw);
    if (end == null) {
      return null;
    }
    final next = end.difference(DateTime.now());
    return next.isNegative ? Duration.zero : next;
  }

  bool get _expired => _remaining == Duration.zero;

  String get _countdownLabel {
    final left = _remaining;
    if (left == null || left == Duration.zero) {
      return '';
    }
    final minutes = left.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = left.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  @override
  Widget build(BuildContext context) {
    final qr = widget.nextAction?.qrContent;

    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            children: [
              Row(
                children: [
                  if (widget.onCancel != null)
                    IconButton(
                      onPressed: widget.onCancel,
                      tooltip: KioskStrings.backCta,
                      style: IconButton.styleFrom(
                        foregroundColor: KioskTokens.ink,
                        minimumSize: const Size(48, 48),
                      ),
                      icon: const Text(
                        '<',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w400,
                          height: 1,
                          color: KioskTokens.ink,
                        ),
                      ),
                    )
                  else
                    const SizedBox(width: 48),
                  const Expanded(
                    child: Text(
                      KioskStrings.paymentTitle,
                      textAlign: TextAlign.center,
                      style: KioskTokens.title,
                    ),
                  ),
                  const SizedBox(width: 48),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                formatIdr(widget.amount),
                style: KioskTokens.display.copyWith(fontSize: 36),
              ),
              const SizedBox(height: 8),
              Text(
                _expired ? KioskStrings.paymentExpired : KioskStrings.paymentHint,
                textAlign: TextAlign.center,
                style: KioskTokens.bodyMuted,
              ),
              if (_countdownLabel.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  _countdownLabel,
                  style: KioskTokens.display.copyWith(fontSize: 28),
                ),
              ],
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_expired)
                      const SizedBox(height: 280)
                    else
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: KioskTokens.surface,
                          border: Border.all(color: KioskTokens.border),
                          borderRadius: BorderRadius.circular(KioskTokens.radius),
                        ),
                        child: qr != null && qr.isNotEmpty
                            ? QrImageView(
                                data: qr,
                                size: 280,
                                backgroundColor: KioskTokens.surface,
                                eyeStyle: const QrEyeStyle(
                                  eyeShape: QrEyeShape.square,
                                  color: KioskTokens.ink,
                                ),
                                dataModuleStyle: const QrDataModuleStyle(
                                  dataModuleShape: QrDataModuleShape.square,
                                  color: KioskTokens.ink,
                                ),
                              )
                            : const SizedBox(
                                width: 280,
                                height: 280,
                                child: Center(child: CircularProgressIndicator()),
                              ),
                      ),
                  ],
                ),
              ),
              if (_expired && widget.onCancel != null)
                SizedBox(
                  width: 280,
                  child: FilledButton(
                    onPressed: widget.onCancel,
                    child: const Text(KioskStrings.newOrder),
                  ),
                ),
              if (widget.onCheckStatus != null) ...[
                const SizedBox(height: 12),
                SizedBox(
                  width: 280,
                  child: OutlinedButton(
                    onPressed: widget.onCheckStatus,
                    child: const Text(KioskStrings.checkStatus),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
