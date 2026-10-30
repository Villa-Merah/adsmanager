<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'campaigns', 'crm_leads', 'data_imports'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('branch', 20)->nullable()->index());
        }
        Schema::table('meta_connections', fn (Blueprint $table) => $table->string('branch', 20)->nullable()->unique());
    }

    public function down(): void
    {
        foreach (['users', 'campaigns', 'crm_leads', 'data_imports', 'meta_connections'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('branch'));
        }
    }
};
