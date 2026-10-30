<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'superadmin']);
        DB::table('users')->where('role', 'viewer')->update(['role' => 'staff']);
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('staff')->change());
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'viewer']);
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('admin')->change());
    }
};
