import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:wanderdesa_kiosk/core/config/app_config.dart';
import 'package:wanderdesa_kiosk/network/api_client.dart';
import 'package:wanderdesa_kiosk/network/auth_interceptor.dart';
import 'package:wanderdesa_kiosk/security/device_token_store.dart';
import 'package:wanderdesa_kiosk/storage/memory_credential_store.dart';

void main() {
  const config = AppConfig(
    apiBaseUrl: 'http://example.test/api/v1',
    softwareVersion: '1.0.0',
  );

  ApiClient buildClient(
    MockClient mock, {
    String? token,
  }) {
    final store = MemoryCredentialStore({
      DeviceTokenStore.key: ?token,
    });
    return ApiClient(
      config: config,
      httpClient: mock,
      interceptor: AuthInterceptor(DeviceTokenStore(store)),
    );
  }

  test('attaches Bearer token on authenticated requests', () async {
    late http.BaseRequest captured;
    final mock = MockClient((request) async {
      captured = request;
      return http.Response(
        jsonEncode({
          'success': true,
          'data': {'ok': true},
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    });

    final client = buildClient(mock, token: 'tok_device_abc');
    await client.get('/kiosks/me');

    expect(captured.headers['Authorization'], 'Bearer tok_device_abc');
    expect(captured.headers['Accept'], 'application/json');
    expect(captured.headers['X-Request-Id'], isNotEmpty);
    expect(captured.url.toString(), 'http://example.test/api/v1/kiosks/me');
  });

  test('omits Authorization on activation exchange', () async {
    late http.BaseRequest captured;
    final mock = MockClient((request) async {
      captured = request;
      return http.Response(
        jsonEncode({
          'success': true,
          'data': {
            'token': 'new',
            'token_type': 'Bearer',
            'device': {
              'id': 1,
              'device_id': 'kiosk-01',
              'name': 'Gate',
              'status': 'active',
              'is_active': true,
              'maintenance_mode': false,
            },
          },
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    });

    final client = buildClient(mock, token: 'stale_token');
    await client.post(
      '/kiosks/activate',
      authenticated: false,
      body: {
        'device_id': 'kiosk-01',
        'activation_code': 'CODE',
      },
    );

    expect(captured.headers.containsKey('Authorization'), isFalse);
    expect(captured.headers['Content-Type'], contains('application/json'));
  });

  test('sends Idempotency-Key on commerce POSTs', () async {
    late http.BaseRequest captured;
    final mock = MockClient((request) async {
      captured = request;
      return http.Response(
        jsonEncode({
          'success': true,
          'data': {'id': 1, 'status': 'pending_payment', 'grand_total': 1},
        }),
        201,
        headers: {'content-type': 'application/json'},
      );
    });

    final client = buildClient(mock, token: 'tok');
    await client.post(
      '/orders',
      idempotencyKey: 'kiosk_order_1',
      body: {
        'destination_id': 1,
        'items': [
          {'ticket_type_id': 2, 'quantity': 1},
        ],
      },
    );

    expect(captured.headers['Idempotency-Key'], 'kiosk_order_1');
    expect(captured.headers['Authorization'], 'Bearer tok');
  });
}
