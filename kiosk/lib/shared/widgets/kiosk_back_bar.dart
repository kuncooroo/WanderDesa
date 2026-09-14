import 'package:flutter/material.dart';

import '../l10n/kiosk_strings.dart';
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
          OutlinedButton(
            onPressed: onBack,
            child: const Text(KioskStrings.backCta),
          )
        else
          const SizedBox(width: 120),
        const SizedBox(width: 16),
        Expanded(
          child: Text(title, style: KioskTokens.title),
        ),
      ],
    );
  }
}
