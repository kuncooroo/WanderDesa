class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    required this.statusCode,
    this.details = const [],
  });

  final String code;
  final String message;
  final int statusCode;
  final List<Object?> details;

  bool get isUnauthorized => statusCode == 401;

  bool get isForbidden => statusCode == 403;

  bool get isNetwork =>
      code == 'network.unreachable' || code == 'network.timeout';

  bool get isInvalidActivation => code == 'auth.activation_invalid';

  bool get requiresReactivation =>
      isUnauthorized ||
      code == 'auth.token_invalid' ||
      code == 'auth.unauthenticated';

  bool get isDeviceInactive =>
      code == 'device.inactive' || code == 'device.disabled';

  bool get isMaintenance => code == 'device.maintenance';

  @override
  String toString() => 'ApiException($statusCode $code: $message)';
}
