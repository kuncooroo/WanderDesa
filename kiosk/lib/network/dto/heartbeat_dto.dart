class HeartbeatDto {
  const HeartbeatDto({
    required this.serverTime,
    required this.deviceStatus,
    required this.commands,
  });

  factory HeartbeatDto.fromJson(Map<String, dynamic> json) {
    final rawCommands = json['commands'];
    return HeartbeatDto(
      serverTime: json['server_time'] as String? ?? '',
      deviceStatus: json['device_status'] as String? ?? '',
      commands: rawCommands is List
          ? rawCommands.map((e) => e.toString()).toList()
          : const <String>[],
    );
  }

  final String serverTime;
  final String deviceStatus;
  final List<String> commands;

  bool get enterMaintenance =>
      commands.contains('enter_maintenance') || deviceStatus == 'maintenance';

  bool get isDisabled => deviceStatus == 'disabled';

  bool get isActive => deviceStatus == 'active';
}
