import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_status_screen.dart';

class MaintenanceScreen extends StatelessWidget {
  const MaintenanceScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const KioskStatusScreen(
      icon: Icons.handyman_outlined,
      iconColor: KioskTokens.warning,
      title: KioskStrings.maintenanceTitle,
      body: KioskStrings.maintenanceBody,
      secondaryLabel: KioskStrings.homeHelp,
    );
  }
}
