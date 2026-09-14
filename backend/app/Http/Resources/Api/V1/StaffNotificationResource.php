<?php

namespace App\Http\Resources\Api\V1;

use App\Support\ApiTimestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;

/**
 * @mixin DatabaseNotification
 */
class StaffNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource->data) ? $this->resource->data : [];
        $data = Arr::except($data, [
            'payload',
            'raw_body',
            'signature',
            'secret',
            'webhook_secret',
            'activation_secret',
            'token',
        ]);

        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'kind' => $data['kind'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'data' => $data,
            'read_at' => ApiTimestamp::utc($this->resource->read_at),
            'created_at' => ApiTimestamp::utc($this->resource->created_at),
        ];
    }
}
