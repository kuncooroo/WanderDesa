class PrintPayloadDto {
  const PrintPayloadDto({
    required this.ticketCode,
    required this.qrPayload,
    required this.status,
    this.destinationName,
    this.ticketTypeName,
    this.validStartAt,
    this.validEndAt,
    this.issuedAt,
  });

  factory PrintPayloadDto.fromJson(Map<String, dynamic> json) {
    final destination = json['destination'];
    final type = json['ticket_type'];
    return PrintPayloadDto(
      ticketCode: json['ticket_code'] as String? ?? '',
      qrPayload: json['qr_payload'] as String? ?? '',
      status: json['status'] as String? ?? '',
      destinationName: destination is Map
          ? destination['name'] as String?
          : null,
      ticketTypeName: type is Map ? type['name'] as String? : null,
      validStartAt: json['valid_start_at'] as String?,
      validEndAt: json['valid_end_at'] as String?,
      issuedAt: json['issued_at'] as String?,
    );
  }

  final String ticketCode;
  final String qrPayload;
  final String status;
  final String? destinationName;
  final String? ticketTypeName;
  final String? validStartAt;
  final String? validEndAt;
  final String? issuedAt;
}
