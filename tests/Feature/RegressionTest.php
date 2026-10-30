<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\MetaConnection;
use App\Models\User;
use App\Services\MetaAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->putJson('/users/'.$admin->id, ['role' => 'viewer'])->assertStatus(422);
        $this->assertSame('admin', $admin->fresh()->role);
        User::factory()->create();
        $this->putJson('/users/'.$admin->id, ['role' => 'viewer'])->assertOk();
    }

    public function test_metrics_filter_actual_data_and_keep_crm_and_report_conversions_separate(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::create(['date' => '2026-10-01', 'name' => 'Actual', 'platform' => 'Instagram', 'budget' => 100, 'revenue' => 500, 'leads_count' => 9, 'closings_count' => 4]);
        $lead = Lead::create(['campaign_id' => $campaign->id, 'name' => 'Prospect']);
        $this->actingAs($user)->postJson('/leads/'.$lead->id.'/close', ['date' => '2026-10-02', 'value' => 200, 'status' => 'Lunas'])->assertOk();
        $this->postJson('/leads/'.$lead->id.'/close', ['date' => '2026-10-02', 'value' => 300, 'status' => 'Lunas'])->assertOk();
        $this->assertDatabaseCount('closings', 1);
        $this->getJson('/metrics?from=2026-10-01&to=2026-10-31')->assertJsonPath('metrics.revenue', 500)->assertJsonPath('metrics.closings', 4);
        $this->getJson('/metrics?from=2026-10-01&to=2026-10-31&conversion_source=crm')->assertJsonPath('metrics.revenue', 300)->assertJsonPath('metrics.closings', 1)->assertJsonPath('metrics.leads', 1);
        $this->getJson('/metrics?from=2026-11-01&to=2026-11-30')->assertJsonPath('metrics.revenue', 0)->assertJsonCount(0, 'campaigns');
        $this->deleteJson('/leads/'.$lead->id)->assertNoContent();
        $this->getJson('/metrics?conversion_source=crm')->assertJsonPath('metrics.revenue', 0)->assertJsonPath('metrics.closings', 0);
    }

    public function test_meta_sync_reads_all_pages_and_is_repeatable(): void
    {
        $connection = MetaConnection::create(['ad_account_id' => '123', 'access_token' => str_repeat('x', 30)]);
        $row = ['campaign_id' => '1', 'campaign_name' => 'First', 'date_start' => '2026-10-01', 'spend' => '100', 'clicks' => 99];
        Http::fakeSequence()->push(['data' => [$row], 'paging' => ['next' => 'https://graph.facebook.com/next', 'cursors' => ['after' => 'cursor2']]])->push(['data' => [array_replace($row, ['campaign_id' => '2'])]])->push(['data' => [$row]]);
        $this->assertSame(2, app(MetaAdsService::class)->sync($connection, '2026-10-01', '2026-10-31'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ($request['after'] ?? null) === 'cursor2');
        $this->assertDatabaseCount('campaigns', 2);
        $this->assertDatabaseHas('campaigns', ['external_id' => '1-2026-10-01', 'link_clicks' => 0]);
        app(MetaAdsService::class)->sync($connection, '2026-10-01', '2026-10-31');
        $this->assertDatabaseCount('campaigns', 2);
    }

    public function test_failed_meta_page_does_not_persist_partial_sync(): void
    {
        $connection = MetaConnection::create(['ad_account_id' => '123', 'access_token' => str_repeat('x', 30)]);
        Http::fakeSequence()->push(['data' => [['campaign_id' => '1', 'campaign_name' => 'First', 'date_start' => '2026-10-01']], 'paging' => ['next' => 'next', 'cursors' => ['after' => 'next']]])->push(['error' => ['message' => 'Expired']], 400);
        try {
            app(MetaAdsService::class)->sync($connection, '2026-10-01', '2026-10-31');
            $this->fail('Expected failed sync');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Expired', $exception->getMessage());
        }
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertNull($connection->fresh()->last_synced_at);
    }
}
