import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_status_screen.dart';

class PaymentSuccessScreen extends StatelessWidget {
  const PaymentSuccessScreen({
    super.key,
    required this.orderNumber,
    required this.ticketCount,
  });

  final String orderNumber;
  final int ticketCount;

  @override
  Widget build(BuildContext context) {
    return KioskStatusScreen(
      icon: Icons.check_circle_outline,
      iconColor: KioskTokens.success,
      title: KioskStrings.successTitle,
      body: '$orderNumber · $ticketCount tiket',
    );
  }
}

class TicketIssuingScreen extends StatelessWidget {
  const TicketIssuingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const ColoredBox(
      color: KioskTokens.surface,
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: KioskTokens.primary),
            SizedBox(height: 24),
            Text(KioskStrings.issuingTitle, style: KioskTokens.headline),
          ],
        ),
      ),
    );
  }
}
