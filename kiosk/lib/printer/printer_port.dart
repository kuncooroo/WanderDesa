import '../network/dto/print_payload_dto.dart';

class PrintResult {
  const PrintResult({required this.ok, this.message});

  final bool ok;
  final String? message;
}

/// Maps **server** print-payload to hardware. Never mints ticket codes.
abstract class TicketPrinter {
  bool get isAvailable;

  bool? get lastOk;

  Future<PrintResult> printPayload(PrintPayloadDto payload);
}

class UnavailableTicketPrinter implements TicketPrinter {
  const UnavailableTicketPrinter();

  @override
  bool get isAvailable => false;

  @override
  bool? get lastOk => false;

  @override
  Future<PrintResult> printPayload(PrintPayloadDto payload) async {
    return const PrintResult(ok: false, message: 'printer.unavailable');
  }
}

class MockTicketPrinter implements TicketPrinter {
  MockTicketPrinter({this.available = true, this.fail = false});

  final bool available;
  final bool fail;
  final List<PrintPayloadDto> printed = [];
  bool? _lastOk;

  @override
  bool get isAvailable => available;

  @override
  bool? get lastOk => _lastOk;

  @override
  Future<PrintResult> printPayload(PrintPayloadDto payload) async {
    printed.add(payload);
    if (fail) {
      _lastOk = false;
      return const PrintResult(ok: false, message: 'printer.mock_fail');
    }
    _lastOk = true;
    return const PrintResult(ok: true);
  }
}

/// Fails after [failAfter] successful prints (partial multi-ticket).
class PartialFailTicketPrinter implements TicketPrinter {
  PartialFailTicketPrinter({this.failAfter = 1});

  final int failAfter;
  int _okCount = 0;
  bool? _lastOk;

  @override
  bool get isAvailable => true;

  @override
  bool? get lastOk => _lastOk;

  @override
  Future<PrintResult> printPayload(PrintPayloadDto payload) async {
    if (_okCount >= failAfter) {
      _lastOk = false;
      return const PrintResult(ok: false, message: 'printer.partial_fail');
    }
    _okCount++;
    _lastOk = true;
    return const PrintResult(ok: true);
  }
}

TicketPrinter printerFromAdapter(String adapter) {
  return switch (adapter) {
    'mock_success' => MockTicketPrinter(),
    'mock_fail' => MockTicketPrinter(fail: true),
    _ => const UnavailableTicketPrinter(),
  };
}
