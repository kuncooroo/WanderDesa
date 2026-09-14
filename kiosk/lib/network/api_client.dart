import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../core/config/app_config.dart';
import '../core/errors/api_exception.dart';
import '../core/utils/request_id.dart';
import 'auth_interceptor.dart';

/// Thin HTTP client. Parses Laravel envelopes; never treats local state as truth.
class ApiClient {
  ApiClient({
    required AppConfig config,
    required http.Client httpClient,
    required AuthInterceptor interceptor,
  }) : _config = config,
       _http = httpClient,
       _interceptor = interceptor;

  final AppConfig _config;
  final http.Client _http;
  final AuthInterceptor _interceptor;

  Future<Map<String, dynamic>> get(
    String path, {
    bool authenticated = true,
  }) {
    return send('GET', path, authenticated: authenticated);
  }

  Future<List<Map<String, dynamic>>> getList(
    String path, {
    bool authenticated = true,
  }) async {
    final data = await _exchange('GET', path, authenticated: authenticated);
    return _asObjectList(data);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
    String? idempotencyKey,
  }) {
    return send(
      'POST',
      path,
      body: body,
      authenticated: authenticated,
      idempotencyKey: idempotencyKey,
    );
  }

  Future<Map<String, dynamic>> send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
    String? idempotencyKey,
  }) async {
    final data = await _exchange(
      method,
      path,
      body: body,
      authenticated: authenticated,
      idempotencyKey: idempotencyKey,
    );
    if (data is Map<String, dynamic>) {
      return data;
    }
    if (data == null) {
      return <String, dynamic>{};
    }
    throw const ApiException(
      code: 'server.error',
      message: 'Unexpected data shape.',
      statusCode: 200,
    );
  }

  Future<Object?> _exchange(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
    String? idempotencyKey,
  }) async {
    final uri = Uri.parse('$_configBase$path');
    var headers = <String, String>{
      'Accept': 'application/json',
      'X-Request-Id': RequestId.next(),
    };
    if (body != null) {
      headers['Content-Type'] = 'application/json';
    }
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['Idempotency-Key'] = idempotencyKey;
    }
    headers = await _interceptor.apply(
      headers: headers,
      authenticated: authenticated,
    );

    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) {
        request.body = jsonEncode(body);
      }

      final streamed = await _http
          .send(request)
          .timeout(_config.requestTimeout);
      final response = await http.Response.fromStream(streamed);
      return _decodeData(response);
    } on TimeoutException {
      throw const ApiException(
        code: 'network.timeout',
        message: 'Request timed out.',
        statusCode: 0,
      );
    } on SocketException {
      throw const ApiException(
        code: 'network.unreachable',
        message: 'Network unreachable.',
        statusCode: 0,
      );
    } on http.ClientException {
      throw const ApiException(
        code: 'network.unreachable',
        message: 'Network unreachable.',
        statusCode: 0,
      );
    }
  }

  String get _configBase => _config.apiBaseUrl;

  Object? _decodeData(http.Response response) {
    final raw = response.body.isEmpty ? '{}' : response.body;
    late final Object? decoded;
    try {
      decoded = jsonDecode(raw);
    } on FormatException {
      throw ApiException(
        code: 'server.error',
        message: 'Invalid JSON from server.',
        statusCode: response.statusCode,
      );
    }

    if (decoded is! Map<String, dynamic>) {
      throw ApiException(
        code: 'server.error',
        message: 'Unexpected response shape.',
        statusCode: response.statusCode,
      );
    }

    final success = decoded['success'] == true;
    if (!success || response.statusCode >= 400) {
      final error = decoded['error'];
      if (error is Map<String, dynamic>) {
        throw ApiException(
          code: error['code'] as String? ?? 'server.error',
          message: error['message'] as String? ?? 'Request failed.',
          statusCode: response.statusCode,
          details: error['details'] is List ? error['details'] as List : const [],
        );
      }
      throw ApiException(
        code: response.statusCode == 401
            ? 'auth.unauthenticated'
            : 'server.error',
        message: 'Request failed.',
        statusCode: response.statusCode,
      );
    }

    return decoded['data'];
  }

  List<Map<String, dynamic>> _asObjectList(Object? data) {
    if (data == null) {
      return const [];
    }
    if (data is! List) {
      throw const ApiException(
        code: 'server.error',
        message: 'Unexpected list shape.',
        statusCode: 200,
      );
    }
    return data
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
  }
}
