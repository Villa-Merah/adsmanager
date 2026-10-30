<?php

use App\Models\MetaConnection;
use App\Services\MetaAdsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meta-ads:sync {--since=} {--until=}', function (MetaAdsService $service) {
    $connection = MetaConnection::first();
    if (! $connection) {
        $this->error('Akun Meta Ads belum dihubungkan.');

        return 1;
    }
    $until = $this->option('until') ?: now()->toDateString();
    $since = $this->option('since') ?: now()->subDays(7)->toDateString();
    try {
        $count = $service->sync($connection, $since, $until);
        $this->info("$count baris berhasil disinkronkan.");

        return 0;
    } catch (Throwable $e) {
        $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);
        $this->error($e->getMessage());

        return 1;
    }
})->purpose('Sinkronkan insight campaign dari Meta Ads');

Schedule::command('meta-ads:sync')->dailyAt('02:00')->withoutOverlapping();
