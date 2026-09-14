<?php

namespace App\Http\Requests\Api\V1\Reports;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use App\Models\Destination;
use App\Services\Reporting\ReportingQueryService;
use App\Support\Reporting\ReportFilters;
use App\Support\Reporting\ReportWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ReportQueryRequest extends FormRequest
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
    protected function destinationRules(): array
    {
        return [
            'destination_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(Destination::class, 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function destinationId(): ?int
    {
        $value = $this->validated('destination_id') ?? null;

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    public function timezone(): string
    {
        return app(ReportingQueryService::class)->timezoneFor($this->destinationId());
    }

    public function defaultToday(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    protected function validateInclusiveRange(Validator $validator, string $from, string $to): void
    {
        if ($from === '' || $to === '') {
            return;
        }

        if ($to < $from) {
            $validator->errors()->add('to', 'The to date must be on or after the from date.');

            return;
        }

        try {
            ReportWindow::forRange($from, $to, $this->timezone());
        } catch (\InvalidArgumentException $e) {
            $validator->errors()->add('to', $e->getMessage());
        }
    }

    public function filters(?string $from = null, ?string $to = null, ?string $paymentStatus = null): ReportFilters
    {
        return app(ReportingQueryService::class)->filters(
            $from ?? $this->defaultToday(),
            $to ?? $from ?? $this->defaultToday(),
            $this->destinationId(),
            $paymentStatus,
        );
    }
}
