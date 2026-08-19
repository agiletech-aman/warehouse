# Warehouse monitoring system Admin (Laravel)

Ye project **Warehouse monitoring system** ke liye admin dashboard provide karta hai. Admin login karke system ka overview dekh sakta hai: **Warehouses/Regions/Devices/Readings/Alerts** aur **Reports** (filters + server-side DataTables + PDF/Excel/CSV export) aur **Email routing settings**.

---

## Tech Stack
- **Laravel** (Controllers, Eloquent Models, Blade views)
- **Bootstrap 5** (UI)
- **jQuery + DataTables** (server-side table in Reports)
- **DataTables Buttons (ColVis)** (column visibility)
- **maatwebsite/excel** (Excel/CSV export)
- **barryvdh/laravel-dompdf** (PDF export)

---

## Admin Access Flow
### Routes (web)
`routes/web.php` me main routes:
- `GET /` → `AdminController@showLogin`  (**name: admin.login**)
- `POST /` → `AdminController@login`  (**name: admin.login.post**)
- `GET /admin/dashboard` → `AdminController@dashboard` (**name: admin.dashboard**)
- `POST /admin/logout` → `AdminController@logout` (**name: admin.logout**)
- `GET /admin/settings` → `AdminController@settings` (**name: admin.settings**)
- `POST /admin/change-password` → `AdminController@changePassword` (**name: admin.change-password**)

Resource modules:
- `regions/*` → `RegionController`
- `warehouses/*` → `WarehouseController`
- `devices/*` → `DeviceController`
- `readings/*` → `ReadingController`
- `alerts/*` → `AlertController`

Reports:
- `GET /admin/reports` → `ReportController@index` (**name: reports.index**)
- `GET /admin/reports/data` → `ReportController@data` (**name: reports.data**)
- `GET /admin/reports/summary` → `ReportController@summary` (**name: reports.summary**)
- `GET /admin/reports/export/{format}` → `ReportController@export` (**name: reports.export**)

Settings (email routing + CC emails):
- `GET /admin/settings/email-routing` → `EmailRoutingController@index` (**name: settings.email-routing**)
- `POST /admin/settings/email-routing` → `EmailRoutingController@update` (**name: settings.email-routing.update**)
- `POST /settings/cc-emails` → `AdminController@storeCcEmail` (**name: settings.cc-emails.store**)
- `PUT /settings/cc-emails/{id}` → `AdminController@updateCcEmail` (**name: settings.cc-emails.update**)
- `DELETE /settings/cc-emails/{id}` → `AdminController@destroyCcEmail` (**name: settings.cc-emails.destroy**)
- `POST /settings/cc-emails/{id}/toggle` → `AdminController@toggleCcEmail` (**name: settings.cc-emails.toggle**)

Hierarchy page:
- `GET /hierarchy` (inline closure) → `resources/views/hierarchy/index.blade.php`

### Session keys (Admin)
`AdminController@login()` login success pe session me:
- `admin_id`
- `admin_name`
- `admin_email`

Dashboard/Settings ke start me session check:
- agar `admin_id` nahi hai → redirect `/` (login)

---

## Layout / Navigation
### `resources/views/layouts/app.blade.php`
- Fixed **Sidebar** + collapsible behavior (localStorage key: `warehouseSidebarCollapsed`)
- Sidebar links with active highlight using `request()->routeIs(...)`
- Logout button in sidebar footer (`route('admin.logout')`)
- Content area me `@yield('content')`

---

## Dashboard (Overview)
### Controller: `app/Http/Controllers/AdminController.php`
`dashboard()` dashboard page ko following data deta hai:

1) **Total Warehouses**
- `Warehouse::count()`

2) **Active Warehouses (last 24 hours)**
- logic: **Warehouse active** = us warehouse se related `readings` me `recorded_at >= now()-24h` exist karta hai.
- implementation me 2 key matching attempts hain:
  - `readings.warehouse_code` == `warehouses.warehouse_code`
  - OR `readings.warehouse` == `warehouses.warehouse_name`

3) **Total Regions**
- `Region::count()`

4) **Online/Offline Devices (last 24 hours)**
- `Reading` table se latest/seen status based best-effort:
  - last 24h me `sensor_device_id` distinct groups
  - Online: group me **online** status present hai (and offline absent) kind of logic
  - Offline: group me **offline** status present hai

5) **Critical Active Alerts**
- `Alert::where(active = true)`
- alert `type` me `critical`, `severe`, `device_offline` count hota hai

