import 'dart:math';

/// Opaque client retry key (≤128 chars). Laravel decides the resource.
String newIdempotencyKey(String kind) {
  final now = DateTime.now().toUtc().microsecondsSinceEpoch;
  final salt = Random().nextInt(0x7fffffff).toRadixString(16);
  return 'kiosk_${kind}_${now}_$salt';
}
