import 'package:flutter/material.dart';

import '../../shared/l10n/kiosk_strings.dart';
import '../../shared/theme/kiosk_theme.dart';
import '../../shared/widgets/kiosk_primary_button.dart';

class ActivationScreen extends StatefulWidget {
  const ActivationScreen({
    super.key,
    required this.onSubmit,
    this.initialDeviceId,
    this.errorMessage,
    this.busy = false,
  });

  final Future<void> Function(String deviceId, String activationCode) onSubmit;
  final String? initialDeviceId;
  final String? errorMessage;
  final bool busy;

  @override
  State<ActivationScreen> createState() => _ActivationScreenState();
}

class _ActivationScreenState extends State<ActivationScreen> {
  late final TextEditingController _deviceId;
  late final TextEditingController _code;
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _deviceId = TextEditingController(text: widget.initialDeviceId ?? '');
    _code = TextEditingController();
  }

  @override
  void dispose() {
    _deviceId.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (widget.busy) {
      return;
    }
    if (_formKey.currentState?.validate() != true) {
      return;
    }
    await widget.onSubmit(_deviceId.text, _code.text);
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: KioskTokens.surface,
      child: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 640),
            child: Padding(
              padding: const EdgeInsets.all(KioskTokens.gutter),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      KioskStrings.activationTitle,
                      style: KioskTokens.headline,
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      KioskStrings.activationBody,
                      style: KioskTokens.bodyMuted,
                    ),
                    const SizedBox(height: 28),
                    TextFormField(
                      controller: _deviceId,
                      enabled: !widget.busy,
                      style: KioskTokens.body,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: KioskStrings.deviceIdLabel,
                        filled: true,
                        fillColor: KioskTokens.surfaceRaised,
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return KioskStrings.deviceIdLabel;
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _code,
                      enabled: !widget.busy,
                      style: KioskTokens.body,
                      obscureText: true,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: const InputDecoration(
                        labelText: KioskStrings.activationCodeLabel,
                        filled: true,
                        fillColor: KioskTokens.surfaceRaised,
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return KioskStrings.activationCodeLabel;
                        }
                        return null;
                      },
                    ),
                    if (widget.errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        widget.errorMessage!,
                        style: KioskTokens.body.copyWith(
                          color: KioskTokens.danger,
                        ),
                      ),
                    ],
                    const SizedBox(height: 28),
                    KioskPrimaryButton(
                      label: widget.busy
                          ? KioskStrings.activating
                          : KioskStrings.activateCta,
                      onPressed: widget.busy ? null : _submit,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
