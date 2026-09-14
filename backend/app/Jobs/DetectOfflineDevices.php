<?php

namespace App\Jobs;

use App\Actions\Ops\NotifyOpsAlerts;
use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Setting;
use App\Support\Audit\AuditWriter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Audit when an active/maintenance kiosk crosses the heartbeat SLA.
 * Operational online/offline remains derived from last_heartbeat_at (docs/02 OFFLINE).
 */
final class DetectOfflineDevices implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 55;

    public function handle(AuditWriter $audit, NotifyOpsAlerts $notify): void
    {
        $staleSeconds = Setting::heartbeatStaleSeconds();
        $threshold = now()->subSeconds($staleSeconds);

        $candidates = Device::query()
            ->whereIn('status', [
                DeviceStatus::Active->value,
                DeviceStatus::Maintenance->value,
            ])
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<=', $threshold);
            })
            ->orderBy('id')
            ->get();

        foreach ($candidates as $device) {
            if ($this->alreadyFlaggedThisOutage($device)) {
                continue;
            }

            $audit->write(
                action: 'device.offline_detected',
                actorType: 'system',
                actorId: null,
                entityType: 'device',
                entityId: (int) $device->id,
                before: [
                    'last_heartbeat_at' => $device->last_heartbeat_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
                after: [
                    'online' => false,
                    'stale_after_seconds' => $staleSeconds,
                ],
                meta: [
                    'device_id' => $device->device_id,
                    'status' => $device->status instanceof DeviceStatus
                        ? $device->status->value
                        : (string) $device->status,
                ],
                destinationId: $device->destination_id,
            );

            Log::info('device.offline_detected', [
                'device_id' => $device->id,
                'stale_after_seconds' => $staleSeconds,
            ]);

            $notify->kioskOffline($device, $staleSeconds);
        }
    }

    private function alreadyFlaggedThisOutage(Device $device): bool
    {
        $query = AuditLog::query()
            ->where('action', 'device.offline_detected')
            ->where('entity_type', 'device')
            ->where('entity_id', $device->id);

        if ($device->last_heartbeat_at !== null) {
            $query->where('created_at', '>', $device->last_heartbeat_at);
        }

        return $query->exists();
    }
}
