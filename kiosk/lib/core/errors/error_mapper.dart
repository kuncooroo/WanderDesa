import 'api_exception.dart';

/// Maps Laravel envelope codes to Bahasa Indonesia copy. Never invents PAID.
abstract final class ErrorMapper {
  static String toUserMessage(ApiException error) {
    if (error.isNetwork) {
      return KioskErrorCopy.network;
    }
    if (error.isInvalidActivation) {
      return KioskErrorCopy.activationInvalid;
    }
    if (error.requiresReactivation) {
      return KioskErrorCopy.tokenInvalid;
    }
    if (error.isDeviceInactive) {
      return KioskErrorCopy.deviceDisabled;
    }
    if (error.isMaintenance) {
      return KioskErrorCopy.maintenance;
    }
    if (error.code == 'order.state_conflict') {
      return KioskErrorCopy.orderExpired;
    }
    return error.message.isNotEmpty ? error.message : KioskErrorCopy.generic;
  }
}

/// Small copy set used from non-widget layers (avoids importing Flutter).
abstract final class KioskErrorCopy {
  static const network = 'Tidak ada koneksi ke server.';
  static const unknownPayment =
      'Status belum diketahui — jangan bayar dua kali.';
  static const paymentFailed = 'Pembayaran tidak berhasil.';
  static const printFailed =
      'Pembayaran berhasil, cetak gagal. Minta cetak ulang di loket.';
  static const orderExpired = 'Pesanan kedaluwarsa. Mulai lagi dari pilihan tiket.';
  static const activationInvalid =
      'Kode aktivasi tidak valid atau kedaluwarsa.';
  static const tokenInvalid =
      'Sesi perangkat tidak valid. Aktivasi ulang diperlukan.';
  static const deviceDisabled = 'Kiosk dinonaktifkan. Hubungi petugas.';
  static const maintenance = 'Kiosk dalam pemeliharaan.';
  static const generic = 'Terjadi kesalahan. Minta bantuan petugas.';
}
