<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\MetaConnection;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = Campaign::query()->latest('date')->get();

        return view('welcome', ['campaigns' => $campaigns, 'leads' => Lead::with(['campaign', 'assignee', 'closing'])->latest()->get(), 'metaConnection' => $request->user()->hasRole('admin') ? MetaConnection::first() : null, 'users' => $request->user()->hasRole('admin') ? User::orderBy('name')->get() : collect()]);
    }

    public function metrics(Request $request)
    {
        $request->validate(['platform' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'conversion_source' => 'nullable|in:campaign,crm']);
        $q = Campaign::query()->when($request->platform, fn ($q, $v) => $q->where('platform', $v))->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v));
        if ($request->conversion_source === 'crm') {
            $q->withCount(['leads as crm_leads_count', 'closings as crm_closings_count'])->withSum('closings', 'value');
        }
        $campaigns = $q->latest('date')->get();
        if ($request->conversion_source === 'crm') {
            foreach ($campaigns as $campaign) {
                $campaign->leads_count = $campaign->crm_leads_count;
                $campaign->closings_count = $campaign->crm_closings_count;
                $campaign->revenue = $campaign->closings_sum_value ?? 0;
            }
        }
        $columns = ['budget' => 'budget', 'impressions' => 'impressions', 'reach' => 'reach', 'clicks' => 'link_clicks', 'profile_visits' => 'profile_visits', 'chats' => 'chats', 'leads' => 'leads_count', 'closings' => 'closings_count', 'revenue' => 'revenue'];
        $metrics = [];
        foreach ($columns as $key => $column) {
            $metrics[$key] = $campaigns->sum($column);
        }
        $trend = $campaigns->groupBy(fn ($c) => $c->date->toDateString())->map(fn ($rows, $date) => ['date' => $date, 'budget' => $rows->sum('budget'), 'revenue' => $rows->sum('revenue')])->sortBy('date')->values();

        return response()->json(['metrics' => $metrics, 'campaigns' => $campaigns, 'trend' => $trend]);
    }
}
