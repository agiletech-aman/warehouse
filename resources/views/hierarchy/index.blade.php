@extends('layouts.app')

@section('title', 'Hierarchy')
@section('page-title', 'Hierarchy')

@section('content')
<style>
    .hierarchy-tools {
        max-width: 320px;
    }

    .hierarchy-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .hierarchy-node {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .hierarchy-region {
        border-color: #bfdbfe;
        background: #eff6ff;
        box-shadow: 0 8px 22px rgba(37, 99, 235, 0.08);
    }

    .hierarchy-region > summary .hierarchy-row {
        border-left: 4px solid #2563eb;
        background: #dbeafe;
    }

    .hierarchy-warehouse {
        border-color: #c7d2fe;
        background: #ffffff;
    }

    .hierarchy-warehouse > summary .hierarchy-row {
        border-left: 4px solid #4f46e5;
        background: #eef2ff;
    }

    .hierarchy-device {
        border-color: #bbf7d0;
        background: #f0fdf4;
    }

    .hierarchy-device .hierarchy-row {
        border-left: 4px solid #16a34a;
        background: #f0fdf4;
    }

    .hierarchy-node summary {
        list-style: none;
        cursor: pointer;
    }

    .hierarchy-node summary::-webkit-details-marker {
        display: none;
    }

    .hierarchy-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
    }

    .hierarchy-main {
        min-width: 0;
    }

    .hierarchy-title {
        font-weight: 700;
        color: #111827;
        line-height: 1.3;
    }

    .hierarchy-subtitle {
        color: #6b7280;
        font-size: 0.9rem;
        margin-top: 3px;
    }

    .hierarchy-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .hierarchy-children {
        border-top: 1px solid #eef2f7;
        padding: 12px 14px 14px 28px;
        background: #f8fbff;
    }

    .hierarchy-children .hierarchy-children {
        background: #f7f8ff;
    }

    .hierarchy-chevron {
        color: #6b7280;
        transition: transform .15s ease;
        display: inline-block;
        width: 18px;
    }

    details[open] > summary .hierarchy-chevron {
        transform: rotate(90deg);
    }

    .hierarchy-empty {
        border: 1px dashed #d1d5db;
        border-radius: 8px;
        padding: 28px;
        text-align: center;
        color: #6b7280;
        background: rgba(255, 255, 255, 0.72);
    }

    @media (max-width: 575.98px) {
        .hierarchy-row {
            align-items: flex-start;
            flex-direction: column;
        }

        .hierarchy-meta {
            justify-content: flex-start;
        }

        .hierarchy-tools {
            max-width: none;
            width: 100%;
        }
    }
</style>

