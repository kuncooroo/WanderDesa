import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class SessionTimeoutModal extends StatelessWidget {
  const SessionTimeoutModal({
    super.key,
    required this.onContinue,
  });

  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.overlay,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Material(
            color: KioskTokens.surfaceRaised,
            borderRadius: BorderRadius.circular(KioskTokens.radius),
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    KioskStrings.timeoutTitle,
                    style: KioskTokens.title,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 24),
                  KioskPrimaryButton(
                    label: KioskStrings.timeoutContinue,
                    onPressed: onContinue,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
