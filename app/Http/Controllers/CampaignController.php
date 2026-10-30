<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignWorkbookImporter;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['source'] = 'manual';
        $data['branch'] = BranchAccess::required($r);

        return response()->json(Campaign::create($data), 201);
    }

    public function update(Request $r, Campaign $campaign)
    {
        BranchAccess::authorize($r, $campaign);
        $data = $this->validated($r);
        if ($r->user()->hasRole('superadmin') && $r->filled('branch')) {
            $data['branch'] = BranchAccess::required($r);
        }
        DB::transaction(function () use ($campaign, $data) {
            $changed = isset($data['branch']) && $data['branch'] !== $campaign->branch;
            $campaign->update($data);
            if ($changed) {
                $campaign->leads()->update(['branch' => $campaign->branch, 'assigned_to' => null]);
            }
        });

        return response()->json($campaign->fresh());
    }

    public function destroy(Request $r, Campaign $campaign)
    {
        BranchAccess::authorize($r, $campaign);
        $campaign->delete();

        return response()->noContent();
    }

    public function preview(Request $r, CampaignWorkbookImporter $importer)
    {
        BranchAccess::required($r);
        $r->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
        try {
            return response()->json($importer->preview($r->file('file')));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function import(Request $r, CampaignWorkbookImporter $importer)
    {
        BranchAccess::required($r);
        $r->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
        try {
            $result = $importer->import($r->file('file'), $r->user()?->id, BranchAccess::required($r));

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function validated(Request $r): array
    {
        return $r->validate(['date' => 'required|date', 'name' => 'required|string|max:255', 'objective' => 'required|string|max:100', 'platform' => ['required', Rule::in(['Instagram', 'Facebook', 'Meta Ads', 'TikTok', 'Google'])], 'budget' => 'required|numeric|min:0', 'impressions' => 'nullable|integer|min:0', 'reach' => 'nullable|integer|min:0', 'link_clicks' => 'nullable|integer|min:0', 'profile_visits' => 'nullable|integer|min:0', 'chats' => 'nullable|integer|min:0', 'leads_count' => 'nullable|integer|min:0', 'closings_count' => 'nullable|integer|min:0', 'revenue' => 'nullable|numeric|min:0', 'status' => 'required|string|max:50', 'notes' => 'nullable|string']);
    }
}
