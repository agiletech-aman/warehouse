<?php

namespace App\Workstations\Http\Controllers;

use App\Workstations\Models\{EnvEntry, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EnvController extends Controller
{
    public function index(Request $r)
    {
        $scope = $r->query('scope') === 'warehouse' ? 'warehouse' : 'global';
        $ref = $scope === 'global' ? '' : (string)$r->query('ref', '');
        $codes = Workstation::whereNotNull('warehouse_code')->distinct()->orderBy('warehouse_code')->pluck('warehouse_code');
        return view('workstations::env', [
            'scope' => $scope, 'ref' => $ref, 'codes' => $codes,
            'entries' => ($scope === 'global' || $ref !== '')
                ? EnvEntry::where('scope', $scope)->where('scope_ref', $ref)->orderBy('key')->get() : collect(),
            'locked' => AgentProtocol::ENV_LOCKED, 'ints' => AgentProtocol::ENV_INT, 'floats' => AgentProtocol::ENV_FLOAT,
        ]);
    }

    public function save(Request $r)
    {
        $scope = (string)$r->input('scope');
        $ref = $scope === 'global' ? '' : (string)$r->input('ref', '');
        if (!in_array($scope, ['global', 'warehouse', 'workstation'], true) || ($scope !== 'global' && $ref === '')) {
            return back()->with('error', 'Choose a warehouse / workstation first.');
        }
        $key = strtoupper(trim((string)$r->input('key')));
        $where = ['scope' => $scope, 'scope_ref' => $ref, 'key' => $key];

        if ($r->input('action') === 'delete') {
            EnvEntry::where($where)->delete();
            return back()->with('ok', "$key removed. Workstations will update on their next check-in.");
        }
        $val = trim((string)$r->input('value'));
        if ($err = AgentProtocol::validateEnvEntry($key, $val)) {
            return back()->withErrors(['value' => $err, 'row' => $r->input('row', 'new')])->withInput();
        }
        EnvEntry::updateOrCreate($where, ['value' => $val]);
        return back()->with('ok', "$key = $val saved. Workstations will pick it up on their next check-in.");
    }
}
