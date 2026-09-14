import 'dart:async';

import 'package:flutter/foundation.dart';

import '../core/config/app_config.dart';
import '../core/errors/api_exception.dart';
import '../core/errors/error_mapper.dart';
import '../features/checkout/checkout_session.dart';
import '../network/commerce_api.dart';
import '../network/dto/config_dto.dart';
import '../network/dto/device_dto.dart';
import '../network/endpoints.dart';
import '../printer/printer_port.dart';
import '../security/device_token_store.dart';
import '../storage/memory_credential_store.dart';
import '../storage/recovery_store.dart';
import 'device_identity_store.dart';
import 'heartbeat_loop.dart';

enum KioskPhase {
  booting,
  activation,
  idle,
  home,
  checkout,
  maintenance,
  networkError,
  disabled,
}

/// UI/session state only. Laravel owns device/commerce status.
class KioskController extends ChangeNotifier {
  KioskController({
    required this.appConfig,
    required DeviceTokenStore tokens,
    required DeviceIdentityStore identity,
    required KioskDeviceApi endpoints,
    KioskCommerceApi? commerce,
    RecoveryStore? recovery,
    TicketPrinter? printer,
    HeartbeatLoop? heartbeat,
  }) : _tokens = tokens,
       _identity = identity,
       _endpoints = endpoints,
       _commerce = commerce,
       _recovery = recovery,
       _printer = printer ?? const UnavailableTicketPrinter(),
       _heartbeat = heartbeat ?? HeartbeatLoop();

  final AppConfig appConfig;
  final DeviceTokenStore _tokens;
  final DeviceIdentityStore _identity;
  final KioskDeviceApi _endpoints;
  final KioskCommerceApi? _commerce;
  final RecoveryStore? _recovery;
  final TicketPrinter _printer;
  final HeartbeatLoop _heartbeat;

  KioskPhase phase = KioskPhase.booting;
  bool busy = false;
  String? bannerMessage;
  DeviceDto? device;
  KioskConfigDto? config;
  String? lastDeviceId;
  CheckoutSession? checkout;
  bool? _printerOk;

  void _setPrinterOk(bool ok) {
    _printerOk = ok;
  }

  bool get canSell {
    final current = device;
    if (current == null) {
      return false;
    }
    return current.status == 'active' &&
        current.isActive &&
        !current.isMaintenance;
  }

  Future<void> boot() async {
    phase = KioskPhase.booting;
    bannerMessage = null;
    lastDeviceId =
        await _identity.read() ?? appConfig.prefilledDeviceId;
    notifyListeners();

    final token = await _tokens.read();
    if (token == null || token.isEmpty) {
      _showActivation();
      return;
    }

    await _refreshSession(startLoop: true);
    if (phase == KioskPhase.idle || phase == KioskPhase.home) {
      await _tryRecoverCheckout();
    }
  }

  Future<void> activate({
    required String deviceId,
    required String activationCode,
  }) async {
    busy = true;
    bannerMessage = null;
    notifyListeners();

    try {
      final result = await _endpoints.exchangeActivation(
        deviceId: deviceId.trim(),
        activationCode: activationCode.trim(),
      );
      await _tokens.write(result.token);
      await _identity.write(result.device.deviceId);
      lastDeviceId = result.device.deviceId;
      device = result.device;
      await _refreshSession(startLoop: true);
    } on ApiException catch (error) {
      busy = false;
      bannerMessage = ErrorMapper.toUserMessage(error);
      phase = KioskPhase.activation;
      notifyListeners();
    }
  }

  Future<void> retryConnection() => _refreshSession(startLoop: true);

  void openHome() {
    if (phase != KioskPhase.idle) {
      return;
    }
    if (!canSell) {
      _applyDeviceFlags();
      return;
    }
    phase = KioskPhase.home;
    notifyListeners();
  }

  void returnToIdle() {
    if (phase == KioskPhase.home) {
      phase = KioskPhase.idle;
      notifyListeners();
    }
  }

  Future<void> startCheckout() async {
    final commerce = _commerce;
    if (!canSell || commerce == null) {
      return;
    }
    _disposeCheckout();
    checkout = CheckoutSession(
      commerce: commerce,
      recovery: _recovery ?? RecoveryStore(MemoryCredentialStore()),
      printEnabled: config?.features['print'] ?? true,
      printer: _printer,
      onPrinterHealth: _setPrinterOk,
      onFinished: endCheckout,
    )..addListener(notifyListeners);
    phase = KioskPhase.checkout;
    notifyListeners();
    await checkout!.start(bound: config?.destination);
  }

