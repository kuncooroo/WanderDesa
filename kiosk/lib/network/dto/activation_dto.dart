import 'device_dto.dart';

class ActivationResultDto {
  const ActivationResultDto({
    required this.token,
    required this.tokenType,
    required this.device,
  });

  factory ActivationResultDto.fromJson(Map<String, dynamic> json) {
    return ActivationResultDto(
      token: json['token'] as String,
      tokenType: json['token_type'] as String? ?? 'Bearer',
      device: DeviceDto.fromJson(json['device'] as Map<String, dynamic>),
    );
  }

  final String token;
  final String tokenType;
  final DeviceDto device;
}
