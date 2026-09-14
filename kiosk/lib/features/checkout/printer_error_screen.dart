import 'package:flutter/material.dart';

import '../../network/dto/ticket_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../tickets/qr_display_screen.dart';

class PrinterErrorScreen extends StatelessWidget {
  const PrinterErrorScreen({
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
      child: Column(
        children: [
          const SafeArea(
            bottom: false,
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                KioskTokens.gutter,
                16,
                KioskTokens.gutter,
                0,
              ),
              child: Column(
                children: [
                  Text(
                    KioskStrings.printFailedTitle,
                    style: KioskTokens.headline,
                    textAlign: TextAlign.center,
                  ),
                  SizedBox(height: 8),
                  Text(
                    KioskStrings.printFailedBody,
                    style: KioskTokens.bodyMuted,
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            child: QrDisplayScreen(tickets: tickets, onDone: onDone),
          ),
        ],
      ),
    );
  }
}