  void endCheckout() {
    _disposeCheckout();
    if (phase == KioskPhase.checkout) {
      phase = KioskPhase.idle;
      notifyListeners();
    }
  }

  void _disposeCheckout() {
    checkout?.removeListener(notifyListeners);
    checkout?.dispose();
    checkout = null;
  }

  Future<void> _tryRecoverCheckout() async {
    final commerce = _commerce;
    final recovery = _recovery;
    if (commerce == null || recovery == null) {
      return;
    }
    final snapshot = await recovery.read();
    if (snapshot == null || !snapshot.hasServerWork) {
      return;
    }
    _disposeCheckout();
    checkout = CheckoutSession(
      commerce: commerce,
      recovery: recovery,
      printEnabled: config?.features['print'] ?? true,
      printer: _printer,
      onPrinterHealth: _setPrinterOk,
      onFinished: endCheckout,
    )..addListener(notifyListeners);
    phase = KioskPhase.checkout;
    notifyListeners();
    await checkout!.recover(snapshot);
  }

  void openActivation() {
    bannerMessage = null;
    _showActivation();
  }

  Future<void> sendHeartbeat() async {
    if (phase == KioskPhase.activation || phase == KioskPhase.booting) {
      return;
    }

    try {
      final beat = await _endpoints.heartbeat(printerOk: _printerOk);
      if (beat.isDisabled) {
        await _handleDisabled();
        return;
      }
      if (beat.enterMaintenance) {
        if (checkout?.locksShellOnMaintenance ?? false) {
          return;
        }
        _disposeCheckout();
        _enterMaintenance();
        return;
      }
      if (beat.isActive &&
          (phase == KioskPhase.maintenance ||
              phase == KioskPhase.networkError ||
              phase == KioskPhase.disabled)) {
        device = device?.copyWith(
          status: 'active',
          isActive: true,
          maintenanceMode: false,
          lastHeartbeatAt: beat.serverTime,
          online: true,
        );
        phase = KioskPhase.idle;
        bannerMessage = null;
        notifyListeners();
      }
    } on ApiException catch (error) {
      await _handleApiError(error);
    }
  }

  Future<void> _refreshSession({required bool startLoop}) async {
    busy = true;
    notifyListeners();

    try {
      device = await _endpoints.me();
      config = await _endpoints.config();
      busy = false;
      bannerMessage = null;
      _applyDeviceFlags();
      if (startLoop &&
          phase != KioskPhase.activation &&
          phase != KioskPhase.disabled) {
        _heartbeat.start(() {
          sendHeartbeat();
        });
        await sendHeartbeat();
      }
    } on ApiException catch (error) {
      busy = false;
      await _handleApiError(error);
    }
  }

  void _applyDeviceFlags() {
    final current = device;
    if (current == null) {
      _showActivation();
      return;
    }
    if (current.isDisabled) {
      unawaited(_handleDisabled());
      return;
    }
    if (current.isMaintenance) {
      if (checkout?.locksShellOnMaintenance ?? false) {
        notifyListeners();
        return;
      }
      _disposeCheckout();
      _enterMaintenance();
      return;
    }
    if (phase == KioskPhase.home || phase == KioskPhase.checkout) {
      notifyListeners();
      return;
    }
    phase = KioskPhase.idle;
    notifyListeners();
  }

  void _enterMaintenance() {
    phase = KioskPhase.maintenance;
    bannerMessage = null;
    notifyListeners();
  }

  void _showActivation() {
    _heartbeat.stop();
    phase = KioskPhase.activation;
    busy = false;
    notifyListeners();
  }

  Future<void> _handleDisabled() async {
    await _tokens.clear();
    _heartbeat.stop();
    device = null;
    config = null;
    phase = KioskPhase.disabled;
    bannerMessage = KioskErrorCopy.deviceDisabled;
    notifyListeners();
  }

  Future<void> _handleApiError(ApiException error) async {
    if (error.requiresReactivation) {
      await _tokens.clear();
      bannerMessage = ErrorMapper.toUserMessage(error);
      _showActivation();
      return;
    }
    if (error.isDeviceInactive) {
      await _handleDisabled();
      return;
    }
    if (error.isMaintenance) {
      _enterMaintenance();
      return;
    }
    if (error.isNetwork) {
      if (phase == KioskPhase.checkout &&
          (checkout?.isInFlightPayment ?? false)) {
        return;
      }
      phase = KioskPhase.networkError;
      bannerMessage = ErrorMapper.toUserMessage(error);
      notifyListeners();
      return;
    }
    bannerMessage = ErrorMapper.toUserMessage(error);
    notifyListeners();
  }

  @override
  void dispose() {
    _heartbeat.stop();
    _disposeCheckout();
    super.dispose();
  }
}
