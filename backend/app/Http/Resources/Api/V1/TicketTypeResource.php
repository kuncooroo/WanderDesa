<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ValidityType;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketType
 */
class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'destination_id' => $this->resource->destination_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'currency' => $this->resource->currency,
            'unit_price' => $this->resource->unit_price,
            'tax_amount' => $this->resource->tax_amount,
            'service_fee_amount' => $this->resource->service_fee_amount,
            'validity_type' => $this->resource->validity_type instanceof ValidityType
                ? $this->resource->validity_type->value
                : $this->resource->validity_type,
            'validity_days' => $this->resource->validity_days,
            'valid_from_time' => $this->formatTime($this->resource->valid_from_time),
            'valid_until_time' => $this->formatTime($this->resource->valid_until_time),
            'max_per_order' => $this->resource->max_per_order,
            'is_active' => $this->resource->is_active,
        ];
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return (string) $value;
    }
}
