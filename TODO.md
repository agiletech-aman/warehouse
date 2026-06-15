# TODO

- [x] Add improved sidebar styles (active state, hover, scrollbar, separators) in `resources/views/layouts/app.blade.php`.
- [ ] Add active menu item highlighting using `request()->routeIs(...)` for each sidebar link.
- [ ] Verify collapsed/expanded toggle still works after CSS changes.

---

# Reports module

- [ ] Update `composer.json` with export/chart dependencies (`maatwebsite/excel`, `barryvdh/laravel-dompdf`).
- [ ] Create `app/Http/Controllers/ReportController.php` with `index`, `data`, `summary`, and `export` endpoints.
- [ ] Create `resources/views/reports/index.blade.php` (sticky filters, stats cards, unified DataTables grid, Chart.js charts, export buttons).
- [ ] Create export class `app/Exports/ReportExport.php`.
- [ ] Wire routes in `routes/web.php` for reports page, AJAX data/summary, and export endpoints.
- [ ] Run `composer update` and (if required) vendor publish for dompdf.
- [ ] Manual test `/admin/reports`: filtering, pagination, charts, and export formats.