6) **Last 24h Alerts Count**
- `Alert::where(created_at >= now()-24h)->count()`

7) **Latest Readings (Top 5)**
- `Reading::with('device')->latest('recorded_at')->take(5)->get()`

### View: `resources/views/admin/dashboard.blade.php`
Dashboard UI me:
- 4 top stat cards:
  - Total Warehouses
  - Active Warehouses
  - Regions
  - Last 24h Alerts
- 2 cards:
  - Device Status (Online/Offline badges)
  - Critical Active Alerts (Active badge)
- Latest Readings table (Top 5)
  - Level badge (critical/severe/normal)
  - Status badge (online/offline/unknown)
- Quick Actions buttons:
  - `readings.index`, `alerts.index`, `devices.index`, `warehouses.index`

---

## Settings (Email)
### Models
- `app/Models/EmailRouting.php` → `email_routing` table ko represent karta hai
- `app/Models/CcEmail.php` → `cc_emails` table ko represent karta hai

### Controller: `AdminController`
`settings()` method:
- session check
- `EmailRouting::all()` ko keyBy: `device_type + '_' + level`
- `CcEmail::orderBy('email')->get()`
- `resources/views/admin/settings.blade.php` ko data bhejta hai

### CC Emails Management
- `storeCcEmail(Request)`:
  - validate `email` required|email|unique:cc_emails,email
  - create with `status` boolean
- `updateCcEmail(Request, $id)`:
  - unique validation with current id exclusion
  - update email only
- `destroyCcEmail($id)`:
  - delete record
- `toggleCcEmail($id)`:
  - `status = !status`

### Password Change
`changePassword(Request)`:
- validate:
  - `old_password` required
  - `new_password` min 8 + confirmed
- session admin ko fetch
- old password Hash check
- new password Hash store

---

## Reports Dashboard (Filters + Table + Summary + Export)
### View: `resources/views/reports/index.blade.php`
Ye page ek complete “Reports Dashboard” hai jo data ko dynamic load karta hai:

#### 1) Filters UI
Form: `#filtersForm`
- `from_date`, `to_date`
- `region_code` (dynamic)
- `warehouse_code` (disabled until region selected)
- `device_code` (disabled until warehouse selected)

Cascading dropdown logic:
- Regions load: `GET /api/regions?per_page=1000`
- Warehouses load: `GET /api/warehouses?per_page=1000&region_code=...`
- Devices load: `GET /api/devices?per_page=1000&warehouse_code=...`

#### 2) Summary Cards
AJAX call:
- `GET /admin/reports/summary?{filters...}`
Response se:
- total_readings
- total_devices
- online_devices
- offline_devices
- severe_alerts
- critical_alerts
- regions_count
- warehouses_count

#### 3) Server-side DataTable
DataTables init:
- `serverSide: true`
- endpoint: `GET /admin/reports/data`
- filters DataTable request me attach hotay hain via `getFilters()`.

Table columns (data keys as per controller mapping):
- date_time, region, region_code, warehouse, warehouse_code
- device_name, device_code (sensor_device_id), device_type, device_ip
- value, unit, level, status

Level/Status UI rendering:
- level badge: normal/severe/critical
- status badge: online/offline

Columns visibility:
- ColVis button (DataTables Buttons)

#### 4) Export Buttons
Export links build hotay hain:
- `GET /admin/reports/export/pdf?{filters...}`
- `GET /admin/reports/export/excel?{filters...}`
- `GET /admin/reports/export/csv?{filters...}`

Print button: `window.print()`

---

## Reports Backend Logic
### Controller: `app/Http/Controllers/ReportController.php`

#### `index()`
- `return view('reports.index')`

#### `data(Request)` (DataTables server-side)
Input:
- DataTables: `draw`, `start`, `length`
- plus filters via query

Processing:
1. `$filters = extractFilters($request)`
2. `$readingsBase = buildReadingsQuery($filters)`
3. `recordsTotal` = all Reading count (global)
4. `recordsFiltered` = filtered count
5. Data slice:
   - latest('recorded_at')
   - offset($start), limit($length)
   - get([...columns...])
6. Response mapping: har row me UI-ready keys set (date_time, region, etc.)

Response JSON:
- draw, recordsTotal, recordsFiltered, data

#### `summary(Request)`
Filters extract + readings query build.
Calculations:
- total_readings = filtered readings count

