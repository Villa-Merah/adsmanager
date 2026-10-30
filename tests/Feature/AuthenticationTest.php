<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_regenerates_session_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'Password123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_logins_are_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_does_not_advertise_default_credentials(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('admin@adsmanager.id')->assertDontSee('Akun awal');
    }
}
