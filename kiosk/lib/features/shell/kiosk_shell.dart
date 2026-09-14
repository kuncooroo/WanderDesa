import 'package:flutter/material.dart';

import '../../device/kiosk_controller.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../activation/activation_screen.dart';
import '../activation/disabled_screen.dart';
import '../checkout/checkout_flow.dart';
import '../home/home_screen.dart';
import '../idle/idle_screen.dart';
import '../maintenance/maintenance_screen.dart';
import '../network/network_error_screen.dart';

class KioskShell extends StatelessWidget {
  const KioskShell({super.key, required this.controller});

  final KioskController controller;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        final destination = controller.config?.destination?.name;
        final child = switch (controller.phase) {
          KioskPhase.booting => const _BootPane(),
          KioskPhase.activation => ActivationScreen(
            onSubmit: (deviceId, code) =>
                controller.activate(deviceId: deviceId, activationCode: code),
            initialDeviceId: controller.lastDeviceId,
            errorMessage: controller.bannerMessage,
            busy: controller.busy,
          ),
          KioskPhase.idle => IdleScreen(
            onStart: controller.openHome,
            destinationName: destination,
          ),
          KioskPhase.home => HomeScreen(
            onIdle: controller.returnToIdle,
            onStart: controller.startCheckout,
            destinationName: destination,
            deviceName: controller.device?.name,
          ),
          KioskPhase.checkout => controller.checkout == null
              ? const _BootPane()
              : CheckoutFlow(session: controller.checkout!),
          KioskPhase.maintenance => const MaintenanceScreen(),
          KioskPhase.networkError => NetworkErrorScreen(
            onRetry: controller.retryConnection,
            message: controller.bannerMessage,
          ),
          KioskPhase.disabled => DisabledScreen(
            onActivate: controller.openActivation,
          ),
        };

        return Stack(
          fit: StackFit.expand,
          children: [
            child,
            if (controller.busy &&
                controller.phase != KioskPhase.activation &&
                controller.phase != KioskPhase.booting &&
                controller.phase != KioskPhase.checkout)
              const ColoredBox(
                color: Color(0x88000000),
                child: Center(
                  child: CircularProgressIndicator(color: Colors.white),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _BootPane extends StatelessWidget {
  const _BootPane();

  @override
  Widget build(BuildContext context) {
    return const ColoredBox(
      color: KioskTokens.surface,
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: KioskTokens.primary),
            SizedBox(height: 24),
            Text(KioskStrings.booting, style: KioskTokens.body),
          ],
        ),
      ),
    );
  }
}
