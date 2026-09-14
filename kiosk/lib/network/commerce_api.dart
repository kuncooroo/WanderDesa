import 'dto/destination_dto.dart';
import 'dto/order_dto.dart';
import 'dto/payment_dto.dart';
import 'dto/print_payload_dto.dart';
import 'dto/quote_dto.dart';
import 'dto/ticket_dto.dart';
import 'dto/ticket_type_dto.dart';

abstract class KioskCommerceApi {
  Future<List<DestinationDto>> destinations();

  Future<List<TicketTypeDto>> ticketTypes(int destinationId);

  Future<QuoteDto> quote({
    required int destinationId,
    required List<CartItemInput> items,
  });

  Future<OrderDto> createOrder({
    required int destinationId,
    required List<CartItemInput> items,
    required String idempotencyKey,
  });

  Future<OrderDto> getOrder(int orderId);

  Future<OrderDto> cancelOrder(int orderId, {String? reason});

  Future<PaymentInitiationDto> initiatePayment({
    required int orderId,
    required String idempotencyKey,
  });

  Future<PaymentDto> getPayment(int paymentId);

  Future<List<TicketDto>> orderTickets(int orderId);

  Future<PrintPayloadDto> printPayload(String ticketCode);

  Future<void> printAck({
    required String ticketCode,
    required String result,
    String? message,
  });
}
