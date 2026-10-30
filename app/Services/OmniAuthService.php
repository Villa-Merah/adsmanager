<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class OmniAuthService
{
    public function server(): string
    {
        $url = rtrim((string) config('omni.server_url'), '/');
        $this->validateUrl($url);
        if (! config('omni.enabled') || ! config('omni.client_id') || ! config('omni.client_secret')) {
            throw new RuntimeException('SSO belum dikonfigurasi.');
        }
        $this->validateUrl((string) config('omni.redirect_uri'));

        return $url;
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $local = app()->environment('local', 'testing') && isset($parts['host']) && ($parts['host'] === 'localhost' || $parts['host'] === '127.0.0.1' || str_ends_with($parts['host'], '.test'));
        if (! $parts || ! filter_var($url, FILTER_VALIDATE_URL) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true)) {
            throw new RuntimeException('URL SSO tidak valid atau tidak menggunakan HTTPS.');
        }
    }

    public function authorizeUrl(string $state, string $verifier): string
    {
        return $this->server().'/oauth/authorize?'.http_build_query([
            'client_id' => config('omni.client_id'), 'redirect_uri' => config('omni.redirect_uri'), 'response_type' => 'code', 'scope' => '', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function profile(string $code, string $verifier): array
    {
        $server = $this->server();
        try {
            $response = Http::asForm()->acceptJson()->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(15)->post($server.'/oauth/token', [
                'grant_type' => 'authorization_code', 'client_id' => config('omni.client_id'), 'client_secret' => config('omni.client_secret'),
                'redirect_uri' => config('omni.redirect_uri'), 'code' => $code, 'code_verifier' => $verifier,
            ]);
            if (! $response->successful()) {
                throw new RuntimeException;
            }
            $token = $response->json('access_token');
            if (! is_string($token) || $token === '' || strtolower((string) $response->json('token_type')) !== 'bearer') {
                throw new RuntimeException;
            }
            $profile = Http::withToken($token)->acceptJson()->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(15)->get($server.'/api/sso/user');
            if (! $profile->successful()) {
                throw new RuntimeException;
            }
            $data = $profile->json();
            if (! is_array($data)) {
                throw new RuntimeException;
            }
            $data['id'] = isset($data['id']) && (is_string($data['id']) || is_int($data['id'])) ? (string) $data['id'] : null;
            $validated = Validator::make($data, ['id' => 'required|string|max:255', 'name' => 'required|string|max:255', 'email' => 'required|email|max:255'])->validate();

            return $validated;
        } catch (\Throwable) {
            throw new RuntimeException('Login SSO gagal. Silakan coba lagi atau hubungi pengelola SSO.');
        }
    }
}
