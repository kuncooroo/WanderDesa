<?php

namespace Tests\Unit\Support\Reporting;

use App\Support\Reporting\ReportWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class ReportWindowTest extends TestCase
{
    public function test_jakarta_day_converts_to_utc_bounds(): void
    {
        $window = ReportWindow::forDate('2026-09-14', 'Asia/Jakarta');

        $this->assertSame('2026-09-13T17:00:00Z', $window->startUtc->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-09-14T16:59:59Z', $window->endUtc->format('Y-m-d\TH:i:s\Z'));
        $this->assertTrue($window->isSingleDay());
    }

    public function test_rejects_inverted_and_oversized_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReportWindow::forRange('2026-09-14', '2026-09-13', 'Asia/Jakarta');
    }

    public function test_rejects_range_longer_than_93_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReportWindow::forRange('2026-01-01', '2026-04-05', 'Asia/Jakarta');
    }

    public function test_allows_93_inclusive_days(): void
    {
        $window = ReportWindow::forRange('2026-01-01', '2026-04-03', 'Asia/Jakarta');
        $this->assertSame('2026-01-01', $window->fromLocal);
        $this->assertSame('2026-04-03', $window->toLocal);
        $this->assertInstanceOf(CarbonImmutable::class, $window->startUtc);
    }
}
