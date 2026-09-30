import 'package:flutter_test/flutter_test.dart';
import 'package:wanderdesa_kiosk/core/errors/api_exception.dart';
import 'package:wanderdesa_kiosk/features/checkout/checkout_session.dart';
import 'package:wanderdesa_kiosk/network/commerce_api.dart';
import 'package:wanderdesa_kiosk/network/dto/destination_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/order_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/payment_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/print_payload_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/quote_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/ticket_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/ticket_type_dto.dart';
import 'package:wanderdesa_kiosk/printer/printer_port.dart';
import 'package:wanderdesa_kiosk/storage/memory_credential_store.dart';
import 'package:wanderdesa_kiosk/storage/recovery_store.dart';

class _FakeCommerce implements KioskCommerceApi {
  _FakeCommerce({
    List<PaymentDto>? polls,
  }) : polls = polls ?? [];

  int quoteTotal = 103000;
  List<PaymentDto> polls;
  int pollIndex = 0;
  Map<String, dynamic>? lastQuoteBody;
  Map<String, dynamic>? lastOrderBody;
  String? lastOrderKey;
  String? lastPaymentKey;
  final List<Map<String, String>> printAcks = [];
  Future<PaymentDto> Function()? onGetPayment;

  static const destination = DestinationDto(
    id: 1,
    code: 'WD',
    name: 'Desa Wisata',
    timezone: 'Asia/Jakarta',
    isActive: true,
  );

  static const adult = TicketTypeDto(
    id: 10,
    destinationId: 1,
    code: 'ADULT',
    name: 'Dewasa',
    unitPrice: 50000,
    taxAmount: 0,
    serviceFeeAmount: 1500,
    maxPerOrder: 10,
    isActive: true,
  );

  @override
  Future<List<DestinationDto>> destinations() async => [destination];

  @override
  Future<List<TicketTypeDto>> ticketTypes(int destinationId) async => [adult];

  @override
  Future<QuoteDto> quote({
    required int destinationId,
    required List<CartItemInput> items,
  }) async {
    lastQuoteBody = {
      'destination_id': destinationId,
      'items': items.map((item) => item.toJson()).toList(),
    };
    final qty = items.fold<int>(0, (sum, item) => sum + item.quantity);
    return QuoteDto(
      currency: 'IDR',
      items: [
        QuoteLineDto(
          ticketTypeId: adult.id,
          ticketTypeCode: adult.code,
          ticketTypeName: adult.name,
          quantity: qty,
          unitPrice: adult.unitPrice,
          lineSubtotal: adult.unitPrice * qty,
          lineTaxTotal: 0,
          lineServiceFeeTotal: adult.serviceFeeAmount * qty,
          lineGrandTotal: quoteTotal,
        ),
      ],
      subtotal: adult.unitPrice * qty,
      discountTotal: 0,
      taxTotal: 0,
      serviceFeeTotal: adult.serviceFeeAmount * qty,
      grandTotal: quoteTotal,
    );
  }

  @override
  Future<OrderDto> createOrder({
    required int destinationId,
    required List<CartItemInput> items,
    required String idempotencyKey,
  }) async {
    lastOrderBody = {
      'destination_id': destinationId,
      'items': items.map((item) => item.toJson()).toList(),
    };
    lastOrderKey = idempotencyKey;
    return OrderDto(
      id: 99,
      orderNumber: 'WD-1',
      destinationId: destinationId,
      status: 'pending_payment',
      currency: 'IDR',
      subtotal: 100000,
      discountTotal: 0,
      taxTotal: 0,
      serviceFeeTotal: 3000,
      grandTotal: quoteTotal,
    );
  }

  @override
  Future<OrderDto> getOrder(int orderId) async {
    return OrderDto(
      id: orderId,
      orderNumber: 'WD-1',
      destinationId: 1,
      status: 'pending_payment',
      currency: 'IDR',
      subtotal: 100000,
      discountTotal: 0,
      taxTotal: 0,
      serviceFeeTotal: 3000,
      grandTotal: quoteTotal,
    );
  }

  @override
  Future<OrderDto> cancelOrder(int orderId, {String? reason}) async {
    return OrderDto(
      id: orderId,
      orderNumber: 'WD-1',
      destinationId: 1,
      status: 'cancelled',
      currency: 'IDR',
      subtotal: 0,
      discountTotal: 0,
      taxTotal: 0,
      serviceFeeTotal: 0,
      grandTotal: 0,
    );
  }

