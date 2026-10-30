<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_identities')) {
            foreach (['user_id', 'issuer', 'subject', 'identity_key'] as $column) {
                if (! Schema::hasColumn('external_identities', $column)) {
                    throw new RuntimeException('Struktur identitas SSO lama perlu ditinjau: '.$column);
                }
            }

            return;
        }
        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('issuer');
            $table->string('subject');
            $table->char('identity_key', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Preserve identity links, including tables inherited from an earlier installation.
    }
};
