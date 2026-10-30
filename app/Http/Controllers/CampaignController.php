<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignWorkbookImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['source'] = 'manual';

        return response()->json(Campaign::create($data), 201);
    }

    public function update(Request $r, Campaign $campaign)
    {
        $campaign->update($this->validated($r));

        return response()->json($campaign->fresh());
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->noContent();
    }

    public function preview(Request $r, CampaignWorkbookImporter $importer)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
        try {
            return response()->json($importer->preview($r->file('file')));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function import(Request $r, CampaignWorkbookImporter $importer)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
        try {
            $result = $importer->import($r->file('file'), $r->user()?->id);

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
