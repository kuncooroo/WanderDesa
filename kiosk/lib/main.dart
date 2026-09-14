import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import 'app.dart';
import 'core/config/app_config.dart';
import 'device/device_identity_store.dart';
import 'device/kiosk_controller.dart';
import 'network/api_client.dart';
import 'network/auth_interceptor.dart';
import 'network/endpoints.dart';
import 'printer/printer_port.dart';
import 'security/device_token_store.dart';
import 'storage/recovery_store.dart';
import 'storage/secure_credential_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await lockKioskPresentation();

  final config = AppConfig.fromEnvironment();
  final credentials = SecureCredentialStore();
  final tokens = DeviceTokenStore(credentials);
  final identity = DeviceIdentityStore(credentials);
  final api = ApiClient(
    config: config,
    httpClient: http.Client(),
    interceptor: AuthInterceptor(tokens),
  );
  final endpoints = KioskEndpoints(api, config);
  final recovery = RecoveryStore(credentials);
  final printer = printerFromAdapter(config.printAdapter);
  final controller = KioskController(
    appConfig: config,
    tokens: tokens,
    identity: identity,
    endpoints: endpoints,
    commerce: endpoints,
    recovery: recovery,
    printer: printer,
  );

  runApp(KioskApp(controller: controller));
  unawaited(controller.boot());
}

Future<void> lockKioskPresentation() async {
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  await SystemChrome.setPreferredOrientations(const [
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);
}
