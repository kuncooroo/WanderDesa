import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../core/constants/kiosk_timing.dart';
import '../../core/errors/api_exception.dart';
import '../../core/errors/error_mapper.dart';
import '../../core/utils/idempotency_key.dart';
import '../../network/commerce_api.dart';
import '../../network/dto/destination_dto.dart';
import '../../network/dto/order_dto.dart';
import '../../network/dto/payment_dto.dart';
import '../../network/dto/quote_dto.dart';
import '../../network/dto/ticket_dto.dart';
import '../../network/dto/ticket_type_dto.dart';
import '../../printer/printer_port.dart';
import '../../storage/recovery_store.dart';

enum CheckoutPhase {
  destinations,
  catalog,
  summary,
  payment,
  processing,
  success,
  issuing,
  printing,
  printSuccess,
  printFailed,
  qr,
  failure,
  recovery,
  unknownStatus,
}

/// UI/session only. Laravel owns quote, order, payment, and tickets.
class CheckoutSession extends ChangeNotifier {
  CheckoutSession({
    required KioskCommerceApi commerce,
    required RecoveryStore recovery,
    this.autoPoll = true,
    this.pollInterval = KioskTiming.paymentPollInterval,
    this.printEnabled = true,
    this.printer,
    this.onPrinterHealth,
    this.onFinished,
  }) : _commerce = commerce,
       _recovery = recovery;

  final KioskCommerceApi _commerce;
  final RecoveryStore _recovery;
  final bool autoPoll;
  final Duration pollInterval;
  final bool printEnabled;
  final TicketPrinter? printer;
  final void Function(bool ok)? onPrinterHealth;
  final VoidCallback? onFinished;

  CheckoutPhase phase = CheckoutPhase.catalog;
  bool busy = false;
  String? errorMessage;
  DestinationDto? destination;
  List<DestinationDto> destinations = const [];
  List<TicketTypeDto> ticketTypes = const [];
  final Map<int, int> quantities = {};
  QuoteDto? quote;
  OrderDto? order;
  PaymentDto? payment;
  NextActionDto? nextAction;
  List<TicketDto> tickets = const [];
  int printIndex = 0;
  String orderIdempotencyKey = newIdempotencyKey('order');
  String paymentIdempotencyKey = newIdempotencyKey('pay');
  Timer? _pollTimer;

  bool get hasCart => quantities.values.any((qty) => qty > 0);

  bool get isInFlightPayment =>
      phase == CheckoutPhase.processing ||
      phase == CheckoutPhase.unknownStatus ||
      phase == CheckoutPhase.recovery;

  bool get locksShellOnMaintenance =>
      isInFlightPayment ||
      phase == CheckoutPhase.qr ||
      phase == CheckoutPhase.printing ||
      phase == CheckoutPhase.printSuccess ||
      phase == CheckoutPhase.printFailed;

  bool get successFromServer {
    final current = payment;
    return current != null && current.isPaid && (current.ticketsIssued || tickets.isNotEmpty);
  }

  List<CartItemInput> get cartItems {
    return quantities.entries
        .where((entry) => entry.value > 0)
        .map(
          (entry) => CartItemInput(
            ticketTypeId: entry.key,
            quantity: entry.value,
          ),
        )
        .toList();
  }

