<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('admin')->after('password'));
        }
        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('external_id')->nullable()->unique();
                $table->date('date');
                $table->string('name');
                $table->string('objective')->default('Leads');
                $table->string('platform');
                $table->decimal('budget', 18, 2)->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('reach')->default(0);
                $table->unsignedBigInteger('link_clicks')->default(0);
                $table->unsignedBigInteger('profile_visits')->default(0);
                $table->unsignedBigInteger('chats')->default(0);
                $table->unsignedInteger('leads_count')->default(0);
                $table->unsignedInteger('closings_count')->default(0);
                $table->decimal('revenue', 18, 2)->default(0);
                $table->string('status')->default('Aktif');
                $table->text('notes')->nullable();
                $table->string('source')->default('manual');
                $table->string('source_hash')->nullable()->unique();
                $table->timestamps();
                $table->index(['date', 'platform', 'status']);
            });
        }
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('program')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('Baru');
            $table->timestamp('follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'follow_up_at']);
        });
        Schema::create('closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('value', 18, 2)->default(0);
            $table->string('status')->default('Lunas');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->string('metric');
            $table->decimal('target', 18, 2);
            $table->string('unit');
            $table->timestamps();
            $table->unique(['period_start', 'metric']);
        });
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('file_hash')->unique();
            $table->string('status');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();
            $table->string('ad_account_id')->unique();
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_connections');
        Schema::dropIfExists('data_imports');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('closings');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('campaigns');
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }
};
