<?php

namespace App\Services;

use App\Models\SosDailyStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SosAnalyticsService
{
    public function recordOpen(): void
    {
        $date = CarbonImmutable::now('Asia/Damascus')->toDateString();

        DB::transaction(function () use ($date) {
            $stat = SosDailyStat::query()->lockForUpdate()->firstOrCreate(
                ['date' => $date],
                ['opens_count' => 0],
            );

            $stat->increment('opens_count');
        });
    }
}
