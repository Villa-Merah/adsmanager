<?php

namespace App\Console\Commands;

use App\Models\ExternalIdentity;
use App\Models\User;
use App\Services\OmniAuthService;
use Illuminate\Console\Command;

class LinkOidcIdentity extends Command
{
    protected $signature = 'app:link-omni-identity {email : Email akun lokal} {subject : ID pengguna pada Omni Central Auth}';

    protected $description = 'Tautkan akun lokal yang sudah ada ke identitas Omni yang telah diverifikasi administrator';

    public function handle(OmniAuthService $service): int
    {
        $subject = (string) $this->argument('subject');
        if ($subject === '' || strlen($subject) > 255) {
            $this->error('ID SSO tidak valid.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Akun lokal tidak ditemukan.');

            return self::FAILURE;
        }
        try {
            $issuer = $service->server();
        } catch (\RuntimeException) {
            $this->error('Konfigurasi Omni belum siap.');

            return self::FAILURE;
        }
        if (ExternalIdentity::where('issuer', $issuer)->where(fn ($q) => $q->where('subject', $subject)->orWhere('user_id', $user->id))->exists()) {
            $this->error('Identitas atau akun ini sudah ditautkan. Tidak ada perubahan.');

            return self::FAILURE;
        }
        ExternalIdentity::create(['user_id' => $user->id, 'issuer' => $issuer, 'subject' => $subject]);
        $this->info('Akun berhasil ditautkan. Role, cabang, dan password tidak berubah.');

        return self::SUCCESS;
    }
}
