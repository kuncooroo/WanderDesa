/// Display helper for IDR integers from the server. Not a pricing engine.
String formatIdr(int amount) {
  final negative = amount < 0;
  var digits = amount.abs().toString();
  final groups = <String>[];
  while (digits.length > 3) {
    groups.insert(0, digits.substring(digits.length - 3));
    digits = digits.substring(0, digits.length - 3);
  }
  groups.insert(0, digits);
  return 'Rp ${negative ? '-' : ''}${groups.join('.')}';
}
