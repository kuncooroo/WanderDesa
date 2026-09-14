import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wanderdesa_kiosk/features/tickets/qr_display_screen.dart';
import 'package:wanderdesa_kiosk/network/dto/ticket_dto.dart';
import 'package:wanderdesa_kiosk/shared/l10n/kiosk_strings.dart';
import 'package:wanderdesa_kiosk/shared/theme/kiosk_theme.dart';

void main() {
  testWidgets('multi-ticket QR pager is navigable', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1280, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        theme: KioskTheme.light(),
        home: QrDisplayScreen(
          tickets: const [
            TicketDto(
              id: 1,
              ticketCode: 'T-1',
              status: 'active',
              qrPayload: 'payload-one',
            ),
            TicketDto(
              id: 2,
              ticketCode: 'T-2',
              status: 'active',
              qrPayload: 'payload-two',
            ),
          ],
          onDone: () {},
        ),
      ),
    );

    expect(find.text(KioskStrings.qrTitle), findsOneWidget);
    expect(find.text('T-1'), findsOneWidget);
    expect(find.text('1 / 2'), findsOneWidget);

    await tester.fling(find.byType(PageView), const Offset(-500, 0), 1000);
    await tester.pumpAndSettle();

    expect(find.text('T-2'), findsOneWidget);
    expect(find.text('2 / 2'), findsOneWidget);
  });
}
