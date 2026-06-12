@extends('layouts.app')

@section('title','Hierarchy Management')

@section('content')
<style>
    :root{
        --tree-line:#e5e7eb;
        --row-hover:#f8fafc;
        --card-bg:#ffffff;
        --card-border:#e8eef6;
        --shadow:0 10px 30px rgba(15, 23, 42, 0.06);
    }

    /* Enterprise dashboard container */
    .hierarchy-shell{padding:0;}

    .hierarchy-header{
        background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border:1px solid var(--card-border);
        border-radius:18px;
        padding:18px 18px;
        box-shadow:0 6px 18px rgba(15, 23, 42, 0.04);
    }

    .hierarchy-tree{
        margin-top:14px;
        border-radius:18px;
        overflow:hidden;
        border:1px solid var(--card-border);
        background:#ffffff;
        box-shadow:0 12px 40px rgba(15, 23, 42, 0.05);
    }

    /* Full width cards */
    .tree-card{
        width:100%;
        border:1px solid var(--card-border);
        background:var(--card-bg);
        border-radius:16px;
        padding:14px 14px;
        box-shadow:none;
        transition:transform .15s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease;
    }

    .tree-card:hover{
        background:var(--row-hover);
        border-color:#dbe7f7;
        transform:translateY(-1px);
        box-shadow:0 10px 26px rgba(15, 23, 42, 0.06);
    }

    .tree-card:active{transform:translateY(0px);}

    .tree-row{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
    }

    .tree-row-left{
        display:flex;
        align-items:flex-start;
        gap:12px;
        min-width:0;
    }

    .tree-indent{
        width:18px;
        height:18px;
        border-radius:6px;
        background:rgba(59, 130, 246, 0.08);
        display:flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
        color:#1d4ed8;
        font-weight:800;
        font-size:12px;
    }

    .tree-meta{
        min-width:0;
    }

    .tree-title{
        font-weight:800;
        color:#0f172a;
        line-height:1.25;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .tree-sub{
        color:#6b7280;
        font-size:12px;
        margin-top:2px;
        display:flex;
        gap:10px;
        flex-wrap:wrap;
    }

    .tree-metrics{
        display:flex;
        gap:14px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .metric{
        min-width:120px;
        text-align:right;
    }
    .metric .label{font-size:12px;color:#6b7280;}
    .metric .value{font-size:16px;font-weight:900;color:#111827;margin-top:2px;}

    .expand-btn{
        border:none;
        background:transparent;
        color:#334155;
        display:flex;
        align-items:center;
        justify-content:center;
        width:40px;
        height:40px;
        border-radius:12px;
        transition:background .15s ease, transform .15s ease;
        flex-shrink:0;
    }

    .tree-card:hover .expand-btn{background:rgba(15, 23, 42, 0.04);}

    .chev{
        display:inline-flex;
        transform:rotate(0deg);
        transition:transform .2s ease;
    }

    .expanded > .tree-row .chev{transform:rotate(90deg);}

    /* Tree nesting containers */
    .children{
        margin-left:22px;
        padding-left:14px;
        border-left:2px solid rgba(37, 99, 235, 0.12);
        overflow:hidden;
        max-height:0;
        opacity:0;
        transition:max-height .35s ease, opacity .25s ease;
    }

    .expanded + .children{
        max-height:1600px;
        opacity:1;
    }

    .tree-node{padding:12px 14px;}

    /* Status badges */
    .status-online{background:rgba(16, 185, 129, .12); color:#059669; border:1px solid rgba(16, 185, 129, .25);}
    .status-offline{background:rgba(239, 68, 68, .10); color:#dc2626; border:1px solid rgba(239, 68, 68, .25);}
    .status-warning{background:rgba(245, 158, 11, .12); color:#d97706; border:1px solid rgba(245, 158, 11, .25);}

    .status-badge{
        font-size:12px;
        font-weight:800;
        padding:6px 10px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        white-space:nowrap;
    }

    .dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
    .dot-online{background:#10b981;}
    .dot-offline{background:#ef4444;}
    .dot-warning{background:#f59e0b;}

    /* Search bar */
    .search-card{
        border:1px solid var(--card-border);
        border-radius:16px;
        padding:14px 14px;
        background:#fff;
        box-shadow:0 10px 26px rgba(15, 23, 42, 0.04);
    }

    .form-control, .form-select{border-radius:12px;}

    /* Mobile friendly */
    @media (max-width: 575.98px){
        .metric{min-width:auto;text-align:left;}
        .tree-metrics{justify-content:flex-start;}
        .tree-row{flex-direction:column;align-items:flex-start;}
        .expand-btn{position:absolute; right:8px; top:10px;}
        .tree-node{position:relative;}
        .children{margin-left:10px; padding-left:12px;}
    }
</style>

<div class="content-shell hierarchy-shell">
    <div class="hierarchy-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="eyebrow">Hierarchy Management</div>
                <h3 class="mt-2 mb-0">Regions → Warehouses → Devices</h3>
                <small class="text-muted">Nested expand/collapse tree with search and status indicators</small>
            </div>

            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <div class="search-card d-flex align-items-center gap-2">
                    <i class="bi bi-search text-primary"></i>
                    <input id="hierarchySearch" class="form-control border-0 shadow-none" style="min-width:240px;" placeholder="Search region / warehouse / device..." />
                </div>

                <button id="btnCollapseAll" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-arrows-collapse"></i> Collapse All
                </button>
                <button id="btnExpandAll" class="btn btn-primary rounded-pill px-3">
                    <i class="bi bi-arrows-expand"></i> Expand All
                </button>
            </div>
        </div>
    </div>

    <div class="hierarchy-tree p-3 p-md-3">
        <div id="treeRoot" class="pt-1">
            <!-- Dummy data nodes rendered by JS -->
        </div>

        <div class="text-center py-4 text-muted" id="treeEmpty" style="display:none;">
            <div class="display-6 fw-bold">No results</div>
            <div class="small">Try a different keyword.</div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<script>
    // Dummy enterprise-like data
    const dummyData = [
        {
            region_id: 'r1',
            region_name: 'North America',
            region_code: 'NA-01',
            totalWarehouses: 5,
            warehouses: [
                {
                    warehouse_id: 'w1',
                    warehouse_name: 'Detroit Cold Storage',
                    warehouse_code: 'DET-7',
                    totalDevices: 12,
                    devices: [
                        {device_id:'d1', device_name:'CO2 Sensor A1', device_code:'CO2-DET-A1', device_type:'CO2', status:'online', last_reading:'2026-06-12 10:12:44'},
                        {device_id:'d2', device_name:'Phosphorus Probe P1', device_code:'PHO-DET-P1', device_type:'Phosphorus', status:'warning', last_reading:'2026-06-12 09:59:12'},
                        {device_id:'d3', device_name:'CO2 Sensor A2', device_code:'CO2-DET-A2', device_type:'CO2', status:'offline', last_reading:'2026-06-12 09:18:03'},
                    ]
                },
                {
                    warehouse_id: 'w2',
                    warehouse_name: 'Chicago Ambient Warehouse',
                    warehouse_code: 'CHI-3',
                    totalDevices: 9,
                    devices: [
                        {device_id:'d4', device_name:'Temperature Logger T1', device_code:'TMP-CHI-T1', device_type:'Temperature', status:'online', last_reading:'2026-06-12 10:07:01'},
                        {device_id:'d5', device_name:'Humidity Logger H1', device_code:'HUM-CHI-H1', device_type:'Humidity', status:'online', last_reading:'2026-06-12 10:06:35'},
                    ]
                }
            ]
        },
        {
            region_id: 'r2',
            region_name: 'Europe',
            region_code: 'EU-02',
            totalWarehouses: 3,
            warehouses: [
                {
                    warehouse_id: 'w3',
                    warehouse_name: 'Berlin Central',
                    warehouse_code: 'BER-1',
                    totalDevices: 7,
                    devices: [
                        {device_id:'d6', device_name:'CO2 Sensor B1', device_code:'CO2-BER-B1', device_type:'CO2', status:'online', last_reading:'2026-06-12 10:11:22'},
                        {device_id:'d7', device_name:'Phosphorus Probe B2', device_code:'PHO-BER-B2', device_type:'Phosphorus', status:'warning', last_reading:'2026-06-12 10:00:10'}
                    ]
                }
            ]
        }
    ];

    const statusToClass = {
        online: 'status-online',
        offline: 'status-offline',
        warning: 'status-warning'
    };

    const statusToDot = {
        online: 'dot-online',
        offline: 'dot-offline',
        warning: 'dot-warning'
    };

    function esc(str){
        return String(str).replace(/[&<>"']/g, function(c) {
            switch(c){
                case '&': return '&amp;';
                case '<': return '<';
                case '>': return '>';
                case '"': return '"';
                case "'": return '&#039;';
                default: return c;
            }
        });
    }


    function fmtLastReading(val){
        // keep as-is for dummy data; in real use parse ISO/DB
        return val ? String(val).replace(' ', ' · ') : '-';
    }

    function badgeHTML(status){
        const cls = statusToClass[status] || 'status-offline';
        const dotCls = statusToDot[status] || 'dot-offline';
        const label = status === 'online' ? 'Online' : status === 'warning' ? 'Warning' : 'Offline';
        return `
            <span class="status-badge ${cls}">
                <span class="dot ${dotCls}"></span>
                ${label}
            </span>
        `;
    }

    function makeDeviceCard(device, indent){
        const content = `
            <div class="tree-node">
                <div class="tree-card" role="button" tabindex="0" aria-expanded="false"
                     data-node-type="device"
                     data-search="${esc(device.device_name)} ${esc(device.device_code)} ${esc(device.device_type)} ${esc(device.status)}"
                     data-id="${esc(device.device_id)}">
                    <div class="tree-row">
                        <div class="tree-row-left">
                            <div class="tree-indent">D</div>
                            <div class="tree-meta">
                                <div class="tree-title">${esc(device.device_name)}</div>
                                <div class="tree-sub">
                                    <span><strong>Code:</strong> ${esc(device.device_code)}</span>
                                    <span><strong>Type:</strong> ${esc(device.device_type)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="tree-metrics">
                            <div class="metric">
                                <div class="label">Last Reading</div>
                                <div class="value" style="font-size:14px; font-weight:800;">${esc(fmtLastReading(device.last_reading))}</div>
                            </div>
                            ${badgeHTML(device.status)}

                            <button class="expand-btn" type="button" aria-label="Device expand">
                                <span class="chev"><i class="bi bi-chevron-right"></i></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="children" style="border-left-color:rgba(37,99,235,0.08);"></div>
            </div>
        `;

        return content;
    }

    function makeWarehouseCard(warehouse){
        const content = `
            <div class="tree-node">
                <div class="tree-card" role="button" tabindex="0" aria-expanded="false"
                     data-node-type="warehouse"
                     data-search="${esc(warehouse.warehouse_name)} ${esc(warehouse.warehouse_code)}"
                     data-id="${esc(warehouse.warehouse_id)}">
                    <div class="tree-row">
                        <div class="tree-row-left">
                            <div class="tree-indent">W</div>
                            <div class="tree-meta">
                                <div class="tree-title">${esc(warehouse.warehouse_name)}</div>
                                <div class="tree-sub">
                                    <span><strong>Code:</strong> ${esc(warehouse.warehouse_code)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="tree-metrics">
                            <div class="metric">
                                <div class="label">Total Devices</div>
                                <div class="value">${esc(warehouse.totalDevices)}</div>
                            </div>

                            <button class="expand-btn" type="button" aria-label="Warehouse expand">
                                <span class="chev"><i class="bi bi-chevron-right"></i></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="children" aria-hidden="true">
                    ${warehouse.devices.map(d => makeDeviceCard(d)).join('')}
                </div>
            </div>
        `;

        return content;
    }

    function makeRegionCard(region){
        const content = `
            <div class="tree-node">
                <div class="tree-card" role="button" tabindex="0" aria-expanded="false"
                     data-node-type="region"
                     data-search="${esc(region.region_name)} ${esc(region.region_code)}"
                     data-id="${esc(region.region_id)}">
                    <div class="tree-row">
                        <div class="tree-row-left">
                            <div class="tree-indent">R</div>
                            <div class="tree-meta">
                                <div class="tree-title">${esc(region.region_name)}</div>
                                <div class="tree-sub">
                                    <span><strong>Code:</strong> ${esc(region.region_code)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="tree-metrics">
                            <div class="metric">
                                <div class="label">Total Warehouses</div>
                                <div class="value">${esc(region.totalWarehouses)}</div>
                            </div>

                            <button class="expand-btn" type="button" aria-label="Region expand">
                                <span class="chev"><i class="bi bi-chevron-right"></i></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="children" aria-hidden="true">
                    ${region.warehouses.map(w => makeWarehouseCard(w)).join('')}
                </div>
            </div>
        `;

        return content;
    }

    function renderTree(){
        const root = document.getElementById('treeRoot');
        root.innerHTML = dummyData.map(r => makeRegionCard(r)).join('');
        bindTreeInteractions();
    }

    function setExpanded(cardEl, expanded){
        const node = cardEl;
        if(expanded){
            node.classList.add('expanded');
            node.setAttribute('aria-expanded','true');
        }else{
            node.classList.remove('expanded');
            node.setAttribute('aria-expanded','false');
        }

        // The CSS uses sibling selector: .expanded + .children
        const children = node.parentElement.querySelector(':scope > .children');
        if(children){
            children.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        }
    }

    function toggleCard(cardEl){
        const expanded = cardEl.classList.contains('expanded');
        setExpanded(cardEl, !expanded);
    }

    function collapseAll(){
        document.querySelectorAll('#treeRoot .tree-card').forEach(el => setExpanded(el,false));
    }

    function expandAll(){
        // Expand only regions and warehouses (devices are leaf)
        document.querySelectorAll('#treeRoot .tree-card').forEach(el =>{
            const type = el.dataset.nodeType;
            if(type === 'region' || type === 'warehouse') setExpanded(el,true);
        });
    }

    function bindTreeInteractions(){
        document.querySelectorAll('#treeRoot .tree-card').forEach(card =>{
            card.addEventListener('click', (e)=>{
                const type = card.dataset.nodeType;
                if(type === 'device') return; // leaf
                toggleCard(card);
            });
            card.addEventListener('keydown', (e)=>{
                if(e.key === 'Enter' || e.key === ' '){
                    e.preventDefault();
                    const type = card.dataset.nodeType;
                    if(type === 'device') return;
                    toggleCard(card);
                }
            });
        });
    }

    // Search: show only matching nodes and expand their ancestors
    function applySearch(keyword){
        const kw = keyword.trim().toLowerCase();
        const cards = Array.from(document.querySelectorAll('#treeRoot .tree-card'));
        if(!kw){
            cards.forEach(c => c.closest('.tree-node').style.display='');
            // keep current expanded state
            document.getElementById('treeEmpty').style.display='none';
            return;
        }

        let anyMatch = false;
        // First hide all
        cards.forEach(c => c.closest('.tree-node').style.display='none');

        // Expand regions/warehouses when their descendants match
        document.querySelectorAll('#treeRoot [data-node-type]').forEach(card =>{
            const searchable = (card.dataset.search || '').toLowerCase();
            const matchesSelf = searchable.includes(kw);
            if(matchesSelf){
                anyMatch = true;
                // show this node
                card.closest('.tree-node').style.display='';

                // show ancestors
                let parent = card.parentElement;
                while(parent && parent !== document.getElementById('treeRoot')){
                    if(parent.classList.contains('children')){
                        // sibling is the card
                        const prev = parent.previousElementSibling;
                        if(prev && prev.classList && prev.classList.contains('tree-card')){
                            prev.closest('.tree-node').style.display='';
                            prev.classList.add('expanded');
                            prev.setAttribute('aria-expanded','true');
                        }
                    }
                    parent = parent.parentElement;
                }
            }
        });

        // Also ensure ancestors of matching device nodes are visible (handled above)

        document.getElementById('treeEmpty').style.display = anyMatch ? 'none' : '';

        // Hide children blocks for non-visible ancestors? CSS handles max-height; keep them expanded if needed.
    }

    // Buttons
    document.getElementById('btnCollapseAll').addEventListener('click', ()=>{
        document.getElementById('hierarchySearch').value = '';
        collapseAll();
        applySearch('');
    });

    document.getElementById('btnExpandAll').addEventListener('click', ()=>{
        document.getElementById('hierarchySearch').value = '';
        expandAll();
        applySearch('');
    });

    document.getElementById('hierarchySearch').addEventListener('input', (e)=>{
        applySearch(e.target.value);
    });

    // Render now
    renderTree();
    // start collapsed
    collapseAll();

</script>
@endsection

