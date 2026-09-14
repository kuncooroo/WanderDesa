<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reporting\ExportDailySalesReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\DailySalesReportRequest;
use App\Http\Requests\Api\V1\Reports\PaymentsReportRequest;
use App\Http\Requests\Api\V1\Reports\TicketUsageReportRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function dailySales(DailySalesReportRequest $request, ReportingQueryService $reports): JsonResponse
    {
        $this->authorizeStaffView($request);

        return ApiResponse::success($reports->dailySales($request->reportFilters()));
    }

    public function payments(PaymentsReportRequest $request, ReportingQueryService $reports): JsonResponse
    {
        $this->authorizeStaffView($request);

        return ApiResponse::success($reports->paymentsByStatus($request->reportFilters()));
    }

    public function ticketUsage(TicketUsageReportRequest $request, ReportingQueryService $reports): JsonResponse
    {
        $this->authorizeStaffView($request);

        return ApiResponse::success($reports->ticketUsage($request->reportFilters()));
    }

    public function exportDailySales(
        DailySalesReportRequest $request,
        ExportDailySalesReport $export,
    ): StreamedResponse {
        $user = $this->requireStaff($request);
        $this->authorize('export', Report::class);

        $result = $export->handle($user, $request->reportFilters(), $request);

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['csv'];
            },
            $result['filename'],
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    private function authorizeStaffView(Request $request): void
    {
        $this->requireStaff($request);
        $this->authorize('viewAny', Report::class);
    }

    private function requireStaff(Request $request): User
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff users may access reports.');
        }

        return $principal;
    }
}