Devices counts:
- `Device` table se (optional device_type + region/warehouse filters apply)
- agar `status` filter hai (online/offline) → devices table me `status` active/inactive proxy mapping:
  - online → active
  - offline → inactive

Alerts counts:
- severity counts `Alert.type` based within date range using `buildAlertsQuery($filters)`

Regions/Warehouses count:
- agar region/warehouse explicitly filter hai → 1
- warna readingsBase se distinct counts

Charts preparation:
- `buildCharts()` returns payload arrays (trend, alerts trend, level distribution, region wise device counts)

Response JSON:
- `success: true`
- `stats: {...}`
- `charts: {...}`

#### `export(Request, format)`
format: `pdf | excel | csv`

PDF:
- `PDF::loadView('reports.exports.pdf', $pdfData)->setPaper('a4','landscape')`
- rows: `buildReadingsQuery($filters)->latest('recorded_at')->limit(5000)`

Excel/CSV:
- `ReportExport($filters)` used
- `Excel::download($export, 'reports_YYYYmmdd_His.xlsx')`
- CSV: `Excel::download(..., 'reports_...csv', Excel::CSV)`

---

## Internal Query Helpers (Reports)
### `extractFilters(Request)`
Filters normalize karta hai:
- from_date, to_date
- region_id/region_code/region_name
- warehouse_id/warehouse_code/warehouse_name
- device_type, device_code, device_name
- status, level
- report_type (default: reading)

### `buildReadingsQuery(array $filters)`
- base: `Reading::query()`
- date range filter on `recorded_at`
- region/warehouse/device/level/status filters apply kar ke readings ko filter karta hai

Special report_type cases:
- `alert` / `severe_alert` / `critical_alert`
  - `alerts` table se `reading_id` pluck karke readings query me `whereIn('id', ...)`
- `offline_device`
  - readings status = offline (schema ke basis par approximation)

### `buildAlertsQuery(array $filters)`
- `Alert::query()->whereNull('deleted_at')`
- date range on `created_at`
- optional `device_id` filter

### `alertReadingIds(array $filters, ?string $type)`
- Alert rows se `reading_id` pluck+unique
- type/date/device filters apply kar ke reading IDs return karta hai

### `applyRegionWarehouseToDevices($devicesQuery, $filters)`
- Device query pe `whereHas('warehouse')` and `whereHas('warehouse.region')`
- best-effort region/warehouse constraints

### `buildCharts(array $filters)`
- bucket select: day/week/month based on report_type
- trend:
  - readings: `recorded_at` bucketing (PHP level)
  - alerts: `created_at` bucketing
- level distribution:
  - readingsBase counts: normal/severe/critical
- region wise device count:
  - readingsBase se `distinct sensor_device_id per region_code` set create karke count

---

## Hierarchy Page
`routes/web.php` me `/hierarchy` ek inline route hai jo:
- latest readings ko `sensor_device_id` unique karta hai
- un ko region + warehouse keys ke basis pe group karta hai
- `resources/views/hierarchy/index.blade.php` ko `regions` structure bhejta hai:
  - region object: region_code, region_name, manager_name, status, warehouses_count, warehouses[]
  - warehouse object: warehouse_code, warehouse_name, manager_name, status, devices_count, devices[]

---

## How to Run
1. Dependencies:
   - `composer install`
   - `npm install` / `npm run dev` (agar frontend assets required hain)
2. Environment:
   - `.env` configure (DB + mail settings agar needed)
3. Migrations:
   - `php artisan migrate`
4. Start server:
   - `php artisan serve`
5. Browser:
   - `/` (login)
   - `/admin/dashboard`
   - `/admin/reports`
   - `/admin/settings`

---

## Notes / Assumptions (important)
- Dashboard “active warehouses” aur “online/offline devices” ko **readings last 24 hours** se infer kiya gaya hai.
- Reports me device online/offline ka mapping “devices.status (active/inactive)” aur readings.status (online/offline) ke beech best-effort proxy hai (ReportController@summary me comment ke saath).

---

## Modules Quick Reference
- **Dashboard**: `AdminController@dashboard` + `admin/dashboard.blade.php`
- **Settings (Email)**: `AdminController@settings` + CC email methods + `EmailRoutingController`
- **CRUD Modules**: `regions`, `warehouses`, `devices`, `readings`, `alerts` resource controllers
- **Hierarchy**: `/hierarchy` route closure + hierarchy view
- **Reports**: `ReportController@index/data/summary/export` + `reports/index.blade.php`

