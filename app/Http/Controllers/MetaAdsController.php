<?php

namespace App\Http\Controllers;

use App\Models\MetaConnection;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;

class MetaAdsController extends Controller
{
    public function connect(Request $r)
    {
        $data = $r->validate(['ad_account_id' => ['required', 'string', 'regex:/^(act_)?[0-9]+$/'], 'account_name' => 'nullable|string|max:255', 'access_token' => 'required|string|min:20']);
        $connection = MetaConnection::updateOrCreate(['ad_account_id' => $data['ad_account_id']], $data + ['status' => 'connected', 'last_error' => null]);

        return response()->json($connection);
    }

    public function sync(Request $r, MetaAdsService $service)
    {
        $data = $r->validate(['since' => 'required|date', 'until' => 'required|date|after_or_equal:since']);
        $connection = MetaConnection::first();
        if (! $connection) {
            return response()->json(['message' => 'Akun Meta Ads belum dihubungkan.'], 422);
        }try {
            return response()->json(['imported' => $service->sync($connection, $data['since'], $data['until'])]);
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect()
    {
        MetaConnection::query()->delete();

        return response()->noContent();
    }
}
