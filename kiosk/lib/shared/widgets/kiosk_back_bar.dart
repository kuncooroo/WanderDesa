import 'package:flutter/material.dart';

import '../theme/kiosk_theme.dart';

class KioskBackBar extends StatelessWidget {
  const KioskBackBar({
    super.key,
    required this.title,
    this.onBack,
  });

  final String title;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (onBack != null)
          IconButton(
            onPressed: onBack,
            tooltip: 'Kembali',
            style: IconButton.styleFrom(
              foregroundColor: KioskTokens.ink,
              minimumSize: const Size(48, 48),
            ),
            icon: const Text(
              '<',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w400,
                height: 1,
                color: KioskTokens.ink,
              ),
            ),
          )
        else
          const SizedBox(width: 48),
        const SizedBox(width: 8),
        Expanded(
          child: Text(title, style: KioskTokens.title),
        ),
      ],
    );
  }
}
