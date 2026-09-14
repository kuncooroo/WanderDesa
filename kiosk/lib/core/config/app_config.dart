/// Compile-time kiosk client config. No secrets belong here.
class AppConfig {
  const AppConfig({
    required this.apiBaseUrl,
    required this.softwareVersion,
    this.hardwareVersion,
    this.prefilledDeviceId,
    this.printAdapter = 'unavailable',
    this.requestTimeout = const Duration(seconds: 15),
  });

  factory AppConfig.fromEnvironment() {
    const rawBase = String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://10.0.2.2:8000/api/v1',
    );
    const deviceId = String.fromEnvironment('DEVICE_ID');
    const version = String.fromEnvironment(
      'SOFTWARE_VERSION',
      defaultValue: '1.0.0',
    );
    const printAdapter = String.fromEnvironment(
      'PRINT_ADAPTER',
      defaultValue: 'unavailable',
    );

    return AppConfig(
      apiBaseUrl: _trimTrailingSlash(rawBase),
      softwareVersion: version,
      hardwareVersion: 'advan-a10-class',
      prefilledDeviceId: deviceId.isEmpty ? null : deviceId,
      printAdapter: printAdapter,
    );
  }

  factory AppConfig.test({
    String apiBaseUrl = 'http://example.test/api/v1',
    String softwareVersion = '1.0.0',
  }) {
    return AppConfig(
      apiBaseUrl: apiBaseUrl,
      softwareVersion: softwareVersion,
      hardwareVersion: 'test',
      printAdapter: 'unavailable',
    );
  }

  final String apiBaseUrl;
  final String softwareVersion;
  final String? hardwareVersion;
  final String? prefilledDeviceId;
  final String printAdapter;
  final Duration requestTimeout;

  static String _trimTrailingSlash(String value) {
    if (value.endsWith('/')) {
      return value.substring(0, value.length - 1);
    }
    return value;
  }
}
