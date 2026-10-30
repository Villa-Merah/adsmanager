<?php

namespace App\Http\Controllers;

use App\Models\MetaConnection;
use App\Services\MetaAdsService;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MetaAdsController extends Controller
{
    public function connect(Request $r)
    {
        $branch = BranchAccess::required($r);
        $data = $r->validate(['ad_account_id' => ['required', 'string', 'regex:/^(act_)?[0-9]+$/'], 'account_name' => 'nullable|string|max:255', 'access_token' => 'required|string|min:20']);
        $data['ad_account_id'] = 'act_'.preg_replace('/^act_/', '', $data['ad_account_id']);
        $digits = substr($data['ad_account_id'], 4);
        $other = MetaConnection::whereIn('ad_account_id', [$data['ad_account_id'], $digits])->where(fn ($q) => $q->whereNotNull('branch')->where('branch', '!=', $branch))->exists();
        if ($other) {
            throw ValidationException::withMessages(['ad_account_id' => 'Akun ini sudah terhubung. Tetapkan cabangnya melalui kartu koneksi lama.']);
        }
        $connection = MetaConnection::where('branch', $branch)->first() ?? MetaConnection::whereNull('branch')->whereIn('ad_account_id', [$data['ad_account_id'], $digits])->first() ?? new MetaConnection;
        $connection->fill($data + ['branch' => $branch, 'status' => 'connected', 'last_synced_at' => null, 'last_error' => null]);
        $connection->save();

        return response()->json($connection);
    }

    public function sync(Request $r, MetaAdsService $service)
    {
        $branch = BranchAccess::required($r);
        $data = $r->validate(['since' => 'required|date', 'until' => 'required|date|after_or_equal:since']);
        $connection = MetaConnection::where('branch', $branch)->first();
        if (! $connection) {
            return response()->json(['message' => 'Akun Meta Ads cabang '.$branch.' belum dihubungkan.'], 422);
        }
        try {
            return response()->json(['imported' => $service->sync($connection, $data['since'], $data['until']), 'branch' => $branch]);
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(Request $r)
    {
        $branch = BranchAccess::required($r);
        MetaConnection::where('branch', $branch)->delete();

        return response()->noContent();
    }
}
