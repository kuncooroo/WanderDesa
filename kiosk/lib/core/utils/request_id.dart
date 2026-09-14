class RequestId {
  static int _sequence = 0;

  static String next() {
    _sequence += 1;
    final millis = DateTime.now().toUtc().millisecondsSinceEpoch;
    return 'req_kiosk_${millis}_$_sequence';
  }
}
