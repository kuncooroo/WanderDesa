import '../storage/credential_store.dart';

class DeviceIdentityStore {
  DeviceIdentityStore(this._store);

  static const key = 'device_id';

  final CredentialStore _store;

  Future<String?> read() => _store.read(key);

  Future<void> write(String deviceId) => _store.write(key, deviceId);

  Future<void> clear() => _store.delete(key);
}
