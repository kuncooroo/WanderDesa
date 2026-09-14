import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/constants/kiosk_timing.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.onIdle,
    this.onStart,
    this.destinationName,
    this.deviceName,
  });

  final VoidCallback onIdle;
  final VoidCallback? onStart;
  final String? destinationName;
  final String? deviceName;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Timer? _idleTimer;

  @override
  void initState() {
    super.initState();
    _restartIdleTimer();
  }

  @override
  void dispose() {
    _idleTimer?.cancel();
    super.dispose();
  }

  void _restartIdleTimer() {
    _idleTimer?.cancel();
    _idleTimer = Timer(KioskTiming.homeIdleTimeout, widget.onIdle);
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: (_) => _restartIdleTimer(),
      child: ColoredBox(
        color: KioskTokens.surface,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(KioskTokens.gutter),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  widget.destinationName ?? KioskStrings.productName,
                  style: KioskTokens.title,
                ),
                if (widget.deviceName != null) ...[
                  const SizedBox(height: 4),
                  Text(widget.deviceName!, style: KioskTokens.bodyMuted),
                ],
                const Spacer(),
                const Text(
                  KioskStrings.homeWelcome,
                  style: KioskTokens.headline,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                Center(
                  child: SizedBox(
                    width: 420,
                    child: KioskPrimaryButton(
                      label: KioskStrings.homeStart,
                      onPressed: widget.onStart,
                    ),
                  ),
                ),
                const Spacer(),
                const Text(
                  KioskStrings.homeHelp,
                  style: KioskTokens.bodyMuted,
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
