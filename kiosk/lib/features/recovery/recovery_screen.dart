import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

class RecoveryScreen extends StatelessWidget {
  const RecoveryScreen({super.key, this.orderNumber});

  final String? orderNumber;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(color: KioskTokens.primary),
            const SizedBox(height: 24),
            const Text(
              KioskStrings.recoveryTitle,
              style: KioskTokens.headline,
            ),
            if (orderNumber != null) ...[
              const SizedBox(height: 8),
              Text(orderNumber!, style: KioskTokens.bodyMuted),
            ],
          ],
        ),
      ),
    );
  }
}