  @override
  Future<PaymentInitiationDto> initiatePayment({
    required int orderId,
    required String idempotencyKey,
  }) async {
    lastPaymentKey = idempotencyKey;
    return const PaymentInitiationDto(
      payment: PaymentDto(
        id: 7,
        paymentNumber: 'PAY-7',
        status: 'processing',
        method: 'digital',
        amount: 103000,
        currency: 'IDR',
        ticketsIssued: false,
        orderId: 99,
      ),
      nextAction: NextActionDto(
        type: 'display_qr',
        qrContent: 'wanderdesa-sandbox:PAY-7',
      ),
    );
  }

  @override
  Future<PaymentDto> getPayment(int paymentId) async {
    if (onGetPayment != null) {
      return onGetPayment!();
    }
    if (pollIndex >= polls.length) {
      return polls.isEmpty
          ? const PaymentDto(
              id: 7,
              paymentNumber: 'PAY-7',
              status: 'processing',
              method: 'digital',
              amount: 103000,
              currency: 'IDR',
              ticketsIssued: false,
            )
          : polls.last;
    }
    return polls[pollIndex++];
  }

  @override
  Future<PaymentInitiationDto> refreshPayment(int paymentId) async {
    final latest = await getPayment(paymentId);
    return PaymentInitiationDto(payment: latest);
  }

  @override
  Future<List<TicketDto>> orderTickets(int orderId) async {
    return const [
      TicketDto(
        id: 1,
        ticketCode: 'T-1',
        status: 'active',
        qrPayload: 'qr-one',
      ),
      TicketDto(
        id: 2,
        ticketCode: 'T-2',
        status: 'active',
        qrPayload: 'qr-two',
      ),
    ];
  }

  @override
  Future<PrintPayloadDto> printPayload(String ticketCode) async {
    return PrintPayloadDto(
      ticketCode: ticketCode,
      qrPayload: 'qr-$ticketCode',
      status: 'active',
      destinationName: destination.name,
      ticketTypeName: adult.name,
    );
  }

  @override
  Future<void> printAck({
    required String ticketCode,
    required String result,
    String? message,
  }) async {
    printAcks.add({
      'ticket_code': ticketCode,
      'result': result,
      'message': ?message,
    });
  }
}

CheckoutSession _sessionOf(
  _FakeCommerce api, {
  TicketPrinter? printer,
  bool printEnabled = true,
}) {
  return CheckoutSession(
    commerce: api,
    recovery: RecoveryStore(MemoryCredentialStore()),
    autoPoll: false,
    printEnabled: printEnabled,
    printer: printer,
  );
}

