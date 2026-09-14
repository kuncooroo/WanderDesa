import 'destination_dto.dart';

class KioskConfigDto {
  const KioskConfigDto({
    required this.localeDefault,
    required this.currency,
    required this.paymentTtlSeconds,
    required this.features,
    this.destination,
  });

  factory KioskConfigDto.fromJson(Map<String, dynamic> json) {
    final destination = json['destination'];
    final features = json['features'];
    return KioskConfigDto(
      destination: destination is Map<String, dynamic>
          ? DestinationDto.fromJson(destination)
          : null,
      localeDefault: json['locale_default'] as String? ?? 'id',
      currency: json['currency'] as String? ?? 'IDR',
      paymentTtlSeconds: json['payment_ttl_seconds'] as int? ?? 0,
      features: features is Map<String, dynamic>
          ? features.map((key, value) => MapEntry(key, value == true))
          : const <String, bool>{},
    );
  }

  final DestinationDto? destination;
  final String localeDefault;
  final String currency;
  final int paymentTtlSeconds;
  final Map<String, bool> features;
}
