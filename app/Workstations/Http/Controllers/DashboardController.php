<?php

namespace App\Workstations\Http\Controllers;

use App\Workstations\Models\{Event, Release, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /** Decorates workstation rows with display state used by every page. */
    public static function decorate($rows)
    {
        $latest = Release::where('is_latest', true)->where('yanked', false)->first();
        $rels = Release::all()->keyBy('id');
        $offlineAfter = (int)config('workstations.offline_after');
        return $rows->map(function (Workstation $w) use ($latest, $rels, $offlineAfter) {
            $target = $w->target_release_id ? ($rels[$w->target_release_id] ?? null) : null;
            $w->vstate = AgentProtocol::versionState($w->current_version, $latest?->toArray(), $target?->toArray(), (bool)$w->auto_update);
            $w->online = $w->last_seen_at && $w->last_seen_at->diffInSeconds(now(), true) < $offlineAfter;
            $w->target_version = $target?->version;
            $w->badge = match (true) {
                $w->status === 'blocked' => 'blocked',
                $w->status === 'pending' => 'pending',
                (bool)$w->online => 'online',
                default => 'offline',
            };
            return $w;
        });
    }

    public function index(Request $r)
    {
        $all = self::decorate(Workstation::orderBy('warehouse_code')->orderBy('workstation_id')->get());
        $wh = (string)$r->query('wh', '');
        $q = trim((string)$r->query('q', ''));
        $rows = $all
            ->when($wh !== '', fn($c) => $c->where('warehouse_code', $wh))
            ->when($q !== '', fn($c) => $c->filter(fn($w) => str_contains(mb_strtolower(implode(' ', [
                $w->workstation_id, $w->label, $w->hostname, $w->warehouse_code, $w->current_version])), mb_strtolower($q))));
        return view('workstations::dashboard', [
            'rows' => $rows, 'wh' => $wh, 'q' => $q,
            'warehouses' => $all->pluck('warehouse_code')->filter()->unique()->sort()->values(),
            'pending' => $all->where('status', 'pending'),
            'latest' => Release::where('is_latest', true)->where('yanked', false)->first(),
            'stats' => ['total' => $all->count(), 'online' => $all->where('badge', 'online')->count(),
                'offline' => $all->where('badge', 'offline')->count(), 'outdated' => $all->where('vstate', 'outdated')->count()],
        ]);
    }

    public function approveAll(Request $r)
    {
        $q = Workstation::where('status', 'pending');
        if ($ids = (array)$r->input('ids', [])) {
            $q->whereIn('workstation_id', $ids);
        }
        $ids = $q->pluck('workstation_id');
        Workstation::whereIn('workstation_id', $ids)->update(['status' => 'approved']);
        foreach ($ids as $id) {
            Event::create(['workstation_id' => $id, 'type' => 'admin_approve', 'message' => 'bulk', 'created_at' => now()]);
        }
        return back()->with('ok', $ids->count() . ' workstation approve ho gaye.');
    }
}
