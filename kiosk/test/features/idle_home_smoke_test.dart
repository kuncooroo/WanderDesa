import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wanderdesa_kiosk/core/config/app_config.dart';
import 'package:wanderdesa_kiosk/core/errors/api_exception.dart';
import 'package:wanderdesa_kiosk/device/device_identity_store.dart';
import 'package:wanderdesa_kiosk/device/heartbeat_loop.dart';
import 'package:wanderdesa_kiosk/device/kiosk_controller.dart';
import 'package:wanderdesa_kiosk/features/home/home_screen.dart';
import 'package:wanderdesa_kiosk/features/idle/idle_screen.dart';
import 'package:wanderdesa_kiosk/features/shell/kiosk_shell.dart';
import 'package:wanderdesa_kiosk/network/dto/activation_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/config_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/destination_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/device_dto.dart';
import 'package:wanderdesa_kiosk/network/dto/heartbeat_dto.dart';
import 'package:wanderdesa_kiosk/network/endpoints.dart';
import 'package:wanderdesa_kiosk/security/device_token_store.dart';
import 'package:wanderdesa_kiosk/shared/l10n/kiosk_strings.dart';
import 'package:wanderdesa_kiosk/shared/theme/kiosk_theme.dart';
import 'package:wanderdesa_kiosk/storage/memory_credential_store.dart';

class _FakeApi implements KioskDeviceApi {
  _FakeApi({
    this.device = const DeviceDto(
      id: 1,
      deviceId: 'kiosk-01',
      name: 'Kiosk Gate',
      status: 'active',
      isActive: true,
      maintenanceMode: false,
    ),
    this.heartbeatStatus = 'active',
    this.commands = const [],
  });

  DeviceDto device;
  String heartbeatStatus;
  List<String> commands;

  @override
  Future<ActivationResultDto> exchangeActivation({
    required String deviceId,
    required String activationCode,
  }) async {
    throw const ApiException(
      code: 'auth.activation_invalid',
      message: 'unused',
      statusCode: 401,
    );
  }

  @override
  Future<HeartbeatDto> heartbeat({bool? printerOk}) async {
    return HeartbeatDto(
      serverTime: '2026-09-13T16:00:00Z',
      deviceStatus: heartbeatStatus,
      commands: commands,
    );
  }

  @override
  Future<DeviceDto> me() async => device;

  @override
  Future<KioskConfigDto> config() async {
    return const KioskConfigDto(
      localeDefault: 'id',
      currency: 'IDR',
      paymentTtlSeconds: 900,
      features: {'print': true},
      destination: DestinationDto(
        id: 1,
        code: 'WD',
        name: 'Desa Wisata',
        timezone: 'Asia/Jakarta',
        isActive: true,
      ),
    );
  }
}

Widget _app(Widget child) {
  return MaterialApp(
    theme: KioskTheme.light(),
    home: child,
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('idle screen shows attract copy', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1280, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(IdleScreen(onStart: () {}, destinationName: 'Desa Wisata')),
    );

    expect(find.text(KioskStrings.idleCta), findsOneWidget);
    expect(find.text('Desa Wisata'), findsOneWidget);
  });

  testWidgets('home screen shows Mulai CTA', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1280, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        HomeScreen(
          onIdle: () {},
          destinationName: 'Desa Wisata',
          deviceName: 'Kiosk Gate',
        ),
      ),
    );

    expect(find.text(KioskStrings.homeStart), findsOneWidget);
    expect(find.text(KioskStrings.homeWelcome), findsOneWidget);
    expect(find.text(KioskStrings.homeHelp), findsOneWidget);
  });

  testWidgets('shell renders maintenance when API status is maintenance', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1280, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final credentials = MemoryCredentialStore({
      DeviceTokenStore.key: 'tok',
      DeviceIdentityStore.key: 'kiosk-01',
    });
    final api = _FakeApi(
      device: const DeviceDto(
        id: 1,
        deviceId: 'kiosk-01',
        name: 'Kiosk Gate',
        status: 'maintenance',
        isActive: true,
        maintenanceMode: true,
      ),
      heartbeatStatus: 'maintenance',
      commands: const ['enter_maintenance'],
    );
    final controller = KioskController(
      appConfig: AppConfig.test(),
      tokens: DeviceTokenStore(credentials),
      identity: DeviceIdentityStore(credentials),
      endpoints: api,
      heartbeat: HeartbeatLoop.disabled(),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: KioskTheme.light(),
        home: KioskShell(controller: controller),
      ),
    );
    await controller.boot();
    await tester.pump();

    expect(find.text(KioskStrings.maintenanceTitle), findsOneWidget);
    expect(find.text(KioskStrings.homeStart), findsNothing);
  });
}
