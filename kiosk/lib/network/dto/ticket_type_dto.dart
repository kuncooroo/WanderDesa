import '../../core/utils/json_numbers.dart';

class TicketTypeDto {
  const TicketTypeDto({
    required this.id,
    required this.destinationId,
    required this.code,
    required this.name,
    required this.unitPrice,
    required this.taxAmount,
    required this.serviceFeeAmount,
    required this.maxPerOrder,
    required this.isActive,
    this.description,
    this.currency = 'IDR',
    this.validityType,
    this.validityDays,
  });

  factory TicketTypeDto.fromJson(Map<String, dynamic> json) {
    return TicketTypeDto(
      id: jsonInt(json['id']),
      destinationId: jsonInt(json['destination_id']),
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      description: json['description'] as String?,
      currency: json['currency'] as String? ?? 'IDR',
      unitPrice: jsonInt(json['unit_price']),
      taxAmount: jsonInt(json['tax_amount']),
      serviceFeeAmount: jsonInt(json['service_fee_amount']),
      validityType: json['validity_type'] as String?,
      validityDays: json['validity_days'] == null
          ? null
          : jsonInt(json['validity_days']),
      maxPerOrder: jsonInt(json['max_per_order'], fallback: 20),
      isActive: jsonBool(json['is_active']),
    );
  }

  final int id;
  final int destinationId;
  final String code;
  final String name;
  final String? description;
  final String currency;
  final int unitPrice;
  final int taxAmount;
  final int serviceFeeAmount;
  final String? validityType;
  final int? validityDays;
  final int maxPerOrder;
  final bool isActive;
}
