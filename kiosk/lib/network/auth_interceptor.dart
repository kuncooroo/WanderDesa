import '../security/device_token_store.dart';

/// Attaches Sanctum device Bearer token when a request is authenticated.
class AuthInterceptor {
  const AuthInterceptor(this._tokens);

  final DeviceTokenStore _tokens;

  Future<Map<String, String>> apply({
    required Map<String, String> headers,
    required bool authenticated,
  }) async {
    final next = Map<String, String>.from(headers);
    if (!authenticated) {
      return next;
    }

    final token = await _tokens.read();
    if (token != null && token.isNotEmpty) {
      next['Authorization'] = 'Bearer $token';
    }
    return next;
  }
}
