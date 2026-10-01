# ATS Warehouse Monitoring System

Ye Laravel application warehouse operations aur sensor/camera monitoring ke liye admin portal aur ingestion APIs provide karti hai. Operations team ek jagah se **regions, warehouses, devices, live status, historical readings, alerts, camera detections, reports aur alert email routing** manage kar sakti hai.

System ka main purpose:

- Sensor aur camera systems se aane wale events ko receive karke database mein rakhna.
- Admin ko current device status aur past readings/alerts dekhne dena.
- Warehouse hierarchy aur operational records ko maintain karna.
- Severe/critical/offline events par configured recipients ko alert email bhejna.
- Reports ko filter, summarize aur export karna.

## Project at a glance

| Part | Kya karta hai |
|---|---|
| Admin web portal | Login-protected dashboard, CRUD screens, hierarchy, detections aur reports |
| Sensor APIs | Readings receive karna, current device status update karna, alerts process karna |
| FNS camera APIs | Fire/smoke/rodent aur doosre camera detections, snapshots aur location fields store karna |
| Database | Master data, event history, latest device status, alert configuration aur camera detections rakhna |
| Laravel scheduler | Stale devices, FNS locations aur unknown readings ke scheduled maintenance tasks chalana |
| Exports | CSV, Excel aur PDF reports generate karna |

## Table of contents

