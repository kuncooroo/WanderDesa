/// Paths relative to `/api/v1` (docs/08).
abstract final class ApiPaths {
  static const activate = '/kiosks/activate';
  static const heartbeat = '/kiosks/heartbeat';
  static const me = '/kiosks/me';
  static const config = '/kiosks/me/config';
  static const destinations = '/destinations';
  static const quote = '/pricing/quote';
  static const orders = '/orders';

  static String ticketTypes(int destinationId) =>
      '/destinations/$destinationId/ticket-types';

  static String order(int id) => '/orders/$id';

  static String cancelOrder(int id) => '/orders/$id/cancel';

  static String orderPayments(int orderId) => '/orders/$orderId/payments';

  static String payment(int id) => '/payments/$id';

  static String paymentRefresh(int id) => '/payments/$id/refresh';

  static String orderTickets(int orderId) => '/orders/$orderId/tickets';

  static String printPayload(String ticketCode) =>
      '/tickets/$ticketCode/print-payload';

  static String printAck(String ticketCode) =>
      '/tickets/$ticketCode/print-ack';
}
