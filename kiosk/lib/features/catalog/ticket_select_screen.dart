import 'package:flutter/material.dart';

import '../../core/utils/money_format.dart';
import '../../network/dto/ticket_type_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_back_bar.dart';
import '../../shared/widgets/kiosk_primary_button.dart';
import '../../shared/widgets/qty_stepper.dart';

class TicketSelectScreen extends StatelessWidget {
  const TicketSelectScreen({
    super.key,
    required this.destinationName,
    required this.ticketTypes,
    required this.quantities,
    required this.onQuantity,
    required this.onContinue,
    this.onBack,
    this.canContinue = false,
    this.errorMessage,
  });

  final String destinationName;
  final List<TicketTypeDto> ticketTypes;
  final Map<int, int> quantities;
  final void Function(int ticketTypeId, int quantity) onQuantity;
  final VoidCallback onContinue;
  final VoidCallback? onBack;
  final bool canContinue;
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
                title: '$destinationName · ${KioskStrings.catalogTitle}',
                onBack: onBack,
              ),
              const SizedBox(height: 8),
              const Text(
                KioskStrings.serverPriceHint,
                style: KioskTokens.bodyMuted,
              ),
              const SizedBox(height: 16),
              if (ticketTypes.isEmpty)
                const Expanded(
                  child: Center(
                    child: Text(
                      KioskStrings.catalogEmpty,
                      style: KioskTokens.bodyMuted,
                    ),
                  ),
                )
              else
                Expanded(
                  child: ListView.separated(
                    itemCount: ticketTypes.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final type = ticketTypes[index];
                      return Material(
                        color: KioskTokens.surfaceRaised,
                        borderRadius: BorderRadius.circular(KioskTokens.radius),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(type.name, style: KioskTokens.title),
                                    if (type.description != null &&
                                        type.description!.isNotEmpty)
                                      Text(
                                        type.description!,
                                        style: KioskTokens.bodyMuted,
                                      ),
                                    Text(
                                      formatIdr(type.unitPrice),
                                      style: KioskTokens.body,
                                    ),
                                  ],
                                ),
                              ),
                              QtyStepper(
                                value: quantities[type.id] ?? 0,
                                max: type.maxPerOrder,
                                onChanged: (value) => onQuantity(type.id, value),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              if (errorMessage != null) ...[
                const SizedBox(height: 8),
                Text(
                  errorMessage!,
                  style: KioskTokens.body.copyWith(color: KioskTokens.danger),
                ),
              ],
              const SizedBox(height: 16),
              KioskPrimaryButton(
                label: KioskStrings.continueCta,
                onPressed: canContinue ? onContinue : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
