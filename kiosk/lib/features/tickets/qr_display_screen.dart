import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../network/dto/ticket_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class QrDisplayScreen extends StatelessWidget {
  const QrDisplayScreen({
    super.key,
    required this.tickets,
    required this.onDone,
  });

  final List<TicketDto> tickets;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            children: [
              const Text(KioskStrings.qrTitle, style: KioskTokens.headline),
              const SizedBox(height: 8),
              const Text(KioskStrings.qrWarning, style: KioskTokens.bodyMuted),
              const SizedBox(height: 16),
              Expanded(
                child: PageView.builder(
                  itemCount: tickets.length,
                  itemBuilder: (context, index) {
                    final ticket = tickets[index];
                    final payload = ticket.qrPayload ?? ticket.ticketCode;
                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: KioskTokens.surface,
                            border: Border.all(color: KioskTokens.border),
                            borderRadius:
                                BorderRadius.circular(KioskTokens.radius),
                          ),
                          child: QrImageView(
                            data: payload,
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
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(ticket.ticketCode, style: KioskTokens.title),
                        Text(
                          '${index + 1} / ${tickets.length}',
                          style: KioskTokens.bodyMuted,
                        ),
                      ],
                    );
                  },
                ),
              ),
              SizedBox(
                width: 360,
                child: KioskPrimaryButton(
                  label: KioskStrings.doneCta,
                  onPressed: onDone,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
