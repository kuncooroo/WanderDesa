import '../../core/utils/json_numbers.dart';
import 'quote_dto.dart';

class OrderDto {
  const OrderDto({
    required this.id,
    required this.orderNumber,
    required this.destinationId,
    required this.status,
    required this.currency,
    required this.subtotal,
    required this.discountTotal,
    required this.taxTotal,
    required this.serviceFeeTotal,
    required this.grandTotal,
    this.expiresAt,
    this.items = const [],
  });

  factory OrderDto.fromJson(Map<String, dynamic> json) {
    final items = json['items'];
    return OrderDto(
      id: jsonInt(json['id']),
      orderNumber: json['order_number'] as String? ?? '',
      destinationId: jsonInt(json['destination_id']),
      status: json['status'] as String? ?? '',
      currency: json['currency'] as String? ?? 'IDR',
      subtotal: jsonInt(json['subtotal']),
      discountTotal: jsonInt(json['discount_total']),
      taxTotal: jsonInt(json['tax_total']),
      serviceFeeTotal: jsonInt(json['service_fee_total']),
      grandTotal: jsonInt(json['grand_total']),
      expiresAt: json['expires_at'] as String?,
      items: items is List
          ? items
                .whereType<Map>()
                .map((row) => QuoteLineDto.fromJson(Map<String, dynamic>.from(row)))
                .toList()
          : const [],
    );
  }

  final int id;
  final String orderNumber;
  final int destinationId;
  final String status;
  final String currency;
  final int subtotal;
  final int discountTotal;
  final int taxTotal;
  final int serviceFeeTotal;
  final int grandTotal;
  final String? expiresAt;
  final List<QuoteLineDto> items;

  bool get isPendingPayment => status == 'pending_payment';

  bool get isPaid => status == 'paid';

  bool get isTerminalUnpaid =>
      status == 'cancelled' || status == 'expired';
}
