import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';

class IdleScreen extends StatelessWidget {
  const IdleScreen({
    super.key,
    required this.onStart,
    this.destinationName,
  });

  final VoidCallback onStart;
  final String? destinationName;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: KioskTokens.primary,
      child: InkWell(
        onTap: onStart,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(KioskTokens.gutter),
            child: Column(
              children: [
                Align(
                  alignment: Alignment.topLeft,
                  child: Text(
                    destinationName ?? KioskStrings.productName,
                    style: KioskTokens.title.copyWith(color: Colors.white),
                  ),
                ),
                const Spacer(),
                const Text(
                  KioskStrings.idleHint,
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 22,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  KioskStrings.idleCta,
                  style: KioskTokens.display,
                  textAlign: TextAlign.center,
                ),
                const Spacer(),
                Text(
                  KioskStrings.productName,
                  style: KioskTokens.bodyMuted.copyWith(color: Colors.white70),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