void main() {
  const bound = _FakeCommerce.destination;

  test('quote totals come from server and cart change rotates order key', () async {
    final api = _FakeCommerce();
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    final firstKey = session.orderIdempotencyKey;
    await session.goToSummary();
    expect(session.quote?.grandTotal, 103000);
    expect(lastHasNoMoney(api.lastQuoteBody!), isTrue);
    session.setQuantity(10, 1);
    expect(session.orderIdempotencyKey, isNot(firstKey));
    session.dispose();
  });

  test('poll processing does not mark success', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'processing',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: false,
        ),
      ],
    );
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    expect(session.phase, CheckoutPhase.processing);
    expect(session.successFromServer, isFalse);
    await session.pollOnce();
    expect(session.phase, CheckoutPhase.processing);
    expect(session.successFromServer, isFalse);
    session.dispose();
  });

  test('poll paid with tickets_issued becomes success', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'paid',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: true,
          orderStatus: 'paid',
        ),
      ],
    );
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    expect(session.phase, CheckoutPhase.success);
    expect(session.successFromServer, isTrue);
    expect(session.tickets, hasLength(2));
    expect(lastHasNoMoney(api.lastOrderBody!), isTrue);
    session.dispose();
  });

  test('poll failed becomes failure not paid', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'failed',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: false,
        ),
      ],
    );
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    expect(session.phase, CheckoutPhase.failure);
    expect(session.successFromServer, isFalse);
    session.dispose();
  });

  test('network during poll is unknown status, never local PAID', () async {
    final api = _FakeCommerce();
    api.onGetPayment = () async {
      throw const ApiException(
        code: 'network.unreachable',
        message: 'down',
        statusCode: 0,
      );
    };
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    expect(session.phase, CheckoutPhase.unknownStatus);
    expect(session.successFromServer, isFalse);
    session.dispose();
  });

  test('recovery asks Laravel and does not invent PAID', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'processing',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: false,
        ),
      ],
    );
    final session = _sessionOf(api);
    await session.recover(
      const CheckoutSnapshot(
        orderId: 99,
        paymentId: 7,
        orderIdempotencyKey: 'kiosk_order_keep',
        paymentIdempotencyKey: 'kiosk_pay_keep',
      ),
    );
    expect(session.phase, CheckoutPhase.processing);
    expect(session.successFromServer, isFalse);
    expect(session.orderIdempotencyKey, 'kiosk_order_keep');
    expect(api.lastPaymentKey, 'kiosk_pay_keep');
    expect(session.nextAction?.qrContent, isNotEmpty);
    session.dispose();
  });

  test('unavailable printer after PAID shows QR and keeps tickets', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'paid',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: true,
          orderStatus: 'paid',
        ),
      ],
    );
    final session = _sessionOf(api, printer: const UnavailableTicketPrinter());
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    expect(session.successFromServer, isTrue);
    await session.printIssuedTickets();
    expect(session.phase, CheckoutPhase.qr);
    expect(session.tickets, hasLength(2));
    expect(api.printAcks, isEmpty);
    session.dispose();
  });

  test('mock printer success acks Laravel without minting local tickets', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'paid',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: true,
          orderStatus: 'paid',
        ),
      ],
    );
    final printer = MockTicketPrinter();
    final session = _sessionOf(api, printer: printer);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    await session.printIssuedTickets();
    expect(session.phase, CheckoutPhase.printSuccess);
    expect(session.successFromServer, isTrue);
    expect(session.tickets, hasLength(2));
    expect(printer.printed, hasLength(2));
    expect(api.printAcks.map((row) => row['result']), everyElement('success'));
    session.dispose();
  });

  test('printer failure keeps PAID tickets and shows printFailed', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'paid',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: true,
          orderStatus: 'paid',
        ),
      ],
    );
    final session = _sessionOf(api, printer: MockTicketPrinter(fail: true));
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    await session.printIssuedTickets();
    expect(session.phase, CheckoutPhase.printFailed);
    expect(session.successFromServer, isTrue);
    expect(session.tickets, hasLength(2));
    expect(api.printAcks.map((row) => row['result']), everyElement('failed'));
    session.dispose();
  });

  test('partial multi-ticket print failure still keeps tickets', () async {
    final api = _FakeCommerce(
      polls: const [
        PaymentDto(
          id: 7,
          paymentNumber: 'PAY-7',
          status: 'paid',
          method: 'digital',
          amount: 103000,
          currency: 'IDR',
          ticketsIssued: true,
          orderStatus: 'paid',
        ),
      ],
    );
    final session = _sessionOf(
      api,
      printer: PartialFailTicketPrinter(failAfter: 1),
    );
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    await session.printIssuedTickets();
    expect(session.phase, CheckoutPhase.printFailed);
    expect(session.tickets, hasLength(2));
    expect(api.printAcks, hasLength(2));
    expect(api.printAcks.first['result'], 'success');
    expect(api.printAcks.last['result'], 'failed');
    session.dispose();
  });

  test('retryPayment rotates payment key and keeps order key', () async {
    final api = _FakeCommerce();
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    final orderKey = session.orderIdempotencyKey;
    final payKey = api.lastPaymentKey;
    await session.retryPayment();
    expect(session.orderIdempotencyKey, orderKey);
    expect(api.lastPaymentKey, isNot(payKey));
    expect(session.successFromServer, isFalse);
    expect(session.phase, isNot(CheckoutPhase.success));
    session.dispose();
  });

  test('retryStatus after unknown poll never invents PAID', () async {
    final api = _FakeCommerce();
    var polls = 0;
    api.onGetPayment = () async {
      polls++;
      if (polls == 1) {
        throw const ApiException(
          code: 'network.unreachable',
          message: 'down',
          statusCode: 0,
        );
      }
      return const PaymentDto(
        id: 7,
        paymentNumber: 'PAY-7',
        status: 'processing',
        method: 'digital',
        amount: 103000,
        currency: 'IDR',
        ticketsIssued: false,
      );
    };
    final session = _sessionOf(api);
    await session.start(bound: bound);
    session.setQuantity(10, 2);
    await session.goToSummary();
    await session.pay();
    await session.pollOnce();
    expect(session.phase, CheckoutPhase.unknownStatus);
    expect(session.successFromServer, isFalse);
    await session.retryStatus();
    expect(session.phase, CheckoutPhase.processing);
    expect(session.successFromServer, isFalse);
    session.dispose();
  });
}

bool lastHasNoMoney(Map<String, dynamic> body) {
  final encoded = body.toString();
  return !encoded.contains('unit_price') &&
      !encoded.contains('grand_total') &&
      !encoded.contains('amount');
}
