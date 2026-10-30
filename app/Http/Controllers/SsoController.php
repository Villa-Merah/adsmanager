<?php

namespace App\Http\Controllers;

use App\Models\ExternalIdentity;
use App\Models\User;
use App\Services\OmniAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SsoController extends Controller
{
    public function login(Request $request, OmniAuthService $service)
    {
        try {
            $state = Str::random(64);
            $verifier = Str::random(96);
            $url = $service->authorizeUrl($state, $verifier);
            $request->session()->put('omni.transaction', ['state' => $state, 'verifier' => $verifier, 'created_at' => time()]);

            return redirect()->away($url)->header('Cache-Control', 'no-store');
        } catch (RuntimeException) {
            return redirect()->route('login')->withErrors(['sso' => 'SSO belum siap. Silakan gunakan login lokal atau hubungi pengelola.']);
        }
    }

    public function callback(Request $request, OmniAuthService $service)
    {
        $transaction = $request->session()->pull('omni.transaction');
        $state = $request->query('state');
        $code = $request->query('code');
        if (! is_array($transaction) || ! is_string($state) || ! hash_equals($transaction['state'], $state) || time() - $transaction['created_at'] > config('omni.transaction_ttl', 300) || $request->has('error') || ! is_string($code) || $code === '' || strlen($code) > 4096) {
            return redirect()->route('login')->withErrors(['sso' => 'Sesi SSO tidak valid atau kedaluwarsa. Mulai login SSO kembali.']);
        }
        try {
            $profile = $service->profile($code, $transaction['verifier']);
            $issuer = $service->server();
            $user = DB::transaction(function () use ($profile, $issuer) {
                $identity = ExternalIdentity::where('issuer', $issuer)->where('subject', $profile['id'])->first();
                if ($identity) {
                    return $identity->user;
                }
                if (User::where('email', $profile['email'])->exists()) {
                    throw new RuntimeException('Email ini sudah terdaftar. Minta superadmin menautkan akun lokal ke ID SSO melalui command app:link-omni-identity.');
                }
                $user = User::create(['name' => $profile['name'], 'email' => $profile['email'], 'password' => Str::password(64), 'role' => 'staff', 'branch' => null]);
                ExternalIdentity::create(['user_id' => $user->id, 'issuer' => $issuer, 'subject' => $profile['id']]);

                return $user;
            });
            if (! $user) {
                throw new RuntimeException('Akun lokal tidak tersedia.');
            }
            Auth::login($user, false);
            $request->session()->regenerate();
            $request->session()->put('auth_via', 'omni');

            return redirect('/')->header('Cache-Control', 'no-store');
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors(['sso' => $e->getMessage()]);
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['sso' => 'Login SSO belum berhasil. Hubungi superadmin untuk pemeriksaan akun.']);
        }
    }
}
