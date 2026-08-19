<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FnsDetection extends Model
{
    protected $table = 'fns_detections';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'camera_ip',
        'camera_name',
        'warehouse_code',
        'godown',
        'compartment',
        'detection_type',
        'confidence',
        'snapshot_path',
        'bounding_box',
        'detected_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'detected_at' => 'datetime',
    ];

    protected $appends = [
        'snapshot_url',
    ];

    public function getSnapshotUrlAttribute(): ?string
    {
        $snapshotPath = trim((string) $this->snapshot_path);

        if ($snapshotPath === '') {
            return null;
        }

        // Preserve legacy external image URLs while new snapshots use the public disk.
        if (Str::startsWith(strtolower($snapshotPath), ['http://', 'https://'])) {
            return $snapshotPath;
        }

        $snapshotPath = ltrim($snapshotPath, '/');

        if (Str::startsWith($snapshotPath, 'storage/')) {
            $snapshotPath = Str::after($snapshotPath, 'storage/');
        }

        return Storage::disk('public')->url($snapshotPath);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('camera_ip', 'like', "%{$search}%")
                    ->orWhere('camera_name', 'like', "%{$search}%")
                    ->orWhere('warehouse_code', 'like', "%{$search}%")
                    ->orWhere('godown', 'like', "%{$search}%")
                    ->orWhere('compartment', 'like', "%{$search}%")
                    ->orWhere('detection_type', 'like', "%{$search}%")
                    ->orWhere('snapshot_path', 'like', "%{$search}%")
                    ->orWhere('bounding_box', 'like', "%{$search}%");
            });
        }

        foreach (['camera_ip', 'camera_name', 'warehouse_code', 'godown', 'compartment'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value !== '') {
                $query->where($field, 'like', "%{$value}%");
            }
        }

        if (! empty($filters['detection_type'])) {
            $query->where('detection_type', $filters['detection_type']);
        }

        if (isset($filters['min_confidence']) && $filters['min_confidence'] !== '') {
            $query->where('confidence', '>=', $filters['min_confidence']);
        }

        if (isset($filters['max_confidence']) && $filters['max_confidence'] !== '') {
            $query->where('confidence', '<=', $filters['max_confidence']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('detected_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('detected_at', '<=', $filters['to_date']);
        }

        return $query;
    }
}
