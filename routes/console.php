<?php

use App\Models\MetaConnection;
use App\Services\MetaAdsService;
use App\Support\BranchAccess;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meta-ads:sync {--since=} {--until=} {--branch=}', function (MetaAdsService $service) {
    $branch = $this->option('branch');
    if ($branch && ! in_array($branch, BranchAccess::BRANCHES, true)) {
        $this->error('Cabang tidak valid.');

        return 1;
    }
    $connections = MetaConnection::whereNotNull('branch')->when($branch, fn ($q) => $q->where('branch', $branch))->get();
    if ($connections->isEmpty()) {
        $this->error('Belum ada koneksi Meta dengan cabang yang ditetapkan.');

        return 1;
    }
    $until = $this->option('until') ?: now()->toDateString();
    $since = $this->option('since') ?: now()->subDays(7)->toDateString();
    $failed = false;
    foreach ($connections as $connection) {
        try {
            $count = $service->sync($connection, $since, $until);
            $this->info($connection->branch.': '.$count.' baris disinkronkan.');
        } catch (Throwable $e) {
            $failed = true;
            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);
            $this->error($connection->branch.': '.$e->getMessage());
        }
    }

    return $failed ? 1 : 0;
})->purpose('Sinkronkan akun Meta Ads per cabang');

Schedule::command('meta-ads:sync')->dailyAt('02:00')->withoutOverlapping();
