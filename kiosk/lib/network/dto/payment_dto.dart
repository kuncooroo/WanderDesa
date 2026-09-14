import '../../core/utils/json_numbers.dart';

class NextActionDto {
  const NextActionDto({
    required this.type,
    this.qrContent,
    this.expiresAt,
  });

  factory NextActionDto.fromJson(Map<String, dynamic> json) {
    return NextActionDto(
      type: json['type'] as String? ?? '',
      qrContent: json['qr_content'] as String?,
      expiresAt: json['expires_at'] as String?,
    );
  }

  final String type;
  final String? qrContent;
  final String? expiresAt;

  bool get isDisplayQr => type == 'display_qr';
}

class PaymentDto {
  const PaymentDto({
    required this.id,
    required this.paymentNumber,
    required this.status,
    required this.method,
    required this.amount,
    required this.currency,
    required this.ticketsIssued,
    this.orderId,
    this.orderStatus,
    this.failedAt,
  });

  factory PaymentDto.fromJson(Map<String, dynamic> json) {
    return PaymentDto(
      id: jsonInt(json['id']),
      paymentNumber: json['payment_number'] as String? ?? '',
      orderId: json['order_id'] == null ? null : jsonInt(json['order_id']),
      status: json['status'] as String? ?? '',
      method: json['method'] as String? ?? '',
      amount: jsonInt(json['amount']),
      currency: json['currency'] as String? ?? 'IDR',
      orderStatus: json['order_status'] as String?,
      ticketsIssued: jsonBool(json['tickets_issued']),
      failedAt: json['failed_at'] as String?,
    );
  }

  final int id;
  final String paymentNumber;
  final int? orderId;
  final String status;
  final String method;
  final int amount;
  final String currency;
  final String? orderStatus;
  final bool ticketsIssued;
  final String? failedAt;

  bool get isPaid => status == 'paid';

  bool get isProcessing => status == 'processing' || status == 'pending';

  bool get isFailed =>
      status == 'failed' || status == 'expired' || status == 'cancelled';

  bool get isSuccess => isPaid && ticketsIssued;
}

class PaymentInitiationDto {
  const PaymentInitiationDto({
    required this.payment,
    this.nextAction,
  });

  factory PaymentInitiationDto.fromJson(Map<String, dynamic> json) {
    final payment = json['payment'];
    final next = json['next_action'];
    return PaymentInitiationDto(
      payment: PaymentDto.fromJson(
        payment is Map<String, dynamic>
            ? payment
            : Map<String, dynamic>.from(payment as Map),
      ),
      nextAction: next is Map<String, dynamic>
          ? NextActionDto.fromJson(next)
          : null,
    );
  }

  final PaymentDto payment;
  final NextActionDto? nextAction;
}
