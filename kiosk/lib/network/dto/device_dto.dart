class DeviceDto {
  const DeviceDto({
    required this.id,
    required this.deviceId,
    required this.name,
    required this.status,
    required this.isActive,
    required this.maintenanceMode,
    this.terminalId,
    this.destinationId,
    this.softwareVersion,
    this.hardwareVersion,
    this.lastHeartbeatAt,
    this.online,
  });

  factory DeviceDto.fromJson(Map<String, dynamic> json) {
    return DeviceDto(
      id: json['id'] as int,
      deviceId: json['device_id'] as String,
      terminalId: json['terminal_id'] as String?,
      destinationId: json['destination_id'] as int?,
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? false,
      maintenanceMode: json['maintenance_mode'] as bool? ?? false,
      softwareVersion: json['software_version'] as String?,
      hardwareVersion: json['hardware_version'] as String?,
      lastHeartbeatAt: json['last_heartbeat_at'] as String?,
      online: json['online'] as bool?,
    );
  }

  final int id;
  final String deviceId;
  final String? terminalId;
  final int? destinationId;
  final String name;
  final String status;
  final bool isActive;
  final bool maintenanceMode;
  final String? softwareVersion;
  final String? hardwareVersion;
  final String? lastHeartbeatAt;
  final bool? online;

  bool get isMaintenance => maintenanceMode || status == 'maintenance';

  bool get isDisabled => !isActive || status == 'disabled';

  DeviceDto copyWith({
    String? status,
    bool? isActive,
    bool? maintenanceMode,
    String? lastHeartbeatAt,
    bool? online,
  }) {
    return DeviceDto(
      id: id,
      deviceId: deviceId,
      terminalId: terminalId,
      destinationId: destinationId,
      name: name,
      status: status ?? this.status,
      isActive: isActive ?? this.isActive,
      maintenanceMode: maintenanceMode ?? this.maintenanceMode,
      softwareVersion: softwareVersion,
      hardwareVersion: hardwareVersion,
      lastHeartbeatAt: lastHeartbeatAt ?? this.lastHeartbeatAt,
      online: online ?? this.online,
    );
  }
}
