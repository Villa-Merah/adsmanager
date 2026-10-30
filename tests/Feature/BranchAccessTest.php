<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\MetaConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(?string $branch, string $name): Campaign
    {
        return Campaign::create(['branch' => $branch, 'name' => $name, 'date' => '2026-10-01', 'platform' => 'Meta Ads', 'budget' => 100]);
    }

    public function test_branch_user_only_receives_own_dashboard_and_metrics(): void
    {
        $this->campaign('Bandung', 'Bandung Only');
        $other = $this->campaign('JakSel', 'JakSel Secret');
        $this->campaign(null, 'Unassigned Secret');
        Lead::create(['branch' => 'JakSel', 'campaign_id' => $other->id, 'name' => 'Private Lead']);
        $user = User::factory()->create(['role' => 'marketing', 'branch' => 'Bandung']);
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Bandung Only')->assertDontSee('JakSel Secret')->assertDontSee('Unassigned Secret')->assertDontSee('Private Lead');
        $this->getJson('/metrics')->assertJsonCount(1, 'campaigns')->assertJsonPath('campaigns.0.branch', 'Bandung')->assertJsonPath('metrics.budget', 100);
        $this->getJson('/metrics?branch=JakSel')->assertForbidden();
        $this->withHeader('X-Branch', 'JakPus')->getJson('/metrics')->assertForbidden();
    }

    public function test_guessed_other_branch_ids_cannot_be_changed_deleted_or_closed(): void
    {
        $campaign = $this->campaign('JakPus', 'Protected');
        $lead = Lead::create(['branch' => 'JakPus', 'campaign_id' => $campaign->id, 'name' => 'Protected Lead']);
        $user = User::factory()->create(['role' => 'marketing', 'branch' => 'Bandung']);
        $this->actingAs($user)->putJson('/campaigns/'.$campaign->id, [])->assertNotFound();
        $this->deleteJson('/campaigns/'.$campaign->id)->assertNotFound();
        $this->putJson('/leads/'.$lead->id, ['name' => 'Stolen'])->assertNotFound();
        $this->deleteJson('/leads/'.$lead->id)->assertNotFound();
        $this->postJson('/leads/'.$lead->id.'/close', ['date' => '2026-10-02', 'value' => 100, 'status' => 'Lunas'])->assertNotFound();
        $this->assertDatabaseCount('campaigns', 1);
        $this->assertDatabaseCount('closings', 0);
    }

    public function test_branch_writes_enforce_branch_and_related_campaign_and_assignee(): void
    {
        $campaign = $this->campaign('JakSel', 'Other');
        $other = User::factory()->create(['role' => 'marketing', 'branch' => 'JakSel']);
        $user = User::factory()->create(['role' => 'marketing', 'branch' => 'Bandung']);
        $this->actingAs($user)->postJson('/leads', ['name' => 'New', 'status' => 'Baru', 'campaign_id' => $campaign->id])->assertUnprocessable()->assertJsonValidationErrors('campaign_id');
        $this->postJson('/leads', ['name' => 'New', 'status' => 'Baru', 'assigned_to' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->postJson('/leads', ['name' => 'New', 'status' => 'Baru', 'branch' => 'JakSel'])->assertForbidden();
        $this->postJson('/leads', ['name' => 'New', 'status' => 'Baru'])->assertCreated()->assertJsonPath('branch', 'Bandung');
        $this->postJson('/users', ['name' => 'Other'])->assertForbidden();
        $this->postJson('/meta/sync', ['branch' => 'JakSel'])->assertForbidden();
    }

    public function test_unassigned_non_admin_has_no_access_to_legacy_or_branch_data(): void
    {
        $this->campaign(null, 'Legacy');
        $this->campaign('Bandung', 'Branch');
        $user = User::factory()->create(['role' => 'marketing', 'branch' => null]);
        $this->actingAs($user)->getJson('/metrics')->assertJsonCount(0, 'campaigns');
        $this->postJson('/leads', ['name' => 'No branch', 'status' => 'Baru'])->assertUnprocessable();
    }

    public function test_admin_can_view_all_filter_and_assign_legacy_campaign_with_leads(): void
    {
        $campaign = $this->campaign(null, 'Legacy');
        $lead = Lead::create(['name' => 'Legacy Lead', 'campaign_id' => $campaign->id]);
        $this->campaign('JakSel', 'Other');
        $admin = User::factory()->create();
        $this->actingAs($admin)->getJson('/metrics')->assertJsonCount(2, 'campaigns');
        $payload = ['branch' => 'Bandung', 'date' => '2026-10-01', 'name' => 'Assigned', 'platform' => 'Meta Ads', 'objective' => 'Leads', 'budget' => 100, 'status' => 'Aktif'];
        $this->putJson('/campaigns/'.$campaign->id, $payload)->assertOk();
        $this->assertSame('Bandung', $lead->fresh()->branch);
        $this->getJson('/metrics?branch=Bandung')->assertJsonCount(1, 'campaigns');
        $this->postJson('/users', ['name' => 'Bandung User', 'email' => 'bdg@example.com', 'role' => 'marketing', 'branch' => 'Bandung', 'password' => 'Password123'])->assertCreated()->assertJsonPath('branch', 'Bandung');
        $this->postJson('/users', ['name' => 'Missing', 'email' => 'missing@example.com', 'role' => 'staff', 'password' => 'Password123'])->assertUnprocessable()->assertJsonValidationErrors('branch');
    }

    public function test_workbook_can_be_imported_independently_in_each_branch(): void
    {
        $admin = User::factory()->create();
        $content = file_get_contents(base_path('Dashboard_hasil iklan.xlsx'));
        foreach (['Bandung', 'JakSel', 'JakPus'] as $branch) {
            $file = UploadedFile::fake()->createWithContent('campaigns.xlsx', $content);
            $this->actingAs($admin)->post('/imports/campaigns', ['branch' => $branch, 'file' => $file], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('rows_imported', 2);
        }
        $this->assertDatabaseCount('campaigns', 6);
        $this->assertDatabaseCount('data_imports', 3);
    }

    public function test_meta_sync_and_disconnect_only_affect_requested_branch(): void
    {
        $admin = User::factory()->create(['role' => 'kacab', 'branch' => 'Bandung']);
        $bandung = MetaConnection::create(['branch' => 'Bandung', 'ad_account_id' => 'act_111', 'access_token' => str_repeat('x', 30)]);
        MetaConnection::create(['branch' => 'JakSel', 'ad_account_id' => 'act_222', 'access_token' => str_repeat('y', 30)]);
        Http::fake(['*' => Http::response(['data' => [['campaign_id' => '9', 'campaign_name' => 'Meta Bandung', 'date_start' => '2026-10-01']]])]);
        $this->actingAs($admin)->postJson('/meta/sync', ['branch' => 'Bandung', 'since' => '2026-10-01', 'until' => '2026-10-30'])->assertOk()->assertJsonPath('branch', 'Bandung');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/act_111/insights'));
        $this->assertDatabaseHas('campaigns', ['branch' => 'Bandung', 'external_id' => 'Bandung-9-2026-10-01']);
        $this->assertNull(MetaConnection::where('branch', 'JakSel')->first()->last_synced_at);
        $this->deleteJson('/meta/disconnect', ['branch' => 'Bandung'])->assertNoContent();
        $this->assertDatabaseHas('meta_connections', ['branch' => 'JakSel']);
        $this->assertDatabaseMissing('meta_connections', ['id' => $bandung->id]);
    }

    public function test_legacy_connection_requires_branch_manager_to_reconnect_with_supplied_token(): void
    {
        $connection = MetaConnection::create(['ad_account_id' => '123', 'access_token' => str_repeat('x', 30)]);
        $manager = User::factory()->create(['role' => 'kacab', 'branch' => 'JakPus']);
        $this->actingAs($manager)->postJson('/meta/connect', ['ad_account_id' => '123', 'account_name' => 'JakPus', 'access_token' => str_repeat('y', 30)])->assertOk()->assertJsonMissingPath('access_token');
        $this->assertSame(str_repeat('y', 30), $connection->fresh()->access_token);
        $this->assertSame('JakPus', $connection->fresh()->branch);
    }

    public function test_scheduled_sync_continues_with_other_branches_after_one_failure(): void
    {
        MetaConnection::create(['branch' => 'Bandung', 'ad_account_id' => 'act_111', 'access_token' => str_repeat('x', 30)]);
        MetaConnection::create(['branch' => 'JakPus', 'ad_account_id' => 'act_333', 'access_token' => str_repeat('y', 30)]);
        Http::fake([
            '*act_111/insights*' => Http::response(['error' => ['message' => 'Expired']], 400),
            '*act_333/insights*' => Http::response(['data' => [['campaign_id' => '8', 'campaign_name' => 'JakPus', 'date_start' => '2026-10-01']]]),
        ]);
        $this->artisan('meta-ads:sync', ['--since' => '2026-10-01', '--until' => '2026-10-30'])->assertExitCode(1);
        $this->assertDatabaseHas('campaigns', ['branch' => 'JakPus']);
        $this->assertDatabaseHas('meta_connections', ['branch' => 'Bandung', 'status' => 'error']);
    }
}
