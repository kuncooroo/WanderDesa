import 'package:flutter/material.dart';

import '../../network/dto/destination_dto.dart';
import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_back_bar.dart';

class DestinationBrowseScreen extends StatelessWidget {
  const DestinationBrowseScreen({
    super.key,
    required this.destinations,
    required this.onSelect,
    this.onBack,
  });

  final List<DestinationDto> destinations;
  final ValueChanged<DestinationDto> onSelect;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(KioskTokens.gutter),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              KioskBackBar(title: KioskStrings.destinationsTitle, onBack: onBack),
              const SizedBox(height: 24),
              if (destinations.isEmpty)
                const Expanded(
                  child: Center(
                    child: Text(
                      KioskStrings.destinationsEmpty,
                      style: KioskTokens.bodyMuted,
                    ),
                  ),
                )
              else
                Expanded(
                  child: GridView.count(
                    crossAxisCount: 2,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    childAspectRatio: 2.4,
                    children: [
                      for (final destination in destinations)
                        FilledButton(
                          onPressed: () => onSelect(destination),
                          child: Text(destination.name),
                        ),
                    ],
                  ),
                ),
              const Text(
                KioskStrings.homeHelp,
                style: KioskTokens.bodyMuted,
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
