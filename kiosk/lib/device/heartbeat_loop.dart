import 'dart:async';

import '../core/constants/kiosk_timing.dart';

class HeartbeatLoop {
  HeartbeatLoop({
    this.interval = KioskTiming.heartbeatInterval,
    this.enabled = true,
  });

  factory HeartbeatLoop.disabled() => HeartbeatLoop(enabled: false);

  final Duration interval;
  final bool enabled;
  Timer? _timer;

  bool get isRunning => _timer != null && _timer!.isActive;

  void start(void Function() onTick) {
    stop();
    if (!enabled) {
      return;
    }
    _timer = Timer.periodic(interval, (_) => onTick());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }
}
