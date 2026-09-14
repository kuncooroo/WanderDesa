import '../../core/utils/json_numbers.dart';

class CartItemInput {
  const CartItemInput({
    required this.ticketTypeId,
    required this.quantity,
    this.visitDate,
  });

  final int ticketTypeId;
  final int quantity;
  final String? visitDate;

  Map<String, dynamic> toJson() {
    return {
      'ticket_type_id': ticketTypeId,
      'quantity': quantity,
      if (visitDate != null) 'visit_date': visitDate,
    };
  }
}

class QuoteLineDto {
  const QuoteLineDto({
    required this.ticketTypeId,
    required this.ticketTypeCode,
    required this.ticketTypeName,
    required this.quantity,
    required this.unitPrice,
    required this.lineSubtotal,
    required this.lineTaxTotal,
    required this.lineServiceFeeTotal,
    required this.lineGrandTotal,
    this.visitDate,
  });

  factory QuoteLineDto.fromJson(Map<String, dynamic> json) {
    return QuoteLineDto(
      ticketTypeId: jsonInt(json['ticket_type_id']),
      ticketTypeCode: json['ticket_type_code'] as String? ?? '',
      ticketTypeName: json['ticket_type_name'] as String? ?? '',
      quantity: jsonInt(json['quantity']),
      visitDate: json['visit_date'] as String?,
      unitPrice: jsonInt(json['unit_price']),
      lineSubtotal: jsonInt(json['line_subtotal']),
      lineTaxTotal: jsonInt(json['line_tax_total']),
      lineServiceFeeTotal: jsonInt(json['line_service_fee_total']),
      lineGrandTotal: jsonInt(json['line_grand_total']),
    );
  }

  final int ticketTypeId;
  final String ticketTypeCode;
  final String ticketTypeName;
  final int quantity;
  final String? visitDate;
  final int unitPrice;
  final int lineSubtotal;
  final int lineTaxTotal;
  final int lineServiceFeeTotal;
  final int lineGrandTotal;
}

class QuoteDto {
  const QuoteDto({
    required this.currency,
    required this.items,
    required this.subtotal,
    required this.discountTotal,
    required this.taxTotal,
    required this.serviceFeeTotal,
    required this.grandTotal,
  });

  factory QuoteDto.fromJson(Map<String, dynamic> json) {
    final items = json['items'];
    return QuoteDto(
      currency: json['currency'] as String? ?? 'IDR',
      items: items is List
          ? items
                .whereType<Map>()
                .map((row) => QuoteLineDto.fromJson(Map<String, dynamic>.from(row)))
                .toList()
          : const [],
      subtotal: jsonInt(json['subtotal']),
      discountTotal: jsonInt(json['discount_total']),
      taxTotal: jsonInt(json['tax_total']),
      serviceFeeTotal: jsonInt(json['service_fee_total']),
      grandTotal: jsonInt(json['grand_total']),
    );
  }

  final String currency;
  final List<QuoteLineDto> items;
  final int subtotal;
  final int discountTotal;
  final int taxTotal;
  final int serviceFeeTotal;
  final int grandTotal;
}
