<form id="fnsDetectionFilters" class="row g-3 align-items-end mb-4">
    <div class="col-md-3">
        <label for="fnsFilterType" class="form-label fw-semibold">Detection</label>
        <select id="fnsFilterType" name="detection_type" class="form-select">
            <option value="">All detections</option>
            @foreach ($detectionTypes as $detectionType)
                <option value="{{ $detectionType }}">{{ ucfirst(str_replace('_', ' ', $detectionType)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="fnsFilterWarehouse" class="form-label fw-semibold">Warehouse</label>
        <input type="text" id="fnsFilterWarehouse" name="warehouse" class="form-control" placeholder="Name or code" autocomplete="off">
    </div>
    <div class="col-md-2">
        <label for="fnsFilterFrom" class="form-label fw-semibold">From Date</label>
        <input type="date" id="fnsFilterFrom" name="from_date" class="form-control">
    </div>
    <div class="col-md-2">
        <label for="fnsFilterTo" class="form-label fw-semibold">To Date</label>
        <input type="date" id="fnsFilterTo" name="to_date" class="form-control">
    </div>
    <div class="col-md-2">
        <button type="reset" class="btn btn-outline-secondary w-100">Reset</button>
    </div>
</form>
