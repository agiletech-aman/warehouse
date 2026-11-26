<?php

namespace App\Workstations\Models;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Workstation extends Model
{
    protected $table = 'wsm_workstations';
    protected $guarded = [];
    protected $casts = ['last_seen_at' => 'datetime', 'auto_update' => 'boolean'];

    /** Warehouse name from the main `warehouses` table for a code, or null if unknown. */
    public static function warehouseName(?string $code): ?string
    {
        if (!$code) {
            return null;
        }
        try {
            return Warehouse::where('warehouse_code', $code)->value('warehouse_name') ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * If the warehouse code exists in the warehouses table, the label always mirrors its name.
     * Otherwise the label is whatever the admin typed (kept as is).
     */
    public function syncLabel(): void
    {
        $name = self::warehouseName($this->warehouse_code);
        if ($name !== null && $this->label !== $name) {
            $this->forceFill(['label' => $name])->save();
        }
    }
}
