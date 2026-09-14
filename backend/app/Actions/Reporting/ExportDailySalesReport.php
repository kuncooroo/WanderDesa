<?php

namespace App\Actions\Reporting;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use App\Support\Audit\AuditWriter;
use App\Support\Authorization\Authorizer;
use App\Support\Reporting\ReportFilters;
use Illuminate\Http\Request;

final class ExportDailySalesReport
{
    public function __construct(
        private readonly ReportingQueryService $reports,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return array{csv: string, filename: string, sales: array<string, mixed>}
     */
    public function handle(User $actor, ReportFilters $filters, ?Request $request = null): array
    {
        Authorizer::authorize($actor, PermissionName::ReportsExport);

        $sales = $this->reports->dailySales($filters);
        $products = $this->reports->salesByProduct($filters);
        $csv = $this->toCsv($sales, $products);
        $filename = $this->filename($filters);

        $this->audit->write(
            action: 'report.exported',
            actorType: 'user',
            actorId: (int) $actor->id,
            entityType: 'report',
            entityId: null,
            meta: [
                'report' => 'sales.daily',
                'from' => $filters->window->fromLocal,
                'to' => $filters->window->toLocal,
                'timezone' => $filters->window->timezone,
                'destination_id' => $filters->destinationId,
                'cashier_user_id' => $filters->cashierUserId,
                'row_count' => count($sales['channels']),
                'product_row_count' => count($products['rows']),
                'gross_sales' => $sales['totals']['gross_sales'],
                'discount_total' => $sales['totals']['discount_total'],
                'refund_total' => $sales['totals']['refund_total'],
                'net_sales' => $sales['totals']['net_sales'],
            ],
            request: $request,
            destinationId: $filters->destinationId,
        );

        return [
            'csv' => $csv,
            'filename' => $filename,
            'sales' => $sales,
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $products
     */
    private function toCsv(array $sales, array $products): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, [
            'section',
            'from',
            'to',
            'timezone',
            'destination_id',
            'cashier_user_id',
            'channel',
            'order_count',
            'gross_sales_idr',
            'discount_total_idr',
            'refund_total_idr',
            'net_sales_idr',
            'product_code',
            'product_name',
            'qty_sold',
            'omzet_idr',
            'currency',
        ]);

        /** @var list<array{channel: string, order_count: int, gross_sales: int, discount_total: int}> $channels */
        $channels = $sales['channels'];
        $totals = $sales['totals'];

        foreach ($channels as $row) {
            fputcsv($handle, [
                'channel',
                $sales['from'],
                $sales['to'],
                $sales['timezone'],
                $sales['destination_id'] ?? '',
                $sales['cashier_user_id'] ?? '',
                $row['channel'],
                $row['order_count'],
                $row['gross_sales'],
                $row['discount_total'],
                '',
                '',
                '',
                '',
                '',
                '',
                $sales['currency'],
            ]);
        }

        fputcsv($handle, [
            'summary',
            $sales['from'],
            $sales['to'],
            $sales['timezone'],
            $sales['destination_id'] ?? '',
            $sales['cashier_user_id'] ?? '',
            'TOTAL',
            $totals['order_count'],
            $totals['gross_sales'],
            $totals['discount_total'],
            $totals['refund_total'],
            $totals['net_sales'],
            '',
            '',
            '',
            '',
            $sales['currency'],
        ]);

        /** @var list<array{ticket_type_code: string, product_name: string, qty_sold: int, omzet: int}> $productRows */
        $productRows = $products['rows'];

        foreach ($productRows as $row) {
            fputcsv($handle, [
                'product',
                $products['from'],
                $products['to'],
                $products['timezone'],
                $products['destination_id'] ?? '',
                $products['cashier_user_id'] ?? '',
                '',
                '',
                '',
                '',
                '',
                '',
                $row['ticket_type_code'],
                $row['product_name'],
                $row['qty_sold'],
                $row['omzet'],
                $products['currency'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private function filename(ReportFilters $filters): string
    {
        $window = $filters->window;
        $stamp = $window->isSingleDay()
            ? $window->fromLocal
            : $window->fromLocal.'_'.$window->toLocal;

        return 'wanderdesa-sales-'.$stamp.'.csv';
    }
}
