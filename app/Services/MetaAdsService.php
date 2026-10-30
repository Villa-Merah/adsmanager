<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\MetaConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaAdsService
{
    public function sync(MetaConnection $connection, string $since, string $until): int
    {
        if (! $connection->branch) {
            throw new RuntimeException('Tetapkan cabang akun Meta Ads sebelum sinkronisasi.');
        }
        $token = $connection->access_token ?: config('services.meta_ads.access_token');
        if (! $token) {
            throw new RuntimeException('Access token Meta Ads belum dikonfigurasi.');
        }
        $version = config('services.meta_ads.version', 'v25.0');
        $account = 'act_'.preg_replace('/^act_/', '', $connection->ad_account_id);
        $url = "https://graph.facebook.com/{$version}/{$account}/insights";
        $params = [
            'access_token' => $token, 'level' => 'campaign', 'date_increment' => 1, 'time_range' => json_encode(['since' => $since, 'until' => $until]),
            'fields' => 'campaign_id,campaign_name,objective,spend,impressions,reach,clicks,actions,action_values,date_start,date_stop',
            'limit' => 500,
        ];
        $rows = [];
        $seen = [];
        do {
            $response = Http::timeout(60)->get($url, $params);
            if ($response->failed()) {
                throw new RuntimeException($response->json('error.message') ?: 'Sinkronisasi Meta Ads gagal.');
            }
            $page = $response->json('data');
            if (! is_array($page)) {
                throw new RuntimeException('Respons Meta Ads tidak valid.');
            }
            array_push($rows, ...$page);
            $after = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
            if ($response->json('paging.next') && ! $after) {
                throw new RuntimeException('Cursor pagination Meta Ads tidak tersedia.');
            }
            if ($after && isset($seen[$after])) {
                throw new RuntimeException('Cursor pagination Meta Ads berulang.');
            }
            if ($after) {
                $seen[$after] = true;
                $params['after'] = $after;
            }
        } while ($after);

        return DB::transaction(function () use ($rows, $connection) {
            $count = 0;
            foreach ($rows as $row) {
                $action = fn (string $type) => collect($row['actions'] ?? [])->firstWhere('action_type', $type)['value'] ?? 0;
                $value = fn (string $type) => collect($row['action_values'] ?? [])->firstWhere('action_type', $type)['value'] ?? 0;
                Campaign::updateOrCreate(['external_id' => $connection->branch.'-'.$row['campaign_id'].'-'.$row['date_start']], [
                    'branch' => $connection->branch, 'date' => $row['date_start'], 'name' => $row['campaign_name'], 'objective' => $row['objective'] ?? 'Unknown', 'platform' => 'Meta Ads',
                    'budget' => $row['spend'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'reach' => $row['reach'] ?? 0, 'link_clicks' => $action('link_click'),
                    'leads_count' => $action('lead'), 'closings_count' => $action('purchase'), 'revenue' => $value('purchase'), 'status' => 'Aktif', 'source' => 'meta_ads',
                ]);
                $count++;
            }
            $connection->update(['status' => 'connected', 'last_synced_at' => now(), 'last_error' => null]);

            return $count;
        });
    }
}
