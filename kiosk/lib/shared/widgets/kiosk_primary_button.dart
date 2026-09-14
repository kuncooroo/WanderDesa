import 'package:flutter/material.dart';

import '../theme/kiosk_theme.dart';

class KioskPrimaryButton extends StatelessWidget {
  const KioskPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: KioskTokens.primaryCtaMin,
      child: FilledButton(
        onPressed: onPressed,
        child: Text(label),
      ),
    );
  }
}
