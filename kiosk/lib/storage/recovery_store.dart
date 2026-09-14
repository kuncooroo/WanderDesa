import 'dart:convert';

import 'credential_store.dart';

import '../core/utils/json_numbers.dart';

class CheckoutSnapshot {
  const CheckoutSnapshot({
    this.orderId,
    this.orderNumber,
    this.paymentId,
    this.orderIdempotencyKey,
    this.paymentIdempotencyKey,
    this.destinationId,
  });

  factory CheckoutSnapshot.fromJson(Map<String, dynamic> json) {
    return CheckoutSnapshot(
      orderId: json['order_id'] == null ? null : jsonInt(json['order_id']),
      orderNumber: json['order_number'] as String?,
      paymentId: json['payment_id'] == null ? null : jsonInt(json['payment_id']),
      orderIdempotencyKey: json['order_idempotency_key'] as String?,
      paymentIdempotencyKey: json['payment_idempotency_key'] as String?,
      destinationId: json['destination_id'] == null
          ? null
          : jsonInt(json['destination_id']),
    );
  }

  final int? orderId;
  final String? orderNumber;
  final int? paymentId;
  final String? orderIdempotencyKey;
  final String? paymentIdempotencyKey;
  final int? destinationId;

  bool get hasServerWork => orderId != null || paymentId != null;

  Map<String, dynamic> toJson() {
    return {
      'order_id': ?orderId,
      'order_number': ?orderNumber,
      'payment_id': ?paymentId,
      'order_idempotency_key': ?orderIdempotencyKey,
      'payment_idempotency_key': ?paymentIdempotencyKey,
      'destination_id': ?destinationId,
    };
  }
}

class RecoveryStore {
  RecoveryStore(this._store);

  static const key = 'checkout_recovery';

  final CredentialStore _store;

  Future<CheckoutSnapshot?> read() async {
    final raw = await _store.read(key);
    if (raw == null || raw.isEmpty) {
      return null;
    }
    final decoded = jsonDecode(raw);
    if (decoded is! Map) {
      return null;
    }
    return CheckoutSnapshot.fromJson(Map<String, dynamic>.from(decoded));
  }

  Future<void> write(CheckoutSnapshot snapshot) {
    return _store.write(key, jsonEncode(snapshot.toJson()));
  }

  Future<void> clear() => _store.delete(key);
}
