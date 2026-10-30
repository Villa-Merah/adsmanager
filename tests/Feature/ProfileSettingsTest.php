<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_viewer_can_update_own_identity_but_cannot_change_role(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get('/')->assertOk()->assertSee('Profil saya')->assertSee('Pilih foto');
        $this->putJson('/profile', ['name' => 'Nama Saya', 'email' => 'new@example.com', 'role' => 'admin'])->assertOk()->assertJsonPath('user.name', 'Nama Saya');
        $viewer->refresh();
        $this->assertSame('viewer', $viewer->role);
        $this->assertSame('new@example.com', $viewer->email);
        $this->assertNull($viewer->email_verified_at);
    }

    public function test_duplicate_email_is_rejected_without_changing_profile(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->putJson('/profile', ['name' => 'Changed', 'email' => $other->email])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNotSame('Changed', $user->fresh()->name);
    }

    public function test_photo_can_be_uploaded_replaced_read_and_removed_privately(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $data = ['name' => $user->name, 'email' => $user->email];
        $this->actingAs($user)->put('/profile', $data + ['avatar' => $this->photo()], ['Accept' => 'application/json'])->assertOk()->assertJsonMissingPath('user.avatar_path');
        $first = $user->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->get('/profile/avatar')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->put('/profile', $data + ['avatar' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($first);
        $second = $user->fresh()->avatar_path;
        $other = User::factory()->create();
        $this->actingAs($other)->get('/profile/avatar')->assertNotFound();
        $this->actingAs($user)->putJson('/profile', $data + ['remove_avatar' => true])->assertOk()->assertJsonPath('user.avatar_url', null);
        Storage::disk('local')->assertMissing($second);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => $user->email, 'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_wrong_current_password_and_mismatched_confirmation_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Original123']);
        $this->actingAs($user)->putJson('/profile/password', ['current_password' => 'Wrong123', 'password' => 'Updated123', 'password_confirmation' => 'Updated123'])->assertUnprocessable();
        $this->putJson('/profile/password', ['current_password' => 'Original123', 'password' => 'Updated123', 'password_confirmation' => 'Mismatch123'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('Original123', $user->fresh()->password));
    }

    public function test_guest_cannot_read_or_update_profile(): void
    {
        $this->putJson('/profile', ['name' => 'Guest', 'email' => 'guest@example.com'])->assertUnauthorized();
        $this->get('/profile/avatar')->assertRedirect('/login');
    }
}
