<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk ke workspace');
    }

    public function test_authenticated_user_can_manage_campaigns(): void
    {
        $user = User::factory()->create();
        $payload = ['date' => '2026-08-12', 'name' => 'Campaign Test', 'objective' => 'Leads', 'platform' => 'Instagram', 'budget' => 1000000, 'status' => 'Aktif'];
        $created = $this->actingAs($user)->postJson('/campaigns', $payload)->assertCreated()->json();
        $this->assertDatabaseHas('campaigns', ['name' => 'Campaign Test', 'budget' => 1000000]);
        $this->actingAs($user)->putJson('/campaigns/'.$created['id'], array_replace($payload, ['name' => 'Campaign Updated']))->assertOk();
        $this->actingAs($user)->deleteJson('/campaigns/'.$created['id'])->assertNoContent();
    }

    public function test_authenticated_user_can_create_and_close_a_lead(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::create(['date' => '2026-08-12', 'name' => 'Test', 'platform' => 'Facebook', 'budget' => 100]);
        $lead = $this->actingAs($user)->postJson('/leads', ['name' => 'Rina', 'status' => 'Baru', 'campaign_id' => $campaign->id])->assertCreated()->json();
        $this->actingAs($user)->postJson('/leads/'.$lead['id'].'/close', ['date' => '2026-08-12', 'value' => 2500000, 'status' => 'Lunas'])->assertOk();
        $this->assertDatabaseHas('crm_leads', ['id' => $lead['id'], 'status' => 'Closing']);
        $this->assertDatabaseHas('closings', ['lead_id' => $lead['id'], 'value' => 2500000]);
    }

    public function test_reference_workbook_can_be_imported_once(): void
    {
        $user = User::factory()->create();
        $path = base_path('Dashboard_hasil iklan.xlsx');
        $file = UploadedFile::fake()->createWithContent('dashboard.xlsx', file_get_contents($path));
        $this->actingAs($user)->post('/imports/campaigns', ['file' => $file], ['Accept' => 'application/json'])->assertOk()->assertJson(['rows_imported' => 2]);
        $this->assertDatabaseCount('campaigns', 2);
        $duplicate = UploadedFile::fake()->createWithContent('same.xlsx', file_get_contents($path));
        $this->postJson('/imports/campaigns', ['file' => $duplicate])->assertStatus(422);
        $this->assertDatabaseCount('campaigns', 2);
    }

    public function test_viewer_can_read_but_cannot_mutate_operational_data(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get('/')->assertOk();
        $this->actingAs($viewer)->getJson('/metrics?from=2026-08-01&to=2026-08-31')->assertOk()->assertJsonStructure(['metrics', 'campaigns', 'trend']);
        $this->actingAs($viewer)->postJson('/campaigns', ['date' => '2026-08-12', 'name' => 'Blocked', 'objective' => 'Leads', 'platform' => 'Instagram', 'budget' => 100, 'status' => 'Aktif'])->assertForbidden();
        $this->actingAs($viewer)->postJson('/leads', ['name' => 'Blocked', 'status' => 'Baru'])->assertForbidden();
    }

    public function test_marketing_cannot_manage_meta_connection(): void
    {
        $marketing = User::factory()->create(['role' => 'marketing']);
        $this->actingAs($marketing)->postJson('/meta/connect', ['ad_account_id' => 'act_123456', 'access_token' => str_repeat('x', 30)])->assertForbidden();
    }

    public function test_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->actingAs($user)->putJson('/profile', ['name' => 'Nama Baru', 'email' => 'baru@example.com'])->assertOk();
        $this->actingAs($user)->putJson('/profile/password', ['current_password' => 'password123', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123'])->assertOk();
        $this->assertTrue(Hash::check('Rahasia123', $user->fresh()->password));
    }

    public function test_admin_can_manage_team_but_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = $this->actingAs($admin)->postJson('/users', ['name' => 'Viewer Baru', 'email' => 'viewer@example.com', 'role' => 'viewer', 'password' => 'Password123'])->assertCreated()->json();
        $this->assertDatabaseHas('users', ['email' => 'viewer@example.com', 'role' => 'viewer']);
        $this->actingAs($admin)->deleteJson('/users/'.$member['id'])->assertNoContent();
        $this->actingAs($admin)->deleteJson('/users/'.$admin->id)->assertStatus(422);
    }
}
