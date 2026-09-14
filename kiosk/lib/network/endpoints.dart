import '../core/config/app_config.dart';
import '../core/constants/api_paths.dart';
import 'api_client.dart';
import 'commerce_api.dart';
import 'dto/activation_dto.dart';
import 'dto/config_dto.dart';
import 'dto/destination_dto.dart';
import 'dto/device_dto.dart';
import 'dto/heartbeat_dto.dart';
import 'dto/order_dto.dart';
import 'dto/payment_dto.dart';
import 'dto/print_payload_dto.dart';
import 'dto/quote_dto.dart';
import 'dto/ticket_dto.dart';
import 'dto/ticket_type_dto.dart';

abstract class KioskDeviceApi {
  Future<ActivationResultDto> exchangeActivation({
    required String deviceId,
    required String activationCode,
  });

  Future<HeartbeatDto> heartbeat({bool? printerOk});

  Future<DeviceDto> me();

  Future<KioskConfigDto> config();
}

class KioskEndpoints implements KioskDeviceApi, KioskCommerceApi {
  const KioskEndpoints(this._api, this._config);

  final ApiClient _api;
  final AppConfig _config;

  @override
  Future<ActivationResultDto> exchangeActivation({
    required String deviceId,
    required String activationCode,
  }) async {
    final data = await _api.post(
      ApiPaths.activate,
      authenticated: false,
      body: {
        'device_id': deviceId,
        'activation_code': activationCode,
        'software_version': _config.softwareVersion,
      },
    );
    return ActivationResultDto.fromJson(data);
  }

  @override
  Future<HeartbeatDto> heartbeat({bool? printerOk}) async {
    final body = <String, dynamic>{
      'software_version': _config.softwareVersion,
      'hardware_version': ?_config.hardwareVersion,
      'printer_ok': ?printerOk,
    };
    final data = await _api.post(ApiPaths.heartbeat, body: body);
    return HeartbeatDto.fromJson(data);
  }

  @override
  Future<DeviceDto> me() async {
    final data = await _api.get(ApiPaths.me);
    return DeviceDto.fromJson(data);
  }

  @override
  Future<KioskConfigDto> config() async {
    final data = await _api.get(ApiPaths.config);
    return KioskConfigDto.fromJson(data);
  }

  @override
  Future<List<DestinationDto>> destinations() async {
    final rows = await _api.getList('${ApiPaths.destinations}?active=1');
    return rows.map(DestinationDto.fromJson).toList();
  }

  @override
  Future<List<TicketTypeDto>> ticketTypes(int destinationId) async {
    final rows = await _api.getList(ApiPaths.ticketTypes(destinationId));
    return rows.map(TicketTypeDto.fromJson).toList();
  }

  @override
  Future<QuoteDto> quote({
    required int destinationId,
    required List<CartItemInput> items,
  }) async {
    final data = await _api.post(
      ApiPaths.quote,
      body: {
        'destination_id': destinationId,
        'items': items.map((item) => item.toJson()).toList(),
      },
    );
    return QuoteDto.fromJson(data);
  }

  @override
  Future<OrderDto> createOrder({
    required int destinationId,
    required List<CartItemInput> items,
    required String idempotencyKey,
  }) async {
    final data = await _api.post(
      ApiPaths.orders,
      idempotencyKey: idempotencyKey,
      body: {
        'destination_id': destinationId,
        'items': items.map((item) => item.toJson()).toList(),
      },
    );
    return OrderDto.fromJson(data);
  }

  @override
  Future<OrderDto> getOrder(int orderId) async {
    final data = await _api.get(ApiPaths.order(orderId));
    return OrderDto.fromJson(data);
  }

  @override
  Future<OrderDto> cancelOrder(int orderId, {String? reason}) async {
    final data = await _api.post(
      ApiPaths.cancelOrder(orderId),
      body: {'reason': ?reason},
    );
    return OrderDto.fromJson(data);
  }

  @override
  Future<PaymentInitiationDto> initiatePayment({
    required int orderId,
    required String idempotencyKey,
  }) async {
    final data = await _api.post(
      ApiPaths.orderPayments(orderId),
      idempotencyKey: idempotencyKey,
      body: const {'method': 'digital'},
    );
    return PaymentInitiationDto.fromJson(data);
  }

  @override
  Future<PaymentDto> getPayment(int paymentId) async {
    final data = await _api.get(ApiPaths.payment(paymentId));
    return PaymentDto.fromJson(data);
  }

  @override
  Future<List<TicketDto>> orderTickets(int orderId) async {
    final rows = await _api.getList(ApiPaths.orderTickets(orderId));
    return rows.map(TicketDto.fromJson).toList();
  }

  @override
  Future<PrintPayloadDto> printPayload(String ticketCode) async {
    final data = await _api.get(ApiPaths.printPayload(ticketCode));
    return PrintPayloadDto.fromJson(data);
  }

  @override
  Future<void> printAck({
    required String ticketCode,
    required String result,
    String? message,
  }) async {
    await _api.post(
      ApiPaths.printAck(ticketCode),
      body: {
        'result': result,
        'message': ?message,
      },
    );
  }
}
