import '../../core/utils/json_numbers.dart';

class DestinationDto {
  const DestinationDto({
    required this.id,
    required this.code,
    required this.name,
    required this.timezone,
    required this.isActive,
  });

  factory DestinationDto.fromJson(Map<String, dynamic> json) {
    return DestinationDto(
      id: jsonInt(json['id']),
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      timezone: json['timezone'] as String? ?? '',
      isActive: jsonBool(json['is_active']),
    );
  }

  final int id;
  final String code;
  final String name;
  final String timezone;
  final bool isActive;
}
