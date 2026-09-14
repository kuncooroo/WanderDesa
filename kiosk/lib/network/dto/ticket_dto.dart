import '../../core/utils/json_numbers.dart';

class TicketDto {
  const TicketDto({
    required this.id,
    required this.ticketCode,
    required this.status,
    this.orderId,
    this.qrPayload,
    this.validStartAt,
    this.validEndAt,
  });

  factory TicketDto.fromJson(Map<String, dynamic> json) {
    return TicketDto(
      id: jsonInt(json['id']),
      ticketCode: json['ticket_code'] as String? ?? '',
      orderId: json['order_id'] == null ? null : jsonInt(json['order_id']),
      status: json['status'] as String? ?? '',
      qrPayload: json['qr_payload'] as String?,
      validStartAt: json['valid_start_at'] as String?,
      validEndAt: json['valid_end_at'] as String?,
    );
  }

  final int id;
  final String ticketCode;
  final int? orderId;
  final String status;
  final String? qrPayload;
  final String? validStartAt;
  final String? validEndAt;
}
