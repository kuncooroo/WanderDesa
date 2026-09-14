<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use App\Models\Destination;
use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    use RejectsClientMoneyFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectClientMoneyFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination_id' => [
                'required',
                'integer',
                Rule::exists(Destination::class, 'id')->whereNull('deleted_at'),
            ],
            'visitor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(Visitor::class, 'id'),
            ],
            'customer_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.visit_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{
     *     destination_id: int,
     *     visitor_id?: int|null,
     *     customer_note?: string|null,
     *     items: list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>
     * }
     */
    public function orderPayload(): array
    {
        /** @var list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}> $items */
        $items = array_map(
            static function (array $item): array {
                $line = [
                    'ticket_type_id' => (int) $item['ticket_type_id'],
                    'quantity' => (int) $item['quantity'],
                ];

                if (array_key_exists('visit_date', $item)) {
                    $line['visit_date'] = $item['visit_date'];
                }

                return $line;
            },
            $this->validated('items'),
        );

        $payload = [
            'destination_id' => (int) $this->validated('destination_id'),
            'items' => $items,
        ];

        if ($this->exists('visitor_id')) {
            $visitorId = $this->validated('visitor_id');
            $payload['visitor_id'] = $visitorId === null ? null : (int) $visitorId;
        }

        if ($this->exists('customer_note')) {
            $payload['customer_note'] = $this->validated('customer_note');
        }

        return $payload;
    }
}
