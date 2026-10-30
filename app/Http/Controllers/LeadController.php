<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Closing;
use App\Models\Lead;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function store(Request $r)
    {
        $branch = BranchAccess::required($r);

        return response()->json(Lead::create($this->validated($r, false, $branch) + ['branch' => $branch]), 201);
    }

    public function update(Request $r, Lead $lead)
    {
        BranchAccess::authorize($r, $lead);
        $branch = $r->user()->hasRole('superadmin') && $r->filled('branch') ? BranchAccess::required($r) : $lead->branch;
        if (! $branch) {
            throw ValidationException::withMessages(['branch' => 'Tetapkan cabang lead terlebih dahulu.']);
        }
        $data = $this->validated($r, true, $branch) + ['branch' => $branch];
        $campaignId = array_key_exists('campaign_id', $data) ? $data['campaign_id'] : $lead->campaign_id;
        if ($campaignId && ! Campaign::whereKey($campaignId)->where('branch', $branch)->exists()) {
            throw ValidationException::withMessages(['campaign_id' => 'Campaign harus berasal dari cabang yang sama.']);
        }
        if ($branch !== $lead->branch && ! array_key_exists('assigned_to', $data)) {
            $data['assigned_to'] = null;
        }
        DB::transaction(function () use ($lead, $data, $r) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            BranchAccess::authorize($r, $lead);
            $lead->update($data);
            if (array_key_exists('campaign_id', $data)) {
                $lead->closing()->update(['campaign_id' => $lead->campaign_id]);
            }
        });

        return response()->json($lead->fresh(['campaign', 'assignee', 'closing']));
    }

    public function destroy(Request $r, Lead $lead)
    {
        BranchAccess::authorize($r, $lead);
        $lead->delete();

        return response()->noContent();
    }

    public function close(Request $r, Lead $lead)
    {
        BranchAccess::authorize($r, $lead);
        $data = $r->validate(['date' => 'required|date', 'value' => 'required|numeric|min:0', 'status' => 'required|string|max:50', 'notes' => 'nullable|string']);
        DB::transaction(function () use ($lead, $data, $r) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            BranchAccess::authorize($r, $lead);
            $lead->update(['status' => 'Closing']);
            Closing::updateOrCreate(['lead_id' => $lead->id], $data + ['campaign_id' => $lead->campaign_id]);
        });

        return response()->json($lead->fresh('closing'));
    }

    private function validated(Request $r, bool $partial = false, ?string $branch = null): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $r->validate(['campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('branch', $branch)], 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('branch', $branch)], 'name' => "$rule|string|max:255", 'phone' => 'nullable|string|max:50', 'city' => 'nullable|string|max:100', 'program' => 'nullable|string|max:150', 'source' => 'nullable|string|max:100', 'status' => "$rule|string|max:50", 'follow_up_at' => 'nullable|date', 'notes' => 'nullable|string']);
    }
}