- [Tech stack](#tech-stack)
- [System data flow](#system-data-flow)
- [Admin access and navigation](#admin-access-flow)
- [Monitoring modules](#monitoring-modules)
- [FNS camera detections](#fns-camera-detections)
- [API reference](#api-reference)
- [Database overview](#database-overview)
- [Reports dashboard](#reports-dashboard)
- [Scheduled jobs and cron](#scheduled-jobs-and-cron)
- [Configuration](#configuration)
- [Local setup](#how-to-run)
- [Production deployment](#production-deployment)
- [Troubleshooting](#troubleshooting)

---

## Tech Stack
- **PHP 8.2+ / Laravel 12** (Controllers, Eloquent Models, Blade views, routing, validation, scheduler)
- **Bootstrap 5** (UI)
- **jQuery + DataTables** (server-side searchable/paged tables)
- **DataTables Buttons (ColVis)** (column visibility)
- **maatwebsite/excel** (Excel/CSV export)
- **barryvdh/laravel-dompdf** (PDF export)
- **Vite + npm** (frontend asset build/dev server)
- **Laravel database/cache/session/filesystem/mail drivers** configured through `.env`

## System data flow

### Sensor readings

1. A sensor service sends a batch of readings to `POST /api/readings`.
2. The API stores historical rows in `readings` and updates one current row per sensor in `device_latest_status`.
3. Reading values and incoming severity/status are normalized before they are used by dashboard summaries.
4. The API resolves a matching device/warehouse where possible, updates online device state, creates or closes alerts, and sends email according to routing settings.
5. The dashboard uses `device_latest_status` for current state; reports use historical `readings` and `alerts` for trends and date ranges.

`device_latest_status` is a projection for fast “what is happening now?” queries. It does not replace the history table. Sensor identifiers that are missing can remain in history but cannot form a stable current-status row.

### Camera detections

1. A camera/FNS service posts a detection to one of the FNS endpoints.
2. The API stores the detection, its warehouse code, classification, confidence, bounding box, timestamp and optional snapshot path.
3. Uploaded or Base64 snapshots are stored on Laravel's public filesystem under `snapshots/`; the database stores the path.
4. The admin detections table reads from `fns_detections` or `fns_detections_02`. Clicking a snapshot thumbnail opens the full image in a modal.
5. Godown and compartment are persisted in their own database columns. The camera `name` remains unchanged; the UI displays the stored location fields rather than deriving them from the name.

### Warehouse hierarchy and reports

Regions contain warehouses, and warehouses can have devices. Readings are also matched using stored region/warehouse codes or names because not every external sensor payload has an internal database ID. Reports combine available master data with readings/alerts and use best-effort matching where IDs are missing.

## Monitoring modules

| Module | Use |
|---|---|
| Dashboard | Current device online/offline counts, active warehouses, alerts and latest sensor status |
| Regions | Region master data, managers and warehouse grouping |
| Warehouses | Warehouse master data, region association, contact/location fields and status |
| Devices | Device inventory, warehouse association, status and device summaries |
| Readings | Historical sensor data, searchable/paged listing and reading detail |
| Alerts | Alert history and active/inactive state management |
| Hierarchy | Current region → warehouse → device view based on latest device status |
| FNS Detections | Camera detection history with location, classification and snapshot preview |
| Reports | Date/region/warehouse/device filters, summary cards/charts and export |
| Settings | Alert email routing and CC recipient management |

The dashboard's “active warehouse” count means a warehouse with a related reading in the previous 24 hours, matched by warehouse code or warehouse name. Device live state comes from `device_latest_status`, not by repeatedly scanning all historical readings.

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
- logic: **Warehouse active** = us warehouse ki `device_latest_status` row ka `recorded_at >= now()-24h` ho.
- matching `device_latest_status.warehouse_code` se hoti hai; legacy/name-based rows ke liye `device_latest_status.warehouse` == `warehouses.warehouse_name` bhi check hota hai.

3) **Total Regions**
- `Region::count()`

4) **Online/Offline Devices**
- `device_latest_status` projection se current status count hota hai:
  - `status = online` → online count
  - `status = offline` → offline count

5) **Critical Active Alerts**
- `Alert::where(active = true)`
- alert `type` me `critical`, `severe`, `device_offline` count hota hai

6) **Last 24h Alerts Count**
- `Alert::where(created_at >= now()-24h)->count()`

7) **Latest Readings (Top 5)**
- `DeviceLatestStatus::latest('recorded_at')->take(5)->get()`

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

## Reports Dashboard
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

## FNS Camera Detections

### Web pages

- `/fns/detections` → `fns_detections` table
- `/fns/detections02` → `fns_detections_02` table
- Dono pages server-side paginated/searchable tables use karte hain.
- Snapshot thumbnail par click karne se image modal khulta hai.
- Warehouse code ko `warehouses.warehouse_code` se resolve karke table mein warehouse name dikhaya jata hai; code match na ho to code fallback hota hai.
- `Godown / Compartment` column database ke `godown` aur `compartment` fields ko join karke dikhata hai. Camera `name` column ko location dikhane ke liye modify nahi kiya jata.

### Location values kaise save hote hain

`app/Support/FnsDetectionLocation.php` supported camera names se canonical database values nikalta hai:

| Camera name example | `godown` stored value | `compartment` stored value |
|---|---|---|
| `G3CA SMOKE 2` | `Godown_3` | `Compartment_A` |
| `G3CB CAM1 FIRE/SMOKE` | `Godown_3` | `Compartment_B` |
| `Godown 4 Cam 2` | `Godown_4` | Compartment name mein na ho to existing value unchanged rehti hai |
| `Godown 4 Compartment B Cam 2` | `Godown_4` | `Compartment_B` |

Naye FNS API inserts location ko seedha database mein likhte hain. Existing records ke liye `fns-detections:sync-locations` command dono tables ko chunks mein scan karke values update karti hai. `--dry-run` option sirf proposed updates count karta hai, data change nahi karta.

```bash
php artisan fns-detections:sync-locations --dry-run
php artisan fns-detections:sync-locations
```

### Snapshot setup

- Laravel public disk par snapshots `snapshots/` folder ke andar save hote hain.
- Local/deployment setup mein public storage link chahiye: `php artisan storage:link`.
- `snapshot_path` database mein path rakhta hai; model `snapshot_url` response field generate karta hai.
- Legacy `http://` aur `https://` image URLs bhi read kiye ja sakte hain.

---

## API Reference

API routes `routes/api.php` mein hain. Base path `/api` hai. Request validation aur response format controller ke endpoint ke hisaab se vary karta hai.

| Method | Path | Use |
|---|---|---|
| `POST` | `/api/login` | Admin/client login flow |
| `POST` | `/api/warehouse/login` | Warehouse login flow |
| `POST` | `/api/readings` | Sensor readings batch receive/store, latest status aur alerts process karna |
| `GET` | `/api/readings/summary` | Current status ya date-filtered historical readings with level counts |
| `GET` | `/api/regions` | Region list/filter |
| `GET` | `/api/warehouses` | Warehouse list/filter |
| `GET` | `/api/devices` | Device list/filter |
| `GET` | `/api/alerts` | Alert list, counts and active filters |
| `GET` | `/api/fns/detections` | FNS detections list with search/filter/pagination |
| `POST` | `/api/fns/detections` | FNS detection insert into the first detections table |
| `POST` | `/api/fns/detections02` | Protected FNS push insert into the second detections table |
| `GET/POST` | `/api/master-alerts...` | Master alert, summary, device, region/warehouse and export integrations |
| `GET/POST` | `/api/master-alert-summary...` | Master alert summary save/dashboard integration |

Master alert routes include `/api/master-alerts/summary`, `/api/master-alerts/devices`, `/api/master-alerts/states`, `/api/master-alerts/export`, state-location lookup, region/warehouse lookup and alert detail endpoints. Full route patterns `routes/api.php` mein hain; ID parameters numeric where applicable.

`/api/readings/summary` bina date range ke current-status projection use karta hai. `start_date`/`end_date` ya supported date aliases dene par historical readings use karta hai. Common filters mein region/warehouse codes, names, device status aur reading level shamil hain.

`/api/fns/detections02` request ko `X-Push-Secret` header se validate karta hai. Secret `FNS_PUSH_SECRET` environment configuration se aana chahiye; secret ko README, source control, shell history ya shared logs mein paste na karein. `/api/fns/detections` aur doosre APIs ke auth/validation details ke liye unke API controllers dekhein.

---

## Database Overview

Main application tables:

| Table | Role |
|---|---|
| `admins` | Admin portal accounts |
| `regions` | Region master data and managers |
| `warehouses` | Warehouse master data and region relation |
| `devices` | Registered devices and their operational status |
| `readings` | Historical sensor readings and received metadata |
| `device_latest_status` | One current-state projection row per usable sensor ID |
| `alerts` | Severe/critical/offline alert records and active state |
| `email_routing` | Device type/severity ke liye alert email routing |
| `cc_emails` | Optional CC recipients and enabled state |
| `fns_detections` | First FNS camera detection stream |
| `fns_detections_02` | Second FNS camera detection stream |
| `master_alert_summaries` | Cached/stored master dashboard summary data |

Laravel framework tables may also include `users`, `cache`, `jobs`, `failed_jobs` and `migrations`; exact set deployed migrations and queue/cache configuration par depend karta hai.

**Important distinction:** `readings` historical records hain; `device_latest_status` current state ko fast serve karta hai. `fns_detections` tables camera events store karti hain and are separate from sensor `readings`.

---

## Scheduled Jobs and Cron

Scheduled jobs `routes/console.php` mein define hain:

| Artisan command | Schedule | Purpose |
|---|---|---|
| `devices:mark-stale-offline` | Every 30 minutes | Configured stale cutoff se purane current device statuses ko offline mark karna |
| `fns-detections:sync-locations` | Every 5 minutes | Dono FNS tables mein camera name se godown/compartment columns sync karna |
| `readings:delete-unknown` | Sunday 03:00 | `unknown` level ke historical readings remove karna |

Laravel scheduler ko server par har minute ek system cron se invoke karein. Project directory aur PHP binary ko hosting ke actual paths se replace karein:

```cron
* * * * * cd /var/www/ajeevi-api && /usr/bin/php artisan schedule:run >> /var/log/ajeevi-api-scheduler.log 2>&1
```

Is one-minute runner ke andar Laravel sirf wahi task execute karta hai jo us minute due ho. App ke saare scheduled tasks isi runner se chalenge. `crontab -l` mein har cron task alag physical line par ho aur uske shuru mein paanch time fields hon.

Useful commands:

```bash
php artisan schedule:list
php artisan fns-detections:sync-locations --dry-run
php artisan fns-detections:sync-locations
php artisan devices:rebuild-latest-status --chunk=1000
```

`devices:rebuild-latest-status` historical readings se current-status projection ko dobara banata hai; normal operation mein har request par chalane ki zaroorat nahi. Weekly `readings:delete-unknown` task data delete karta hai, isliye us schedule ko production mein enable karne se pehle retention requirement confirm karein.

---

## Configuration

Secrets aur environment-specific settings `.env` mein rakhein; `.env` ko git mein commit na karein. `.env.example` variable names ka reference hai, production credentials ka source nahi.

| Variable group | Use |
|---|---|
| `APP_*` | App name, environment, URL, debug setting, encryption key and locale |
| `DB_*` | Database driver, server and credentials |
| `MAIL_*` | Alert mail delivery connection/from address |
| `FILESYSTEM_DISK` | Default file storage; FNS snapshots public disk use karte hain |
| `FNS_PUSH_SECRET` | `/api/fns/detections02` push authentication secret |
| `WAREHOUSE_SUPER_ADMIN_*` | Configured warehouse super-admin login provisioning |
| `WAREHOUSE_MANAGER_INITIAL_PASSWORD` | Initial password for warehouse managers |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | Cache/session/queue backends |

`APP_KEY` ko deploy ke beech change na karein; isse encrypted app data/session behavior affect ho sakta hai. `APP_DEBUG=false` production mein rakhein. DB password aur API secrets ko cron command line, README ya tickets mein na likhein.

---

### Mail settings

Alert emails bhejne ke liye working `MAIL_*` configuration chahiye. `email_routing` table mein device type aur severity ke liye alert routing configure hoti hai; `cc_emails` mein optional CC addresses enable/disable kiye ja sakte hain. Mail failures application logs mein record hote hain.

---

## Project structure

| Path | Responsibility |
|---|---|
| `app/Http/Controllers/` | Admin pages, CRUD, reports and API request handlers |
| `app/Http/Middleware/` | Web admin session protection |
| `app/Models/` | Eloquent models and query scopes for database records |
| `app/Services/` | Reusable ingestion, alert, email, summary and latest-status logic |
| `app/Support/` | Shared helpers, including FNS camera-name location parsing |
| `app/Console/Commands/` | Maintenance, sync and rebuild commands |
| `app/Exports/` | Excel/CSV export definitions |
| `routes/web.php` | Admin web routes |
| `routes/api.php` | Sensor, FNS and master integration APIs |
| `routes/console.php` | Artisan commands and scheduler definitions |
| `resources/views/` | Blade pages/layouts for the admin UI |
| `database/migrations/` | Database schema evolution |
| `database/seeders/` | Optional initial/maintenance data seeders |
| `resources/js/`, `resources/css/`, `vite.config.js` | Frontend asset build entry points/configuration |
| `storage/app/public/` | Publicly served uploaded snapshots and files |

---

## How to Run
1. Install dependencies and create the local environment file:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   npm install
   ```

2. `.env` mein database, mail, URL, `FNS_PUSH_SECRET` and required warehouse auth settings configure karein.
3. Database schema banayein:

   ```bash
   php artisan migrate
   ```

4. Snapshot URLs serve karne ke liye public storage link banayein:

   ```bash
   php artisan storage:link
   ```

5. Development mein `npm run dev` ek terminal par aur Laravel web server doosre terminal par chalayein:

   ```bash
   php artisan serve
   ```

6. Admin login:
   - `/` login page hai; successful login ke baad `/admin/dashboard` khulta hai.
   - `DatabaseSeeder` default admin account nahi banata. Pehla admin `admins` table mein create karein, for example `php artisan tinker` ke through aur password `Hash::make(...)` se hash karke. Real password code/README mein na rakhein.

   Tinker example; placeholder email/password ko apne secure values se replace karein:

   ```php
   \App\Models\Admin::create([
       'name' => 'Warehouse Admin',
       'email' => 'admin@example.com',
       'password' => \Illuminate\Support\Facades\Hash::make('replace-with-a-strong-password'),
   ]);
   ```

7. Frontend production assets build karne ke liye:

   ```bash
   npm run build
   ```

8. `php artisan schedule:list` se scheduled tasks dekhein. Production cron setup neeche [Scheduled Jobs and Cron](#scheduled-jobs-and-cron) mein hai.

9. Useful pages: `/admin/dashboard`, `/regions`, `/warehouses`, `/devices`, `/readings`, `/alerts`, `/hierarchy`, `/fns/detections`, `/fns/detections02`, `/admin/reports`, `/admin/settings`.

---

## Production Deployment

Deployment hosting ke mutabik vary karega; typical Linux server release steps:

1. Current database ka backup lein aur release code deploy karein.
2. Production `.env` set karein: database, `APP_URL`, mail, filesystem and FNS push secret. `APP_DEBUG=false` rakhein.
3. Dependencies/assets install aur build karein:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   npm ci
   npm run build
   ```

4. Migrations aur public storage link apply karein:

   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```

5. `storage/` aur `bootstrap/cache/` ko PHP/web runtime user ke liye writable rakhein. Cron bhi deployment directory aur compatible PHP binary se run hona chahiye.
6. Application caches refresh karein:

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```

7. System crontab mein `schedule:run` wali single-minute entry add karein. `crontab -l` mein har job alag physical line par aur uske shuru mein paanch time fields hone chahiye. Cron commands mein plaintext DB password na rakhein.
8. Existing FNS rows preview/backfill karein:

   ```bash
   php artisan fns-detections:sync-locations --dry-run
   php artisan fns-detections:sync-locations
   ```

9. Login, dashboard, API ingestion, snapshots, email and `php artisan schedule:list` check karein. Application errors `storage/logs/laravel.log` mein inspect karein.

Code deploy karna scheduled command chalane ke liye kaafi nahi: server cron ko Laravel scheduler har minute invoke karna hota hai. Cron user aur PHP/web file ownership consistent rakhein, taaki cron root-owned files create karke web process ko block na kare.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Scheduler command listed nahi hai | Project root se `php artisan schedule:list`; command registration and route config check karein |
| FNS location `-` aa rahi hai | Stored `godown`/`compartment` fields dekhein; sync command ka `--dry-run` output check karein |
| FNS image load nahi hoti | `php artisan storage:link`, file existence, public disk URL and web-server permissions check karein |
| Scheduler log update nahi hota | `crontab -l`, cron daemon, PHP binary/project path and log permissions check karein |
| Alert email nahi aati | `.env` ke `MAIL_*`, email-routing rows, enabled CC recipients and `storage/logs/laravel.log` check karein |
| Dashboard status purana hai | `device_latest_status.recorded_at` dekhein; bulk import ke baad `php artisan devices:rebuild-latest-status` chalayein |
| Web page/API 500 deta hai | DB connectivity, migrations, `APP_KEY`, storage/cache permissions and Laravel log inspect karein |

---

## Notes / Assumptions (important)
- Dashboard “active warehouses” ko `device_latest_status` ki recent warehouse-associated rows se infer karta hai; online/offline device counts current projection values hain.
- Reports me device online/offline ka mapping “devices.status (active/inactive)” aur readings.status (online/offline) ke beech best-effort proxy hai (ReportController@summary me comment ke saath).

---

## Modules Quick Reference
- **Dashboard**: `AdminController@dashboard` + `admin/dashboard.blade.php`
- **Settings (Email)**: `AdminController@settings` + CC email methods + `EmailRoutingController`
- **CRUD Modules**: `regions`, `warehouses`, `devices`, `readings`, `alerts` resource controllers
- **Hierarchy**: `/hierarchy` route closure + hierarchy view
- **Reports**: `ReportController@index/data/summary/export` + `reports/index.blade.php`