  Future<void> start({DestinationDto? bound}) async {
    busy = true;
    errorMessage = null;
    notifyListeners();
    try {
      if (bound != null) {
        destination = bound;
        await _loadCatalog(bound.id);
        phase = CheckoutPhase.catalog;
      } else {
        destinations = await _commerce.destinations();
        if (destinations.length == 1) {
          destination = destinations.first;
          await _loadCatalog(destination!.id);
          phase = CheckoutPhase.catalog;
        } else {
          phase = CheckoutPhase.destinations;
        }
      }
    } on ApiException catch (error) {
      errorMessage = ErrorMapper.toUserMessage(error);
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> selectDestination(DestinationDto selected) async {
    errorMessage = null;
    try {
      destination = selected;
      await _loadCatalog(selected.id);
      phase = CheckoutPhase.catalog;
    } on ApiException catch (error) {
      errorMessage = ErrorMapper.toUserMessage(error);
    }
    notifyListeners();
  }

  Future<void> _loadCatalog(int destinationId) async {
    busy = true;
    notifyListeners();
    ticketTypes = await _commerce.ticketTypes(destinationId);
    quantities
      ..clear()
      ..addEntries(ticketTypes.map((type) => MapEntry(type.id, 0)));
    _rotateOrderKey();
    quote = null;
    busy = false;
  }

  void setQuantity(int ticketTypeId, int quantity) {
    final type = ticketTypes.where((item) => item.id == ticketTypeId).firstOrNull;
    final max = type?.maxPerOrder ?? 20;
    final next = quantity.clamp(0, max);
    if (quantities[ticketTypeId] == next) {
      return;
    }
    quantities[ticketTypeId] = next;
    quote = null;
    _rotateOrderKey();
    notifyListeners();
  }

  Future<void> goToSummary() async {
    if (!hasCart || destination == null) {
      return;
    }
    busy = true;
    errorMessage = null;
    notifyListeners();
    try {
      quote = await _commerce.quote(
        destinationId: destination!.id,
        items: cartItems,
      );
      phase = CheckoutPhase.summary;
    } on ApiException catch (error) {
      errorMessage = ErrorMapper.toUserMessage(error);
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  void backToCatalog() {
    if (order != null && order!.isPendingPayment) {
      return;
    }
    phase = CheckoutPhase.catalog;
    errorMessage = null;
    notifyListeners();
  }

  Future<void> pay() async {
    if (destination == null || !hasCart) {
      return;
    }
    busy = true;
    errorMessage = null;
    phase = CheckoutPhase.payment;
    notifyListeners();
    try {
      quote ??= await _commerce.quote(
        destinationId: destination!.id,
        items: cartItems,
      );
      order ??= await _commerce.createOrder(
        destinationId: destination!.id,
        items: cartItems,
        idempotencyKey: orderIdempotencyKey,
      );
      await _persistRecovery();
      final initiation = await _commerce.initiatePayment(
        orderId: order!.id,
        idempotencyKey: paymentIdempotencyKey,
      );
      payment = initiation.payment;
      nextAction = initiation.nextAction;
      await _persistRecovery();
      await _applyPayment(payment!);
    } on ApiException catch (error) {
      busy = false;
      if (error.isNetwork) {
        phase = CheckoutPhase.unknownStatus;
        errorMessage = KioskErrorCopy.unknownPayment;
      } else {
        phase = CheckoutPhase.failure;
        errorMessage = ErrorMapper.toUserMessage(error);
      }
      notifyListeners();
    }
  }

  Future<void> pollOnce() async {
    final paymentId = payment?.id;
    if (paymentId == null) {
      return;
    }
    try {
      final latest = await _commerce.getPayment(paymentId);
      payment = latest;
      await _applyPayment(latest);
    } on ApiException catch (error) {
      _stopPoll();
      if (error.isNetwork) {
        phase = CheckoutPhase.unknownStatus;
        errorMessage = KioskErrorCopy.unknownPayment;
      } else {
        phase = CheckoutPhase.failure;
        errorMessage = ErrorMapper.toUserMessage(error);
      }
      notifyListeners();
    }
  }

  Future<void> retryStatus() async {
    if (payment != null) {
      phase = CheckoutPhase.processing;
      notifyListeners();
      await pollOnce();
      return;
    }
    await pay();
  }

  Future<void> retryPayment() async {
    paymentIdempotencyKey = newIdempotencyKey('pay');
    payment = null;
    nextAction = null;
    await pay();
  }

  Future<void> changeTickets() async {
    _stopPoll();
    await _cancelUnpaidQuietly();
    await _recovery.clear();
    order = null;
    payment = null;
    nextAction = null;
    tickets = const [];
    _rotateOrderKey();
    paymentIdempotencyKey = newIdempotencyKey('pay');
    phase = CheckoutPhase.catalog;
    errorMessage = null;
    notifyListeners();
  }

  Future<void> cancelToIdle() async {
    _stopPoll();
    await _cancelUnpaidQuietly();
    await _recovery.clear();
    onFinished?.call();
  }

  void continueAfterSuccess() {
    if (tickets.isEmpty) {
      phase = CheckoutPhase.issuing;
      notifyListeners();
      unawaited(_loadTickets().then((_) => printIssuedTickets()));
      return;
    }
    unawaited(printIssuedTickets());
  }

  Future<void> printIssuedTickets() async {
    if (tickets.isEmpty) {
      if (phase == CheckoutPhase.unknownStatus) {
        return;
      }
      phase = CheckoutPhase.issuing;
      notifyListeners();
      return;
    }

    final adapter = printer;
    if (!printEnabled || adapter == null || !adapter.isAvailable) {
      onPrinterHealth?.call(adapter?.lastOk ?? false);
      phase = CheckoutPhase.qr;
      notifyListeners();
      return;
    }

    phase = CheckoutPhase.printing;
    printIndex = 0;
    errorMessage = null;
    notifyListeners();

    var anyFailed = false;
    for (var i = 0; i < tickets.length; i++) {
      printIndex = i;
      notifyListeners();
      final ticket = tickets[i];
      var printedOk = false;
      String? failMessage;
      try {
        final payload = await _commerce.printPayload(ticket.ticketCode);
        final result = await adapter.printPayload(payload);
        printedOk = result.ok;
        failMessage = result.message;
        onPrinterHealth?.call(result.ok);
      } on ApiException catch (error) {
        anyFailed = true;
        failMessage = ErrorMapper.toUserMessage(error);
        onPrinterHealth?.call(false);
        await _ackPrint(
          ticket.ticketCode,
          success: false,
          message: error.code,
        );
        continue;
      }

      await _ackPrint(
        ticket.ticketCode,
        success: printedOk,
        message: failMessage,
      );
      if (!printedOk) {
        anyFailed = true;
      }
    }

    phase = anyFailed ? CheckoutPhase.printFailed : CheckoutPhase.printSuccess;
    if (anyFailed) {
      errorMessage = KioskErrorCopy.printFailed;
    }
    notifyListeners();
  }

  Future<void> _ackPrint(
    String ticketCode, {
    required bool success,
    String? message,
  }) async {
    try {
      await _commerce.printAck(
        ticketCode: ticketCode,
        result: success ? 'success' : 'failed',
        message: message,
      );
    } on ApiException {
      // Ledger already ISSUED; missing ack must not void tickets.
    }
  }

  Future<void> refreshTickets() => _loadTickets();

  Future<void> recover(CheckoutSnapshot snapshot) async {
    phase = CheckoutPhase.recovery;
    busy = true;
    errorMessage = null;
    orderIdempotencyKey =
        snapshot.orderIdempotencyKey ?? orderIdempotencyKey;
    paymentIdempotencyKey =
        snapshot.paymentIdempotencyKey ?? paymentIdempotencyKey;
    notifyListeners();

    try {
      if (snapshot.orderId != null) {
        order = await _commerce.getOrder(snapshot.orderId!);
      }
      if (snapshot.paymentId != null) {
        payment = await _commerce.getPayment(snapshot.paymentId!);
      }
      if (payment != null &&
          payment!.isProcessing &&
          order != null &&
          order!.isPendingPayment) {
        try {
          final initiation = await _commerce.initiatePayment(
            orderId: order!.id,
            idempotencyKey: paymentIdempotencyKey,
          );
          payment = initiation.payment;
          nextAction = initiation.nextAction;
        } on ApiException {
          // Poll still works; QR may be missing until the operator retries.
        }
      }
      if (payment != null) {
        await _applyPayment(payment!);
      } else if (order != null && order!.isPaid) {
        await _loadTickets();
        phase = tickets.isEmpty ? CheckoutPhase.issuing : CheckoutPhase.success;
      } else if (order != null && order!.isPendingPayment) {
        phase = CheckoutPhase.summary;
        quote = QuoteDto(
          currency: order!.currency,
          items: order!.items,
          subtotal: order!.subtotal,
          discountTotal: order!.discountTotal,
          taxTotal: order!.taxTotal,
          serviceFeeTotal: order!.serviceFeeTotal,
          grandTotal: order!.grandTotal,
        );
      } else {
        await _recovery.clear();
        onFinished?.call();
      }
    } on ApiException catch (error) {
      if (error.isNetwork) {
        phase = CheckoutPhase.unknownStatus;
        errorMessage = KioskErrorCopy.unknownPayment;
      } else {
        phase = CheckoutPhase.failure;
        errorMessage = ErrorMapper.toUserMessage(error);
      }
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> _applyPayment(PaymentDto latest) async {
    busy = false;
    if (latest.isSuccess || (latest.isPaid && tickets.isNotEmpty)) {
      _stopPoll();
      if (tickets.isEmpty) {
        await _loadTickets();
      }
      await _recovery.clear();
      phase = CheckoutPhase.success;
      notifyListeners();
      return;
    }
    if (latest.isPaid && !latest.ticketsIssued) {
      phase = CheckoutPhase.issuing;
      notifyListeners();
      await _loadTickets();
      if (tickets.isEmpty) {
        _startPoll();
      } else {
        await _recovery.clear();
        phase = CheckoutPhase.success;
        notifyListeners();
      }
      return;
    }
    if (latest.isFailed) {
      _stopPoll();
      phase = CheckoutPhase.failure;
      errorMessage = KioskErrorCopy.paymentFailed;
      notifyListeners();
      return;
    }
    phase = CheckoutPhase.processing;
    notifyListeners();
    _startPoll();
  }

  Future<void> _loadTickets() async {
    final orderId = order?.id ?? payment?.orderId;
    if (orderId == null) {
      return;
    }
    try {
      tickets = await _commerce.orderTickets(orderId);
      if (tickets.isNotEmpty) {
        await _recovery.clear();
      }
    } on ApiException catch (error) {
      if (error.isNetwork) {
        phase = CheckoutPhase.unknownStatus;
        errorMessage = KioskErrorCopy.unknownPayment;
      } else {
        errorMessage = ErrorMapper.toUserMessage(error);
      }
    }
    notifyListeners();
  }

  void _startPoll() {
    if (!autoPoll || _pollTimer != null) {
      return;
    }
    _pollTimer = Timer.periodic(pollInterval, (_) {
      unawaited(pollOnce());
    });
  }

  void _stopPoll() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  void _rotateOrderKey() {
    orderIdempotencyKey = newIdempotencyKey('order');
  }

  Future<void> _persistRecovery() async {
    final snapshot = CheckoutSnapshot(
      orderId: order?.id,
      orderNumber: order?.orderNumber,
      paymentId: payment?.id,
      orderIdempotencyKey: orderIdempotencyKey,
      paymentIdempotencyKey: paymentIdempotencyKey,
      destinationId: destination?.id ?? order?.destinationId,
    );
    if (!snapshot.hasServerWork) {
      return;
    }
    await _recovery.write(snapshot);
  }

  Future<void> _cancelUnpaidQuietly() async {
    final current = order;
    if (current == null || !current.isPendingPayment) {
      return;
    }
    try {
      await _commerce.cancelOrder(current.id, reason: 'kiosk_session_end');
    } on ApiException {
      // Idle path must not invent success; ignore cancel transport errors.
    }
  }

  @override
  void dispose() {
    _stopPoll();
    super.dispose();
  }
}