<div class="content-shell">
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Hierarchy</h3>
                <p class="text-muted mb-0">View regions, warehouses, and devices from latest sensor readings.</p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <input type="text"
                       id="hierarchySearch"
                       class="form-control form-control-sm hierarchy-tools"
                       placeholder="Search region, warehouse, device">

                <button type="button" id="expandAll" class="btn btn-primary btn-sm rounded-pill px-3">
                    Expand All
                </button>

                <button type="button" id="collapseAll" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    Collapse All
                </button>
            </div>
        </div>

        @if($regions->isEmpty())
            <div class="hierarchy-empty">No hierarchy data found.</div>
        @else
            <div id="hierarchyList" class="hierarchy-list">
                @foreach($regions as $region)
                    @php
                        $regionSearch = strtolower(trim($region->region_code . ' ' . $region->region_name . ' ' . $region->status));
                    @endphp

                    <details class="hierarchy-node hierarchy-region hierarchy-item" data-search="{{ $regionSearch }}">
                        <summary>
                            <div class="hierarchy-row">
                                <div class="hierarchy-main">
                                    <div class="hierarchy-title">
                                        <span class="hierarchy-chevron">›</span>
                                        {{ $region->region_name }}
                                    </div>
                                    <div class="hierarchy-subtitle">
                                        Code: {{ $region->region_code }} | Manager: {{ $region->manager_name ?? '-' }}
                                    </div>
                                </div>

                                <div class="hierarchy-meta">
                                    <span class="badge bg-light text-dark">{{ $region->warehouses_count }} Warehouses</span>
                                    @if($region->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </summary>

                        <div class="hierarchy-children">
                            @forelse($region->warehouses as $warehouse)
                                @php
                                    $warehouseSearch = strtolower(trim($warehouse->warehouse_code . ' ' . $warehouse->warehouse_name . ' ' . $warehouse->status));
                                @endphp

                                <details class="hierarchy-node hierarchy-warehouse hierarchy-item mb-2" data-search="{{ $warehouseSearch }}">
                                    <summary>
                                        <div class="hierarchy-row">
                                            <div class="hierarchy-main">
                                                <div class="hierarchy-title">
                                                    <span class="hierarchy-chevron">›</span>
                                                    {{ $warehouse->warehouse_name }}
                                                </div>
                                                <div class="hierarchy-subtitle">
                                                    Code: {{ $warehouse->warehouse_code }} | Manager: {{ $warehouse->manager_name ?? '-' }}
                                                </div>
                                            </div>

                                            <div class="hierarchy-meta">
                                                <span class="badge bg-light text-dark">{{ $warehouse->devices_count }} Devices</span>
                                                @if($warehouse->status === 'active')
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </div>
                                        </div>
                                    </summary>

                                    <div class="hierarchy-children">
                                        @forelse($warehouse->devices as $device)
                                            @php
                                                $deviceSearch = strtolower(trim($device->sensor_device_id . ' ' . $device->device_name . ' ' . $device->device_type . ' ' . $device->status . ' ' . $device->godown . ' ' . $device->compartment));
                                            @endphp

                                            <div class="hierarchy-node hierarchy-device hierarchy-item mb-2" data-search="{{ $deviceSearch }}">
                                                <div class="hierarchy-row">
                                                    <div class="hierarchy-main">
                                                        <div class="hierarchy-title">{{ $device->device_name ?: $device->sensor_device_id }}</div>
                                                        <div class="hierarchy-subtitle">
                                                            Code: {{ $device->sensor_device_id }}
                                                            @if($device->device_type)
                                                                | Type: {{ $device->device_type }}
                                                            @endif
                                                            @if($device->godown)
                                                                | Godown: {{ $device->godown }}
                                                            @endif
                                                            @if($device->compartment)
                                                                | Compartment: {{ $device->compartment }}
                                                            @endif
                                                            @if(!is_null($device->reading_value))
                                                                | Latest: {{ $device->reading_value }} {{ $device->unit }}
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="hierarchy-meta">
                                                        @if($device->level === 'critical')
                                                            <span class="badge bg-danger">Critical</span>
                                                        @elseif($device->level === 'severe')
                                                            <span class="badge bg-severe text-dark">severe</span>
                                                        @else
                                                            <span class="badge bg-success">Normal</span>
                                                        @endif

                                                        @if($device->status === 'online')
                                                            <span class="badge bg-success">Online</span>
                                                        @elseif($device->status === 'offline')
                                                            <span class="badge bg-secondary">Offline</span>
                                                        @else
                                                            <span class="badge bg-light text-dark">{{ ucfirst($device->status ?? 'Unknown') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="hierarchy-empty py-3">No devices found.</div>
                                        @endforelse
                                    </div>
                                </details>
                            @empty
                                <div class="hierarchy-empty py-3">No warehouses found.</div>
                            @endforelse
                        </div>
                    </details>
                @endforeach
            </div>

            <div id="hierarchyNoResults" class="hierarchy-empty mt-3 d-none">
                No matching hierarchy data found.
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('hierarchySearch');
        const items = Array.from(document.querySelectorAll('#hierarchyList .hierarchy-item'));
        const noResults = document.getElementById('hierarchyNoResults');

        function applySearch() {
            const keyword = (searchInput.value || '').trim().toLowerCase();

            if (!keyword) {
                items.forEach(item => {
                    item.style.display = '';
                });

                if (noResults) {
                    noResults.classList.add('d-none');
                }

                return;
            }

            items.forEach(item => {
                item.style.display = 'none';
            });

            items.forEach(item => {
                const ownText = item.dataset.search || '';
                if (!ownText.includes(keyword)) {
                    return;
                }

                item.style.display = '';

                item.querySelectorAll('.hierarchy-item').forEach(child => {
                    child.style.display = '';
                });

                let parent = item.parentElement.closest('details.hierarchy-item');
                while (parent) {
                    parent.style.display = '';
                    parent.open = true;
                    parent = parent.parentElement.closest('details.hierarchy-item');
                }

                if (item.tagName.toLowerCase() === 'details') {
                    item.open = true;
                }
            });

            const visibleTopLevel = items.filter(item => {
                return !item.closest('.hierarchy-children') && item.style.display !== 'none';
            }).length;

            noResults?.classList.toggle('d-none', visibleTopLevel > 0);
        }

        document.getElementById('expandAll')?.addEventListener('click', function () {
            document.querySelectorAll('#hierarchyList details').forEach(item => {
                item.open = true;
            });
        });

        document.getElementById('collapseAll')?.addEventListener('click', function () {
            document.querySelectorAll('#hierarchyList details').forEach(item => {
                item.open = false;
            });
        });

        searchInput?.addEventListener('input', applySearch);
    });
</script>
@endsection
