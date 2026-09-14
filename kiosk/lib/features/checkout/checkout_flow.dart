import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/constants/kiosk_timing.dart';
import '../../core/errors/error_mapper.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../catalog/destination_browse_screen.dart';
import '../catalog/ticket_select_screen.dart';
import '../network/network_error_screen.dart';
import '../recovery/recovery_screen.dart';
import '../tickets/qr_display_screen.dart';
import 'checkout_session.dart';
import 'order_summary_screen.dart';
import 'payment_failure_screen.dart';
import 'payment_processing_screen.dart';
import 'payment_screen.dart';
import 'payment_success_screen.dart';
import 'print_success_screen.dart';
import 'printer_error_screen.dart';
import 'printing_screen.dart';
import 'session_timeout_modal.dart';

class CheckoutFlow extends StatefulWidget {
  const CheckoutFlow({super.key, required this.session});

  final CheckoutSession session;

  @override
  State<CheckoutFlow> createState() => _CheckoutFlowState();
}

class _CheckoutFlowState extends State<CheckoutFlow> {
  Timer? _idleTimer;
  Timer? _warnTimer;
  Timer? _successTimer;
  bool _showWarning = false;

  CheckoutSession get session => widget.session;

  @override
  void initState() {
    super.initState();
    session.addListener(_onSession);
    _restartIdle();
  }

  @override
  void dispose() {
    session.removeListener(_onSession);
    _idleTimer?.cancel();
    _warnTimer?.cancel();
    _successTimer?.cancel();
    super.dispose();
  }

  bool get _tracksInactivity {
    return switch (session.phase) {
      CheckoutPhase.destinations ||
      CheckoutPhase.catalog ||
      CheckoutPhase.summary ||
      CheckoutPhase.failure => true,
      _ => false,
    };
  }

  void _onSession() {
    if (!mounted) {
      return;
    }
    if (session.phase == CheckoutPhase.success && _successTimer == null) {
      _successTimer = Timer(KioskTiming.successDwell, () {
        session.continueAfterSuccess();
      });
    }
    if (!_tracksInactivity) {
      _idleTimer?.cancel();
      _warnTimer?.cancel();
      _showWarning = false;
    } else {
      _restartIdle();
    }
    setState(() {});
  }

  void _restartIdle() {
    if (!_tracksInactivity) {
      return;
    }
    _idleTimer?.cancel();
    _warnTimer?.cancel();
    _showWarning = false;
    _idleTimer = Timer(KioskTiming.sessionInactivity, _onIdleElapsed);
  }

  void _onIdleElapsed() {
    setState(() => _showWarning = true);
    _warnTimer = Timer(KioskTiming.sessionWarning, () {
      unawaited(session.cancelToIdle());
    });
  }

  @override
  Widget build(BuildContext context) {
    final child = switch (session.phase) {
      CheckoutPhase.destinations => DestinationBrowseScreen(
        destinations: session.destinations,
        onSelect: (destination) => session.selectDestination(destination),
        onBack: session.cancelToIdle,
      ),
      CheckoutPhase.catalog => TicketSelectScreen(
        destinationName: session.destination?.name ?? KioskStrings.productName,
        ticketTypes: session.ticketTypes,
        quantities: session.quantities,
        onQuantity: session.setQuantity,
        onContinue: session.goToSummary,
        onBack: session.cancelToIdle,
        canContinue: session.hasCart,
        errorMessage: session.errorMessage,
      ),
      CheckoutPhase.summary => session.quote == null
          ? const TicketIssuingScreen()
          : OrderSummaryScreen(
              quote: session.quote!,
              destinationName: session.destination?.name ?? '',
              onPay: session.pay,
              onBack: session.backToCatalog,
              errorMessage: session.errorMessage,
            ),
      CheckoutPhase.payment || CheckoutPhase.processing =>
        session.nextAction?.qrContent != null
            ? PaymentScreen(
                amount: session.payment?.amount ??
                    session.quote?.grandTotal ??
                    session.order?.grandTotal ??
                    0,
                nextAction: session.nextAction,
                onCheckStatus: session.pollOnce,
                onCancel: session.cancelToIdle,
              )
            : PaymentProcessingScreen(onCheckStatus: session.pollOnce),
      CheckoutPhase.success => PaymentSuccessScreen(
        orderNumber: session.order?.orderNumber ?? '',
        ticketCount: session.tickets.length,
      ),
      CheckoutPhase.issuing => const TicketIssuingScreen(),
      CheckoutPhase.printing => PrintingScreen(
        index: session.printIndex,
        total: session.tickets.length,
      ),
      CheckoutPhase.printSuccess => PrintSuccessScreen(
        onDone: session.cancelToIdle,
      ),
      CheckoutPhase.printFailed => PrinterErrorScreen(
        tickets: session.tickets,
        onDone: session.cancelToIdle,
      ),
      CheckoutPhase.qr => QrDisplayScreen(
        tickets: session.tickets,
        onDone: session.cancelToIdle,
      ),
      CheckoutPhase.failure => PaymentFailureScreen(
        onRetryPay: session.retryPayment,
        onChangeTickets: session.changeTickets,
        onCancel: session.cancelToIdle,
        message: session.errorMessage,
      ),
      CheckoutPhase.recovery => RecoveryScreen(
        orderNumber: session.order?.orderNumber,
      ),
      CheckoutPhase.unknownStatus => NetworkErrorScreen(
        onRetry: session.retryStatus,
        message: session.errorMessage ?? KioskErrorCopy.unknownPayment,
      ),
    };

    return Listener(
      onPointerDown: (_) {
        if (_showWarning) {
          _warnTimer?.cancel();
          _restartIdle();
        } else if (_tracksInactivity) {
          _restartIdle();
        }
      },
      child: Stack(
        fit: StackFit.expand,
        children: [
          child,
          if (session.busy)
            const ColoredBox(
              color: Color(0x66000000),
              child: Center(
                child: CircularProgressIndicator(color: Colors.white),
              ),
            ),
          if (_showWarning)
            SessionTimeoutModal(
              onContinue: () {
                _warnTimer?.cancel();
                _restartIdle();
                setState(() {});
              },
            ),
        ],
      ),
    );
  }
}
