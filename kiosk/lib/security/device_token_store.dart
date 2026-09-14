import '../storage/credential_store.dart';

/// Sanctum device token persistence. Local storage is never payment authority.
class DeviceTokenStore {
  DeviceTokenStore(this._store);

  static const key = 'device_token';

  final CredentialStore _store;

  Future<String?> read() => _store.read(key);

  Future<void> write(String token) => _store.write(key, token);

  Future<void> clear() => _store.delete(key);
}
