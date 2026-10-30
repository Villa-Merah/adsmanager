<?php

namespace Tests\Feature;

use App\Models\ExternalIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmniSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['omni.enabled' => true, 'omni.server_url' => 'http://central.test', 'omni.client_id' => 'test-client', 'omni.client_secret' => 'test-secret', 'omni.redirect_uri' => 'http://ads.test/auth/omni/callback']);
        Http::preventStrayRequests();
    }

    private function beginLogin(): array
    {
        $response = $this->get('/auth/omni')->assertRedirect();
        $transaction = session('omni.transaction');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($transaction['state'], $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('http://ads.test/auth/omni/callback', $query['redirect_uri']);
        $this->assertStringNotContainsString('test-secret', $response->headers->get('Location'));

        return $transaction;
    }

    private function fakeProfile(array $profile): void
    {
        Http::fake(['http://central.test/oauth/token' => Http::response(['access_token' => 'fake-token', 'token_type' => 'Bearer']), 'http://central.test/api/sso/user' => Http::response($profile)]);
    }

    public function test_linked_sso_login_preserves_local_role_branch_and_password(): void
    {
        $user = User::factory()->create(['role' => 'marketing', 'branch' => 'JakPus']);
        $password = $user->password;
        ExternalIdentity::create(['user_id' => $user->id, 'issuer' => 'http://central.test', 'subject' => '42']);
        $transaction = $this->beginLogin();
        $this->fakeProfile(['id' => '42', 'name' => 'Central Name', 'email' => $user->email, 'role' => 'superadmin', 'branch' => 'Bandung']);
        $this->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code123']))->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('marketing', $user->fresh()->role);
        $this->assertSame('JakPus', $user->fresh()->branch);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertNull(session('omni.transaction'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token') && $request['code_verifier'] === $transaction['verifier']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sso/user') && $request->hasHeader('Authorization', 'Bearer fake-token'));
    }

    public function test_unknown_identity_is_provisioned_as_unassigned_staff(): void
    {
        $transaction = $this->beginLogin();
        $this->fakeProfile(['id' => 'new-user', 'name' => 'New User', 'email' => 'new@example.com', 'role' => 'superadmin']);
        $this->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code']))->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'staff', 'branch' => null]);
        $this->assertDatabaseCount('external_identities', 1);
        $this->getJson('/metrics')->assertJsonCount(0, 'campaigns');
    }

    public function test_matching_email_without_explicit_link_does_not_take_over_local_account(): void
    {
        $user = User::factory()->create();
        $transaction = $this->beginLogin();
        $this->fakeProfile(['id' => 'unexpected', 'name' => 'Other', 'email' => $user->email]);
        $this->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code']))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_invalid_state_is_rejected_and_cannot_be_replayed(): void
    {
        Http::fake();
        $transaction = $this->beginLogin();
        $this->get('/auth/omni/callback?state=wrong&code=code')->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code']))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_expired_login_transaction_is_rejected(): void
    {
        Http::fake();
        $transaction = $this->beginLogin();
        $transaction['created_at'] = time() - 400;
        $this->withSession(['omni.transaction' => $transaction])->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code']))->assertRedirect('/login')->assertSessionHasErrors('sso');
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_generic_and_does_not_expose_credentials(): void
    {
        $transaction = $this->beginLogin();
        Http::fake(['*' => Http::response(['error' => 'client_secret=test-secret'], 400)]);
        $this->get('/auth/omni/callback?'.http_build_query(['state' => $transaction['state'], 'code' => 'code']))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertStringNotContainsString('test-secret', session('errors')->first('sso'));
        $this->assertGuest();
    }

    public function test_link_command_links_existing_account_without_changing_permissions(): void
    {
        $user = User::factory()->create(['role' => 'kacab', 'branch' => 'Bandung']);
        $this->artisan('app:link-omni-identity', ['email' => $user->email, 'subject' => '55'])->assertSuccessful();
        $this->assertDatabaseHas('external_identities', ['user_id' => $user->id, 'subject' => '55']);
        $this->assertSame('kacab', $user->fresh()->role);
        $this->assertSame('Bandung',$user->fresh()->branch);
    }
}
