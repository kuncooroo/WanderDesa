<?php

namespace App\Http\Requests\Api\V1\Reports;

use App\Support\Reporting\ReportFilters;
use Illuminate\Validation\Validator;

class DailySalesReportRequest extends ReportQueryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->destinationRules(),
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = $this->reportDate();
            $this->validateInclusiveRange($validator, $date, $date);
        });
    }

    public function reportDate(): string
    {
        $date = $this->input('date');

        return is_string($date) && $date !== '' ? $date : $this->defaultToday();
    }

    public function reportFilters(): ReportFilters
    {
        $date = $this->reportDate();

        return $this->filters($date, $date);
    }
}
