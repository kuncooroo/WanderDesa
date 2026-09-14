<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Enums\ValidityType;
use App\Models\Destination;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TicketType $ticketType */
        $ticketType = $this->route('ticket_type');

        $destinationId = $this->input('destination_id', $ticketType->destination_id);

        return [
            'destination_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists(Destination::class, 'id')->whereNull('deleted_at'),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('ticket_types', 'code')
                    ->where(fn ($query) => $query->where('destination_id', $destinationId))
                    ->ignore($ticketType->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:IDR'],
            'unit_price' => ['sometimes', 'required', 'integer', 'min:0'],
            'tax_amount' => ['sometimes', 'integer', 'min:0'],
            'service_fee_amount' => ['sometimes', 'integer', 'min:0'],
            'validity_type' => ['sometimes', 'string', Rule::in(ValidityType::values())],
            'validity_days' => [
                Rule::requiredIf(function () use ($ticketType): bool {
                    $type = $this->input('validity_type', $ticketType->validity_type?->value);

                    return $type === ValidityType::DaysFromIssue->value;
                }),
                'nullable',
                'integer',
                'min:1',
            ],
            'valid_from_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'valid_until_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'max_per_order' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ticketTypePayload(): array
    {
        return $this->safe()->only([
            'destination_id',
            'code',
            'name',
            'description',
            'currency',
            'unit_price',
            'tax_amount',
            'service_fee_amount',
            'validity_type',
            'validity_days',
            'valid_from_time',
            'valid_until_time',
            'max_per_order',
            'is_active',
        ]);
    }
}
