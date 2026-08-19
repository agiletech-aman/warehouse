<?php

namespace App\Http\Controllers;

use App\Models\EmailRouting;
use Illuminate\Http\Request;

class EmailRoutingController extends Controller
{
    public function index()
    {
        $routing = EmailRouting::all()
            ->keyBy(fn($r) => $r->device_type . '_' . $r->level);

        return view('settings.email-routing', compact('routing'));
    }

    public function update(Request $request)
    {
        $types = ['co2', 'ph3'];
        $levels = ['normal', 'severe', 'critical'];

        foreach ($types as $type) {
            foreach ($levels as $level) {

                $key = "{$type}_{$level}";

                EmailRouting::updateOrCreate(
                    [
                        'device_type' => $type,
                        'level' => $level
                    ],
                    [
                        'warehouse_mail'      => $request->has("warehouse_mail_{$key}"),
                        'warehouse_whatsapp'  => $request->has("warehouse_whatsapp_{$key}"),

                        'regional_mail'       => $request->has("regional_mail_{$key}"),
                        'regional_whatsapp'   => $request->has("regional_whatsapp_{$key}"),
                    ]
                );
            }
        }

        return back()->with('success', 'Alert routing settings saved successfully.');
    }
}