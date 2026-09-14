import 'package:flutter/material.dart';

import '../../core/utils/money_format.dart';
import '../../network/dto/quote_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_back_bar.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class OrderSummaryScreen extends StatelessWidget {
  const OrderSummaryScreen({
    super.key,
    required this.quote,
    required this.destinationName,
    required this.onPay,
    this.onBack,
    this.errorMessage,
  });

  final QuoteDto quote;
  final String destinationName;
  final VoidCallback onPay;
  final VoidCallback? onBack;
  final String? errorMessage;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              KioskBackBar(
                title: KioskStrings.summaryTitle,
                onBack: onBack,
              ),
              const SizedBox(height: 8),
              Text(destinationName, style: KioskTokens.bodyMuted),
              const SizedBox(height: 16),
              Expanded(
                child: ListView(
                  children: [
                    for (final line in quote.items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${line.ticketTypeName} × ${line.quantity}',
                                style: KioskTokens.body,
                              ),
                            ),
                            Text(
                              formatIdr(line.lineGrandTotal),
                              style: KioskTokens.body,
                            ),
                          ],
                        ),
                      ),
                    const Divider(),
                    _MoneyRow(KioskStrings.subtotalLabel, quote.subtotal),
                    _MoneyRow(KioskStrings.discountLabel, quote.discountTotal),
                    _MoneyRow(KioskStrings.taxLabel, quote.taxTotal),
                    _MoneyRow(KioskStrings.serviceFeeLabel, quote.serviceFeeTotal),
                    const SizedBox(height: 8),
                    _MoneyRow(
                      KioskStrings.totalLabel,
                      quote.grandTotal,
                      emphasize: true,
                    ),
                  ],
                ),
              ),
              if (errorMessage != null)
                Text(
                  errorMessage!,
                  style: KioskTokens.body.copyWith(color: KioskTokens.danger),
                ),
              const SizedBox(height: 12),
              KioskPrimaryButton(
                label: KioskStrings.payCta,
                onPressed: onPay,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MoneyRow extends StatelessWidget {
  const _MoneyRow(this.label, this.amount, {this.emphasize = false});

  final String label;
  final int amount;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = emphasize ? KioskTokens.title : KioskTokens.body;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(formatIdr(amount), style: style),
        ],
      ),
    );
  }
}
