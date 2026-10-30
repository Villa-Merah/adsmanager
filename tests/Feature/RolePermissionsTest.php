<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_migration_preserves_accounts_and_defaults_new_users_to_staff(): void
    {
        $oldAdmin = User::factory()->create(['role' => 'admin']);
        $oldViewer = User::factory()->create(['role' => 'viewer', 'branch' => 'JakSel']);
        $password = $oldAdmin->password;
        $migration = require database_path('migrations/2026_10_30_170000_update_user_role_names.php');
        $migration->up();
        $this->assertSame('superadmin', $oldAdmin->fresh()->role);
        $this->assertSame($password, $oldAdmin->fresh()->password);
        $this->assertSame('staff', $oldViewer->fresh()->role);
        $this->assertSame('JakSel', $oldViewer->fresh()->branch);
        $id = DB::table('users')->insertGetId(['name' => 'Default', 'email' => 'default@example.com', 'password' => $password]);
        $this->assertSame('staff', User::findOrFail($id)->role);
    }

    public function test_only_kacab_and_marketing_can_manage_meta_for_their_own_branch(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        foreach (User::ROLES as $role) {
            $user = User::factory()->create(['role' => $role, 'branch' => in_array($role, User::CENTRAL_ROLES, true) ? null : 'Bandung']);
            $payload = ['branch' => 'Bandung', 'ad_account_id' => 'act_123', 'access_token' => str_repeat('x', 30)];
            if (in_array($role, ['kacab', 'marketing'], true)) {
                $this->actingAs($user)->postJson('/meta/connect', $payload)->assertOk();
                $this->postJson('/meta/sync', ['branch' => 'Bandung', 'since' => '2026-10-01', 'until' => '2026-10-30'])->assertOk();
                $this->postJson('/meta/sync', ['branch' => 'JakSel', 'since' => '2026-10-01', 'until' => '2026-10-30'])->assertForbidden();
                $this->postJson('/meta/connect', array_replace($payload, ['branch' => 'JakPus']))->assertForbidden();
                $this->deleteJson('/meta/disconnect', ['branch' => 'JakSel'])->assertForbidden();
                $this->get('/')->assertSee('id="connectButton"', false)->assertSee('data-sync-meta="Bandung"', false)->assertDontSee('data-connect-meta="JakSel"', false);
                $this->deleteJson('/meta/disconnect', ['branch' => 'Bandung'])->assertNoContent();
            } else {
                $this->actingAs($user)->postJson('/meta/connect', $payload)->assertForbidden();
                $this->postJson('/meta/sync', ['branch' => 'Bandung', 'since' => '2026-10-01', 'until' => '2026-10-30'])->assertForbidden();
                $this->deleteJson('/meta/disconnect', ['branch' => 'Bandung'])->assertForbidden();
                $this->get('/')->assertDontSee('id="connectButton"', false)->assertDontSee('Meta Ads per cabang');
            }
        }
        Http::assertSentCount(2);
    }

    public function test_leadership_reads_all_branches_but_cannot_modify_data_or_users(): void
    {
        foreach (['Bandung', 'JakSel', 'JakPus'] as $branch) {
            Campaign::create(['branch' => $branch, 'name' => $branch, 'date' => '2026-10-01', 'platform' => 'Meta Ads']);
        }
        foreach (['direktur utama', 'direktur', 'komisaris'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/metrics')->assertJsonCount(3, 'campaigns');
            $this->getJson('/metrics?branch=JakPus')->assertJsonCount(1, 'campaigns');
            $this->postJson('/campaigns', [])->assertForbidden();
            $this->postJson('/leads', [])->assertForbidden();
            $this->postJson('/imports/campaigns', [])->assertForbidden();
            $this->postJson('/users', [])->assertForbidden();
            $this->get('/')->assertSee('id="workspaceBranch"', false)->assertDontSee('id="newUserButton"', false);
        }
    }

    public function test_superadmin_can_create_all_supported_roles_and_rejects_old_role_names(): void
    {
        $admin = User::factory()->create();
        foreach (User::ROLES as $index => $role) {
            $central = in_array($role, User::CENTRAL_ROLES, true);
            $this->actingAs($admin)->postJson('/users', ['name' => 'Member', 'email' => 'member'.$index.'@example.com', 'role' => $role, 'branch' => $central ? null : 'JakPus', 'password' => 'Password123'])->assertCreated()->assertJsonPath('role', $role)->assertJsonPath('branch', $central ? null : 'JakPus');
        }
        $this->postJson('/users', ['name' => 'Old', 'email' => 'old@example.com', 'role' => 'admin', 'password' => 'Password123'])->assertUnprocessable()->assertJsonValidationErrors('role');
    }
}
