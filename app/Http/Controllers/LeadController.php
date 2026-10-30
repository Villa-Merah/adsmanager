<?php

namespace App\Http\Controllers;

use App\Models\Closing;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function store(Request $r)
    {
        return response()->json(Lead::create($this->validated($r)), 201);
    }

    public function update(Request $r, Lead $lead)
    {
        $data = $this->validated($r, true);
        DB::transaction(function () use ($lead, $data) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $lead->update($data);
            if (array_key_exists('campaign_id', $data)) {
                $lead->closing()->update(['campaign_id' => $lead->campaign_id]);
            }
        });

        return response()->json($lead->fresh(['campaign', 'assignee', 'closing']));
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->noContent();
    }

    public function close(Request $r, Lead $lead)
    {
        $data = $r->validate(['date' => 'required|date', 'value' => 'required|numeric|min:0', 'status' => 'required|string|max:50', 'notes' => 'nullable|string']);
        DB::transaction(function () use ($lead, $data) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $lead->update(['status' => 'Closing']);
            Closing::updateOrCreate(['lead_id' => $lead->id], $data + ['campaign_id' => $lead->campaign_id]);
        });

        return response()->json($lead->fresh('closing'));
    }

    private function validated(Request $r, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $r->validate(['campaign_id' => 'nullable|exists:campaigns,id', 'assigned_to' => 'nullable|exists:users,id', 'name' => "$rule|string|max:255", 'phone' => 'nullable|string|max:50', 'city' => 'nullable|string|max:100', 'program' => 'nullable|string|max:150', 'source' => 'nullable|string|max:100', 'status' => "$rule|string|max:50", 'follow_up_at' => 'nullable|date', 'notes' => 'nullable|string']);
    }
}
