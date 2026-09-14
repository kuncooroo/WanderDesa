<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use App\Models\Destination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.visit_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{destination_id: int, items: list<array{ticket_type_id: int, quantity: int, visit_date?: string|null}>}
     */
    public function quotePayload(): array
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

        return [
            'destination_id' => (int) $this->validated('destination_id'),
            'items' => $items,
        ];
    }
}
