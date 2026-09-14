import 'package:flutter/material.dart';

import '../theme/kiosk_theme.dart';

class KioskStatusScreen extends StatelessWidget {
  const KioskStatusScreen({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.iconColor = KioskTokens.inkMuted,
    this.actionLabel,
    this.onAction,
    this.secondaryLabel,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final String body;
  final String? actionLabel;
  final VoidCallback? onAction;
  final String? secondaryLabel;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 88, color: iconColor),
              const SizedBox(height: 24),
              Text(
                title,
                style: KioskTokens.headline,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Text(
                body,
                style: KioskTokens.bodyMuted,
                textAlign: TextAlign.center,
              ),
              if (actionLabel != null) ...[
                const SizedBox(height: 32),
                SizedBox(
                  height: KioskTokens.primaryCtaMin,
                  width: 360,
                  child: FilledButton(
                    onPressed: onAction,
                    child: Text(actionLabel!),
                  ),
                ),
              ],
              if (secondaryLabel != null) ...[
                const SizedBox(height: 16),
                Text(secondaryLabel!, style: KioskTokens.bodyMuted),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
