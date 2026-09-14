import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

class PrintingScreen extends StatelessWidget {
  const PrintingScreen({
    super.key,
    required this.index,
    required this.total,
  });

  final int index;
  final int total;

  @override
  Widget build(BuildContext context) {
    final current = total == 0 ? 0 : index + 1;
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(color: KioskTokens.primary),
              const SizedBox(height: 24),
              const Text(KioskStrings.printingTitle, style: KioskTokens.headline),
              const SizedBox(height: 12),
              Text('$current / $total', style: KioskTokens.bodyMuted),
            ],
          ),
        ),
      ),
    );
  }
}
