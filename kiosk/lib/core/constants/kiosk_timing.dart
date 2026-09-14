/// UX timers from docs/09. Heartbeat cadence stays well under the API limit.
abstract final class KioskTiming {
  static const heartbeatInterval = Duration(seconds: 30);
  static const homeIdleTimeout = Duration(seconds: 50);
  static const sessionInactivity = Duration(seconds: 75);
  static const sessionWarning = Duration(seconds: 10);
  static const paymentPollInterval = Duration(seconds: 2);
  static const successDwell = Duration(seconds: 3);
  static const errorAutoIdle = Duration(seconds: 60);
}
