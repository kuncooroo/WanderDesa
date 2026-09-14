<?php

namespace App\Http\Requests\Api\V1\Reports;

use App\Enums\PaymentStatus;
use App\Support\Reporting\ReportFilters;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentsReportRequest extends ReportQueryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->destinationRules(),
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(PaymentStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->input('from');
            $to = $this->input('to');
            $fromSet = is_string($from) && $from !== '';
            $toSet = is_string($to) && $to !== '';

            if ($fromSet !== $toSet) {
                $validator->errors()->add(
                    $fromSet ? 'to' : 'from',
                    'Both from and to dates are required when filtering by range.',
                );

                return;
            }

            $this->validateInclusiveRange($validator, $this->fromDate(), $this->toDate());
        });
    }

    public function fromDate(): string
    {
        $from = $this->input('from');

        return is_string($from) && $from !== '' ? $from : $this->defaultToday();
    }

    public function toDate(): string
    {
        $to = $this->input('to');

        return is_string($to) && $to !== '' ? $to : $this->fromDate();
    }

    public function statusFilter(): ?string
    {
        $status = $this->validated('status') ?? null;

        return is_string($status) && $status !== '' ? $status : null;
    }

    public function reportFilters(): ReportFilters
    {
        return $this->filters($this->fromDate(), $this->toDate(), $this->statusFilter());
    }
}
