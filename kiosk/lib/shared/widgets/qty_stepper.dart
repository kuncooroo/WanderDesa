import 'package:flutter/material.dart';

import '../theme/kiosk_theme.dart';

class QtyStepper extends StatelessWidget {
  const QtyStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.max = 20,
  });

  final int value;
  final int max;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _Hit(
          label: '−',
          onTap: value > 0 ? () => onChanged(value - 1) : null,
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Text('$value', style: KioskTokens.title),
        ),
        _Hit(
          label: '+',
          onTap: value < max ? () => onChanged(value + 1) : null,
        ),
      ],
    );
  }
}

class _Hit extends StatelessWidget {
  const _Hit({required this.label, this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 56,
      height: 56,
      child: FilledButton(
        onPressed: onTap,
        style: FilledButton.styleFrom(
          padding: EdgeInsets.zero,
          backgroundColor: KioskTokens.primary,
        ),
        child: Text(label, style: const TextStyle(fontSize: 28)),
      ),
    );
  }
}
