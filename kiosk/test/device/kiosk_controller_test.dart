import 'package:flutter_test/flutter_test.dart';
import 'package:wanderdesa_kiosk/core/config/app_config.dart';
import 'package:wanderdesa_kiosk/core/errors/api_exception.dart';
import 'package:wanderdesa_kiosk/device/device_identity_store.dart';
import 'package:wanderdesa_kiosk/device/heartbeat_loop.dart';
import 'package:wanderdesa_kiosk/device/kiosk_controller.dart';
import 'package:wanderdesa_kiosk/network/dto/activation_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/config_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/device_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/heartbeat_dto.dart';
import 'package:wanderdesa_kiosk/network/endpoints.dart';
import 'package:wanderdesa_kiosk/security/device_token_store.dart';
import 'package:wanderdesa_kiosk/storage/memory_credential_store.dart';

class _FakeApi implements KioskDeviceApi {
  _FakeApi({this.onMe, this.onHeartbeat, this.onActivate});

  Future<DeviceDto> Function()? onMe;
  Future<HeartbeatDto> Function()? onHeartbeat;
  Future<ActivationResultDto> Function()? onActivate;

  @override
  Future<ActivationResultDto> exchangeActivation({
    required String deviceId,
    required String activationCode,
  }) {
    return onActivate!.call();
  }

  @override
  Future<HeartbeatDto> heartbeat({bool? printerOk}) {
    return onHeartbeat!.call();
  }

  @override
  Future<DeviceDto> me() => onMe!.call();

  @override
  Future<KioskConfigDto> config() async {
    return const KioskConfigDto(
      localeDefault: 'id',
      currency: 'IDR',
      paymentTtlSeconds: 900,
      features: {'print': true},
    );
  }
}

DeviceDto _activeDevice() {
  return const DeviceDto(
    id: 1,
    deviceId: 'kiosk-01',
    name: 'Gate',
    status: 'active',
    isActive: true,
    maintenanceMode: false,
  );
}

void main() {
  KioskController controllerOf(
    MemoryCredentialStore store,
    _FakeApi api,
  ) {
    return KioskController(
      appConfig: AppConfig.test(),
      tokens: DeviceTokenStore(store),
      identity: DeviceIdentityStore(store),
      endpoints: api,
      heartbeat: HeartbeatLoop.disabled(),
    );
  }

  test('boot without token opens activation', () async {
    final store = MemoryCredentialStore();
    final controller = controllerOf(store, _FakeApi());
    await controller.boot();
    expect(controller.phase, KioskPhase.activation);
  });

  test('invalid token on me returns to activation and clears token', () async {
    final store = MemoryCredentialStore({DeviceTokenStore.key: 'stale'});
    final api = _FakeApi(
      onMe: () async {
        throw const ApiException(
          code: 'auth.unauthenticated',
          message: 'Unauthenticated.',
          statusCode: 401,
        );
      },
    );
    final controller = controllerOf(store, api);
    await controller.boot();
    expect(controller.phase, KioskPhase.activation);
    expect(await store.read(DeviceTokenStore.key), isNull);
  });

  test('offline on launch with stored token shows network error', () async {
    final store = MemoryCredentialStore({DeviceTokenStore.key: 'tok'});
    final api = _FakeApi(
      onMe: () async {
        throw const ApiException(
          code: 'network.unreachable',
          message: 'Network unreachable.',
          statusCode: 0,
        );
      },
    );
    final controller = controllerOf(store, api);
    await controller.boot();
    expect(controller.phase, KioskPhase.networkError);
    expect(await store.read(DeviceTokenStore.key), 'tok');
  });

  test('activation stores token then heartbeats into idle', () async {
    final store = MemoryCredentialStore();
    final api = _FakeApi(
      onActivate: () async {
        return ActivationResultDto(
          token: 'tok_live',
          tokenType: 'Bearer',
          device: _activeDevice(),
        );
      },
      onMe: () async => _activeDevice(),
      onHeartbeat: () async {
        return const HeartbeatDto(
          serverTime: '2026-09-13T16:00:00Z',
          deviceStatus: 'active',
          commands: [],
        );
      },
    );
    final controller = controllerOf(store, api);
    await controller.activate(deviceId: 'kiosk-01', activationCode: 'ABCD');
    expect(controller.phase, KioskPhase.idle);
    expect(await store.read(DeviceTokenStore.key), 'tok_live');
  });

  test('heartbeat enter_maintenance switches to maintenance', () async {
    final store = MemoryCredentialStore({DeviceTokenStore.key: 'tok'});
    var beats = 0;
    final api = _FakeApi(
      onMe: () async => _activeDevice(),
      onHeartbeat: () async {
        beats += 1;
        if (beats == 1) {
          return const HeartbeatDto(
            serverTime: '2026-09-13T16:00:00Z',
            deviceStatus: 'active',
            commands: [],
          );
        }
        return const HeartbeatDto(
          serverTime: '2026-09-13T16:01:00Z',
          deviceStatus: 'maintenance',
          commands: ['enter_maintenance'],
        );
      },
    );
    final controller = controllerOf(store, api);
    await controller.boot();
    expect(controller.phase, KioskPhase.idle);
    await controller.sendHeartbeat();
    expect(controller.phase, KioskPhase.maintenance);
  });

  test('retryConnection after offline boot returns to idle and keeps token', () async {
    final store = MemoryCredentialStore({DeviceTokenStore.key: 'tok'});
    var meCalls = 0;
    final api = _FakeApi(
      onMe: () async {
        meCalls++;
        if (meCalls == 1) {
          throw const ApiException(
            code: 'network.unreachable',
            message: 'Network unreachable.',
            statusCode: 0,
          );
        }
        return _activeDevice();
      },
      onHeartbeat: () async {
        return const HeartbeatDto(
          serverTime: '2026-09-13T16:00:00Z',
          deviceStatus: 'active',
          commands: [],
        );
      },
    );
    final controller = controllerOf(store, api);
    await controller.boot();
    expect(controller.phase, KioskPhase.networkError);
    expect(await store.read(DeviceTokenStore.key), 'tok');
    await controller.retryConnection();
    expect(controller.phase, KioskPhase.idle);
    expect(await store.read(DeviceTokenStore.key), 'tok');
  });
}
