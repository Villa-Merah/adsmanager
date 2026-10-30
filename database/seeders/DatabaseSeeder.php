<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\KpiTarget;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        $admin = User::firstOrCreate(['email' => 'admin@adsmanager.id'], ['name' => 'Admin Marketing', 'password' => Str::password(24), 'role' => 'superadmin']);
        $campaigns = [
            ['date' => '2026-08-01', 'name' => 'Kelas SR+AR', 'objective' => 'Leads', 'platform' => 'Instagram', 'budget' => 3000000, 'impressions' => 220000, 'reach' => 150000, 'link_clicks' => 4500, 'profile_visits' => 1200, 'chats' => 520, 'leads_count' => 210, 'closings_count' => 68, 'revenue' => 42500000, 'status' => 'Aktif', 'notes' => 'Contoh kampanye akuisisi utama', 'source' => 'excel'],
            ['date' => '2026-08-02', 'name' => 'Kelas AR', 'objective' => 'Leads', 'platform' => 'Facebook', 'budget' => 1500000, 'impressions' => 120000, 'reach' => 85000, 'link_clicks' => 2800, 'profile_visits' => 850, 'chats' => 340, 'leads_count' => 145, 'closings_count' => 49, 'revenue' => 30000000, 'status' => 'Selesai', 'notes' => 'Contoh kampanye trial class', 'source' => 'excel'],
        ];
        foreach ($campaigns as $row) {
            Campaign::updateOrCreate(['date' => $row['date'], 'name' => $row['name'], 'platform' => $row['platform']], $row);
        }
        foreach ([['Reach', 500000, 'akun'], ['Klik Link', 3000, 'klik'], ['Leads', 500, 'lead'], ['Closing', 433, 'closing'], ['Omzet', 6500000000, 'rupiah']] as [$metric,$target,$unit]) {
            KpiTarget::updateOrCreate(['period_start' => '2026-08-01', 'metric' => $metric], compact('target', 'unit'));
        }
        if (Lead::count() === 0) {
            $first = Campaign::first();
            foreach ([['Rina Wijaya', 'Bandung', 'SR+AR', 'Qualified'], ['Budi Santoso', 'Jakarta', 'AR', 'Follow Up'], ['Maya Putri', 'Surabaya', 'SR+AR', 'Baru']] as [$name,$city,$program,$status]) {
                Lead::create(compact('name', 'city', 'program', 'status') + ['campaign_id' => $first?->id, 'assigned_to' => $admin->id, 'source' => 'Instagram']);
            }
        }
    }
}
