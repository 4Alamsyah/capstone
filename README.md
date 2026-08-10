# Skatra ERP — Dokumentasi Modul

Dokumentasi ini menjelaskan **alur proses**, **relasi database**, dan **daftar fungsi** untuk setiap modul aplikasi Skatra ERP. Setiap referensi kode ditulis dalam format `path/file.php:baris` agar mudah ditelusuri langsung di editor.

## Gambaran Umum Arsitektur

- **Stack**: Laravel 12 (backend) + Inertia.js v2 + Vue 3 (frontend), tanpa REST API terpisah — setiap controller me-render halaman Vue lewat `Inertia::render()`.
- **Routing**: seluruh route aplikasi (setelah login) didefinisikan di [routes/web.php](routes/web.php), dikelompokkan per modul dan dilindungi middleware `permission:<key>`. Route pengaturan akun ada di [routes/settings.php](routes/settings.php).
- **Otorisasi**: **tidak memakai** package `spatie/laravel-permission`. Role disimpan sebagai kolom string `users.role` (`staff|admin|gm|director`) dan permission disimpan sebagai kolom JSON `users.permissions` (hanya menyimpan *override* terhadap template default per role). Logika ada di [app/Models/User.php](app/Models/User.php) (`hasPermission()`, `resolvedPermissions()`, `permissionsTemplateForRole()`) dan ditegakkan oleh middleware [app/Http/Middleware/EnsureUserPermission.php](app/Http/Middleware/EnsureUserPermission.php) (alias `permission`, didaftarkan di `bootstrap/app.php`).
- **Konfigurasi aplikasi**: nilai-nilai seperti format penomoran dokumen (WO/PO/CO/Quotation/PV), mata uang default, tarif pajak, dan mapping akun GL disimpan sebagai baris key-value di tabel `app_settings`, diakses lewat helper statis `AppSetting::get()` / `AppSetting::set()` di [app/Models/AppSetting.php](app/Models/AppSetting.php) (di-cache permanen per key via `Cache::rememberForever`).
- **Dokumen cetak (PDF)**: memakai `barryvdh/laravel-dompdf`, dipakai untuk Invoice dan Delivery Order.

### Alur bisnis lintas modul (ringkas)

```
Quotation → (generate) → Customer Order → confirm() → Work Order (produksi) + Purchase Voucher (jika stok kurang)
                                                              │                         │
                                                              ▼                         ▼
                                                     Laporan Produksi WO      approve → convert → Purchase Order
                                                     (konsumsi stok BOM)                          → approve (auto-buat WO juga)
                                                                                                    → lapor Arrival (tambah stok)
Customer Order → updateStatus(Delivered) → Invoice (auto) → send() → posting GL (AR/Revenue)
                                                             → request/approve/reject payment
                                                             → recordPayment() → posting GL (Cash/AR) + AR Aging report
```

## Daftar Isi

1. [Dashboard](#1-dashboard)
2. [Master Data & Inventory](#2-master-data--inventory) — Parts, Stock, BOM, Tool Loan, Work Center
3. [Manufacturing / Work Order](#3-manufacturing--work-order)
4. [Sales](#4-sales) — Customer, Quotation, Customer Order, Invoice, Payment
5. [Purchase](#5-purchase) — Supplier, Purchase Voucher, Purchase Order, Arrival
6. [Accounting](#6-accounting) — COA, Fiscal Period, Journal, AR Aging, GL/Tax Setting, Audit Trail
7. [Settings & Access Control](#7-settings--access-control)

---

## 1. Dashboard

### Alur Proses

1. User membuka `GET /dashboard` (route `dashboard`, [routes/web.php:36](routes/web.php#L36)), middleware `permission:menu.dashboard` ([routes/web.php:34-37](routes/web.php#L34-L37)) — merender halaman Inertia `Dashboard` (kosong, data diambil via AJAX terpisah).
2. Halaman Vue [resources/js/pages/Dashboard.vue](resources/js/pages/Dashboard.vue) memanggil endpoint `GET /api/dashboard/analytics` ([routes/web.php:35](routes/web.php#L35)) → `DashboardController::analytics()` (`app/Http/Controllers/DashboardController.php:22`), yang mengembalikan `JsonResponse` gabungan 5 blok: `summary`, `workOrders`, `customerOrders`, `inventory`, `suppliers`.
3. Setiap blok dihitung oleh method privat: `getSummary()` (baris 36 — total supplier/part/work center/customer/BOM + low stock items), `getWorkOrdersAnalytics()` (baris 53 — distribusi status WO + 5 WO terbaru), `getCustomerOrdersAnalytics()` (baris 80 — distribusi status CO, tren bulanan 6 bulan, top 5 customer), `getInventoryAnalytics()` (baris 98 — stok material vs tool, low/overstock, tool loan aktif), `getSuppliersAnalytics()` (baris 128 — top 5 supplier by jumlah part).
4. Ambang batas *low stock* dan *overstock* dibaca dari `AppSetting` (`inventory_low_stock_threshold`, `inventory_overstock_threshold`) lewat `lowStockThreshold()` (baris 190) dan `overStockThreshold()` (baris 198) — **catatan**: dua key ini tidak punya halaman pengaturan khusus di modul Settings, kemungkinan diisi manual lewat `tinker`/seeder.

### Relasi Database

Modul ini tidak punya tabel sendiri — murni agregasi read-only dari tabel modul lain (`suppliers`, `parts`, `work_centers`, `customers`, `boms`, `stocks`, `work_orders`, `customer_orders`, `tool_loans`). Cara verifikasi: buka `/dashboard` di browser lalu lihat response JSON dari `/api/dashboard/analytics` di tab Network, atau jalankan langsung di `tinker`:

```php
app(App\Http\Controllers\DashboardController::class)->analytics()->getData(true);
```

### Daftar Fungsi

| File | Baris | Fungsi | Deskripsi |
|---|---|---|---|
| app/Http/Controllers/DashboardController.php | 22 | `analytics(): JsonResponse` | Endpoint utama yang menggabungkan seluruh data analitik dashboard. |
| app/Http/Controllers/DashboardController.php | 36 | `getSummary(): array` *(private)* | Ringkasan total supplier, part, work center, customer, BOM, dan item stok rendah. |
| app/Http/Controllers/DashboardController.php | 53 | `getWorkOrdersAnalytics(): array` *(private)* | Distribusi status Work Order dan 5 WO terbaru. |
| app/Http/Controllers/DashboardController.php | 80 | `getCustomerOrdersAnalytics(): array` *(private)* | Distribusi status Customer Order, tren bulanan, top customer. |
| app/Http/Controllers/DashboardController.php | 98 | `getInventoryAnalytics(): array` *(private)* | Statistik stok material/tool, low stock, overstock, tool loan aktif. |
| app/Http/Controllers/DashboardController.php | 128 | `getSuppliersAnalytics(): array` *(private)* | Total supplier dan top 5 supplier berdasarkan jumlah part yang dipasok. |
| app/Http/Controllers/DashboardController.php | 146 | `getMonthlyCustomerOrdersTrend(): array` *(private)* | Tren jumlah Customer Order per bulan, 6 bulan terakhir. |
| app/Http/Controllers/DashboardController.php | 174 | `getTopCustomers(): array` *(private)* | 5 customer dengan jumlah order terbanyak. |
| app/Http/Controllers/DashboardController.php | 190 | `lowStockThreshold(): int` *(private)* | Ambang batas stok rendah dari `AppSetting`. |
| app/Http/Controllers/DashboardController.php | 198 | `overStockThreshold(): int` *(private)* | Ambang batas overstock dari `AppSetting`. |

---

## 2. Master Data & Inventory

Mencakup **Parts**, **Stock**, **BOM (Bill of Materials)**, **Tool Loan**, dan **Work Center**. Sumber: [app/Http/Controllers/PartController.php](app/Http/Controllers/PartController.php), [BomController.php](app/Http/Controllers/BomController.php), [WorkCenterController.php](app/Http/Controllers/WorkCenterController.php), [ToolLoanController.php](app/Http/Controllers/ToolLoanController.php).

### Alur Proses

#### Parts

1. **List** — `GET /parts` → `PartController::index()` (`app/Http/Controllers/PartController.php:26`). Query `Part::with(['supplierPrices.supplier'])->withSum('stocks as total_stock','quantity')` (baris 30-32), filter pencarian part_number/name/description/supplier (33-43), paginasi 10, lalu tiap baris di-lengkapi stok per warehouse (67-69). Render `Inertia::render('parts/List', ...)` → [resources/js/pages/parts/List.vue](resources/js/pages/parts/List.vue).
2. **Form tambah** — `GET /parts/register` → `create()` (baris 104) → [resources/js/pages/parts/Register.vue](resources/js/pages/parts/Register.vue).
3. **Simpan** — `POST /parts` → `store()` (baris 115), validasi `StorePartRequest`. Dalam `DB::transaction` (119): buat `Part` (120-128), buat `PartSupplierPrice` per supplier unik (130-138), buat `Stock` per warehouse dengan qty > 0 (140-151).
4. **Update** — `PUT /parts/{part}` → `update()` (baris 160). Pola *full-resync*: hapus lalu buat ulang seluruh `supplierPrices` (175, 177-185) dan `stocks` (187, 189-198) — bukan diff/merge.
5. **Hapus** — `DELETE /parts/{part}` → `destroy()` (baris 207). Cascade otomatis ke `stocks`, `part_supplier_prices`, `stock_movements`; `bom_items.component_part_id` di-null-kan (nullOnDelete).
6. **Dashboard stok** — `GET /parts/stock` → `stock()` (baris 217): daftar stok, ringkasan agregat per warehouse/inventory_type, 50 pergerakan stok terakhir, tool loan aktif, daftar part tipe tool → [resources/js/pages/parts/Stock.vue](resources/js/pages/parts/Stock.vue).

#### Stock

Tidak ada `StockController` tersendiri — baris `Stock` hanya berubah sebagai efek samping alur lain: dibuat/direplace saat Part disimpan/diupdate (`PartController.php:146-150, 193-198`), dikurangi saat Tool Loan dipinjam (`ToolLoanController.php:54`), ditambah saat Tool Loan dikembalikan (`ToolLoanController.php:110-120`), ditambah saat barang PO datang (`PurchaseOrderController.php`, lihat modul Purchase), dan dikurangi saat laporan produksi Work Order mengonsumsi komponen (lihat modul Manufacturing).

#### BOM

1. **List** — `GET /bom` → `BomController::index()` (`app/Http/Controllers/BomController.php:22`) → [resources/js/pages/bom/Index.vue](resources/js/pages/bom/Index.vue).
2. **Form tambah** — `GET /bom/create` → `create()` (baris 73), bisa preselect part via query param → [resources/js/pages/bom/Create.vue](resources/js/pages/bom/Create.vue).
3. **Simpan** — `POST /bom` → `store()` (baris 87). Dalam transaksi (91): buat header `Bom` (92-97), lalu buat tiap `BomItem` (item bertipe `part` atau `operation`), `sort_order` default ke indeks loop (99-109).
4. **Detail** — `GET /bom/{bom}` → `show()` (baris 118), eager-load `part`, `items.componentPart`, `items.workCenter` (120) → [resources/js/pages/bom/Show.vue](resources/js/pages/bom/Show.vue).
5. **Update** — `PUT /bom/{bom}` → `update()` (baris 155): full-resync, hapus semua item lama (167) lalu buat ulang (169-179).
6. **Hapus** — `DELETE /bom/{bom}` → `destroy()` (baris 188), cascade ke `bom_items`.

#### Tool Loan

1. **Pinjam** — `POST /parts/stock/tool-loans` → `ToolLoanController::store()` (`app/Http/Controllers/ToolLoanController.php:20`). Validasi part harus `inventory_type = tool` (26). Dalam transaksi (35): kunci baris `Stock` (`lockForUpdate()`, 36-40), tolak jika stok tidak cukup (48-52), kurangi `Stock.quantity` (54), buat `ToolLoan` status `borrowed` (56-67), catat `StockMovement` (`movement_type='tool_borrow'`, qty negatif) (69-76).
2. **Kembalikan** — `PATCH /parts/stock/tool-loans/{toolLoan}` → `update()` (baris 85). Kunci baris `ToolLoan` (93), hitung sisa qty belum dikembalikan (101-108), `firstOrCreate` baris `Stock` bila belum ada (110-118), tambah stok (120), update `ToolLoan` (status jadi `returned` hanya jika qty kembali sepenuhnya) (122-132), catat `StockMovement` (`movement_type='tool_return'`, qty positif) (134-141).

#### Work Center

CRUD standar tanpa logika khusus: `index()` (`app/Http/Controllers/WorkCenterController.php:18`), `store()` (61), `update()` (71), `destroy()` (81 — referensi di `bom_items.work_center_id` di-null-kan via `nullOnDelete`, tidak diblokir).

### Relasi Database

| Tabel | Kolom FK | Referensi & Cascade | Migrasi |
|---|---|---|---|
| `part_supplier_prices` | `part_id`, `supplier_id` | `parts.id`, `suppliers.id` — keduanya `cascadeOnDelete()`; unique `(part_id, supplier_id)` | [2026_03_15_000003](database/migrations/2026_03_15_000003_create_part_supplier_prices_table.php) |
| `stocks` | `part_id`, `warehouse_id` | keduanya `cascadeOnDelete()`; unique `(part_id, warehouse_id)` | [2026_03_15_000005](database/migrations/2026_03_15_000005_create_stocks_table.php) |
| `boms` | `part_id` | `cascadeOnDelete()` | [2026_03_15_000007](database/migrations/2026_03_15_000007_create_boms_table.php) |
| `bom_items` | `bom_id`, `component_part_id`, `work_center_id` | `bom_id` cascade; dua lainnya nullable + `nullOnDelete()` | [2026_03_15_000008](database/migrations/2026_03_15_000008_create_bom_items_table.php) |
| `stock_movements` | `part_id`, `warehouse_id`, `work_order_id`, `work_order_report_id`, `tool_loan_id` | `part_id` cascade; sisanya nullable + `nullOnDelete()` | [2026_03_15_000013](database/migrations/2026_03_15_000013_create_stock_movements_table.php), [2026_04_12_000003](database/migrations/2026_04_12_000003_add_tool_loan_id_to_stock_movements_table.php) |
| `tool_loans` | `part_id`, `warehouse_id`, `created_by`, `returned_by` | part/warehouse cascade; created_by/returned_by nullable → `users.id` nullOnDelete | [2026_04_12_000002](database/migrations/2026_04_12_000002_create_tool_loans_table.php) |

**Relasi Eloquent:**

| Model | Method | File:Baris | Target |
|---|---|---|---|
| Part | `supplierPrices()` | app/Models/Part.php:37 | hasMany `PartSupplierPrice` |
| Part | `suppliers()` | app/Models/Part.php:45 | belongsToMany `Supplier` via `part_supplier_prices` |
| Part | `stocks()` | app/Models/Part.php:55 | hasMany `Stock` |
| Part | `stockMovements()` | app/Models/Part.php:63 | hasMany `StockMovement` |
| Part | `toolLoans()` | app/Models/Part.php:71 | hasMany `ToolLoan` |
| Stock | `part()` / `warehouse()` | app/Models/Stock.php:28 / 36 | belongsTo |
| Stock | `movements()` | app/Models/Stock.php:44 | hasMany `StockMovement`, kunci non-standar (`part_id` di kedua sisi + filter `warehouse_id` instance) |
| Bom | `part()` / `items()` | app/Models/Bom.php:31 / 36 | belongsTo `Part`; hasMany `BomItem` (orderBy sort_order) |
| BomItem | `bom()` / `componentPart()` / `workCenter()` | app/Models/BomItem.php:31 / 36 / 41 | belongsTo |
| ToolLoan | `part()` / `warehouse()` / `creator()` / `returner()` | app/Models/ToolLoan.php:51 / 59 / 67 / 75 | belongsTo (`creator`/`returner` → `User`) |
| WorkCenter | — | app/Models/WorkCenter.php | **tidak ada method relasi** — hanya diakses sebagai inverse dari `BomItem::workCenter()` |

**Cara verifikasi di aplikasi:**
- Eager-load nyata: `PartController.php:30-32`, `PartController.php:67-69`, `PartController.php:219-220`, `PartController.php:250-251`, `BomController.php:120`.
- Halaman yang menampilkan data gabungan: `GET /parts/stock` dan `GET /bom/{bom}`.
- Tinker: `Part::find(1)->stocks`, `Bom::find(1)->items`, `ToolLoan::find(1)->part`.

### Daftar Fungsi

**app/Http/Controllers/PartController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 26 | `index(Request $request): Response` | Daftar part dengan pencarian, total stok, harga supplier, stok per warehouse. |
| 104 | `create(): Response` | Form registrasi part baru. |
| 115 | `store(StorePartRequest $request): RedirectResponse` | Buat part + harga supplier + stok awal (transaksi). |
| 160 | `update(UpdatePartRequest $request, Part $part): RedirectResponse` | Update part, replace penuh harga supplier & stok. |
| 207 | `destroy(Request $request, Part $part): RedirectResponse` | Hapus part (cascade). |
| 217 | `stock(): Response` | Dashboard stok, ringkasan, riwayat pergerakan, tool loan aktif. |

**app/Http/Controllers/BomController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 22 | `index(Request $request): Response` | Daftar BOM dengan pencarian & jumlah item. |
| 73 | `create(Request $request): Response` | Form BOM baru. |
| 87 | `store(StoreBomRequest $request): RedirectResponse` | Buat header BOM + item (transaksi). |
| 118 | `show(Bom $bom): Response` | Detail BOM lengkap (item, component part, work center). |
| 155 | `update(UpdateBomRequest $request, Bom $bom): RedirectResponse` | Update header + replace penuh item. |
| 188 | `destroy(Request $request, Bom $bom): RedirectResponse` | Hapus BOM (cascade item). |

**app/Http/Controllers/WorkCenterController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 18 | `index(Request $request): Response` | Daftar work center dengan pencarian. |
| 61 | `store(StoreWorkCenterRequest $request): RedirectResponse` | Buat work center baru. |
| 71 | `update(UpdateWorkCenterRequest $request, WorkCenter $workCenter): RedirectResponse` | Update work center. |
| 81 | `destroy(Request $request, WorkCenter $workCenter): RedirectResponse` | Hapus work center. |

**app/Http/Controllers/ToolLoanController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 20 | `store(Request $request): RedirectResponse` | Catat peminjaman tool: kunci & kurangi stok, buat `ToolLoan`, catat `StockMovement`. |
| 85 | `update(Request $request, ToolLoan $toolLoan): RedirectResponse` | Catat pengembalian tool: tambah stok, update status `ToolLoan`, catat `StockMovement`. |

**Models** (relasi — lihat tabel relasi di atas untuk baris lengkap)

| Model | Baris method tambahan | Deskripsi |
|---|---|---|
| app/Models/Part.php | 15, 17 | Konstanta `INVENTORY_TYPE_MATERIAL='material'`, `INVENTORY_TYPE_TOOL='tool'`. |
| app/Models/ToolLoan.php | 13, 15 | Konstanta `STATUS_BORROWED='borrowed'`, `STATUS_RETURNED='returned'`. |
| app/Models/ToolLoan.php | 83 | `getRemainingQuantityAttribute(): int` — accessor `remaining_quantity` (borrowed - returned, min 0); tidak dipakai controller (dihitung ulang manual di `PartController.php:280`). |

---

## 3. Manufacturing / Work Order

Sumber: [app/Http/Controllers/WorkOrderController.php](app/Http/Controllers/WorkOrderController.php), [app/Services/WoNumberService.php](app/Services/WoNumberService.php), model `WorkOrder`, `WorkOrderReport`, `WorkOrderLog`. Modul ini mengonsumsi data BOM dan menyentuh Stock/StockMovement.

### Alur Proses

1. **Form buat WO** — `GET /work-orders/create` → `create()` (`app/Http/Controllers/WorkOrderController.php:241`): daftar BOM aktif + preview nomor via `WorkOrder::generateNumber()` (`app/Models/WorkOrder.php:58-68`) → [resources/js/pages/work-orders/Create.vue](resources/js/pages/work-orders/Create.vue).
2. **Generate nomor WO** — `WorkOrder::generateNumber()` membaca format JSON dari `AppSetting::get('wo_format')`, fallback `defaultFormat()` (70-82); dirakit oleh `WoNumberService::generate()`/`stem()` (`app/Services/WoNumberService.php:7-49`). **Catatan**: tidak ada locking, sehingga nomor final saat `store()` bisa berbeda dari preview jika ada WO lain dibuat di antaranya (race condition).
3. **Simpan WO** — `POST /work-orders` → `store(StoreWorkOrderRequest $request)` (baris 260): buat `WorkOrder` status `draft`, catat log via helper privat `logWorkOrder()` (baris 614) dengan `log_type='created'`.
4. **List / detail** — `index()` (baris 113), `show()` (baris 482) — eager-load `bom.part`, `bom.items.componentPart`, `bom.items.workCenter`, `purchaseOrder`, `reports`.
5. **Update manual** — `update(UpdateWorkOrderRequest $request, WorkOrder $workOrder)` (baris 526): bandingkan `getChanges()`, catat `WorkOrderLog` tipe `updated` jika berubah.
6. **Form laporan produksi** — `GET /work-orders/{workOrder}/report` → `report()` (baris 289): load `bom.items.componentPart.stocks.warehouse`; hitung `recommendedQuantity = round(qty_wo * qty_bom_item)` per komponen → [resources/js/pages/work-orders/ReportForm.vue](resources/js/pages/work-orders/ReportForm.vue).
7. **Submit laporan (`submitReport`)** — `POST /work-orders/{workOrder}/report` → `submitReport(ReportWorkOrderRequest $request, WorkOrder $workOrder)` (baris 356), dalam `DB::transaction`:
   - Buat 1 baris `WorkOrderReport` (363-371).
   - Untuk tiap `consumptions[]`: validasi `BomItem` bertipe `part`, kunci `Stock` (`lockForUpdate()`), validasi stok cukup & part bukan tipe `tool`, `$stock->decrement('quantity', $quantity)`, buat `StockMovement` (`movement_type='consume'`, qty negatif, terhubung ke `work_order_id` & `work_order_report_id`) (373-449), catat `WorkOrderLog` tipe `stock_consumed`.
   - Update `WorkOrder.status` sesuai `new_status`, catat `WorkOrderLog` tipe `reported` (451-473).
8. **Log audit WO** — `logs()` (baris 554): daftar semua `WorkOrderLog` (created/updated/stock_consumed/reported) dengan pencarian → [resources/js/pages/work-orders/Log.vue](resources/js/pages/work-orders/Log.vue).
9. **Lead-time timeline** — `leadTimeTimeline()` (baris 25): hitung durasi proses tiap WO dari `created_at` sampai log terakhir/`updated_at`, dalam jam → [resources/js/pages/work-orders/LeadTime.vue](resources/js/pages/work-orders/LeadTime.vue).
10. **Hapus** — `destroy()` (baris 607): hard delete, `work_order_reports` & `work_order_logs` ikut terhapus (cascade).

**Integrasi lintas modul**: WO dibuat dari satu `Bom` (`work_orders.bom_id`); `work_orders.purchase_order_id` opsional menandai WO yang otomatis dibuat saat approval PO (lihat modul Purchase); satu-satunya titik WO menyentuh stok fisik adalah `submitReport()`.

### Relasi Database

| Tabel | Kolom FK | Cascade | Migrasi |
|---|---|---|---|
| `work_orders` | `bom_id` → `boms.id` (restrict); `purchase_order_id` → `purchase_orders.id` (nullable, nullOnDelete) | [2026_03_15_000010](database/migrations/2026_03_15_000010_create_work_orders_table.php), [2026_04_12_000008](database/migrations/2026_04_12_000008_add_purchase_order_id_to_work_orders_table.php) |
| `work_order_reports` | `work_order_id` (cascade); `reported_by` → `users.id` (nullOnDelete) | [2026_03_15_000011](database/migrations/2026_03_15_000011_create_work_order_reports_table.php) |
| `work_order_logs` | `work_order_id` (cascade); `user_id` → `users.id` (nullOnDelete) | [2026_03_15_000012](database/migrations/2026_03_15_000012_create_work_order_logs_table.php) |

**Relasi Eloquent:**

| Model | Method | File:Baris | Target |
|---|---|---|---|
| WorkOrder | `bom()` | app/Models/WorkOrder.php:33 | belongsTo `Bom` |
| WorkOrder | `purchaseOrder()` | app/Models/WorkOrder.php:38 | belongsTo `PurchaseOrder` |
| WorkOrder | `reports()` | app/Models/WorkOrder.php:43 | hasMany `WorkOrderReport` (latest) |
| WorkOrder | `logs()` | app/Models/WorkOrder.php:48 | hasMany `WorkOrderLog` (latest) |
| WorkOrder | `stockMovements()` | app/Models/WorkOrder.php:53 | hasMany `StockMovement` (latest) |
| WorkOrderReport | `workOrder()` / `stockMovements()` / `reporter()` | app/Models/WorkOrderReport.php:26 / 31 / 36 | belongsTo / hasMany / belongsTo `User` |
| WorkOrderLog | `workOrder()` / `user()` | app/Models/WorkOrderLog.php:23 / 28 | belongsTo |

**Cara verifikasi di aplikasi:**
- Rantai eager-load terpanjang: `$workOrder->load(['bom.part', 'bom.items.componentPart.stocks.warehouse', 'bom.items.workCenter', 'reports.reporter'])` di `WorkOrderController.php:291-296`.
- Halaman: `work-orders/ReportForm.vue` (komponen BOM + stok + riwayat laporan), `work-orders/LeadTime.vue` (timeline log).
- Tinker:
```php
$wo = App\Models\WorkOrder::with(['bom.part', 'bom.items.componentPart', 'logs.user', 'reports.reporter'])->latest()->first();
App\Models\StockMovement::where('work_order_id', $wo->id)->with('part', 'warehouse')->get();
```

### Daftar Fungsi

**app/Http/Controllers/WorkOrderController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 25 | `leadTimeTimeline(Request $request): Response` | Timeline lead-time WO + riwayat log. |
| 113 | `index(Request $request): Response` | Daftar work order dengan pencarian/filter status. |
| 180 | `reportIndex(Request $request): Response` | Daftar WO yang bisa dilaporkan. |
| 241 | `create(): Response` | Form WO baru + preview nomor. |
| 260 | `store(StoreWorkOrderRequest $request): RedirectResponse` | Buat WO status draft + log `created`. |
| 289 | `report(WorkOrder $workOrder): Response` | Form pelaporan WO (kebutuhan komponen, rekomendasi qty, stok). |
| 356 | `submitReport(ReportWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse` | Simpan laporan produksi, konsumsi stok, ubah status, log audit. |
| 482 | `show(WorkOrder $workOrder): Response` | Detail WO. |
| 526 | `update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse` | Update status/qty/jadwal + log `updated`. |
| 554 | `logs(Request $request): Response` | Daftar log audit WO. |
| 607 | `destroy(Request $request, WorkOrder $workOrder): RedirectResponse` | Hapus WO (cascade reports & logs). |
| 614 | `logWorkOrder(...): void` *(private)* | Helper buat satu baris `WorkOrderLog`. |

**app/Services/WoNumberService.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 7 | `generate(array $format, int $sequenceNumber): string` | Rakit nomor WO lengkap. |
| 27 | `preview(array $format): string` | Contoh nomor (sequence=1) untuk UI setting. |
| 32 | `stem(array $format): string` | Bagian statis nomor untuk pola pencarian. |
| 51 | `formatComponent(...): ?string` *(private static)* | Format satu komponen (prefix/year/month/sequential). |
| 62 | `formatYear(string $format): string` *(private static)* | Format komponen tahun. |
| 71 | `formatMonth(string $format): string` *(private static)* | Format komponen bulan. |
| 80 | `formatSequential(string $format, int $number): string` *(private static)* | Format nomor urut dengan padding nol. |

**app/Models/WorkOrder.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 58 | `generateNumber(): string` *(static)* | Generate nomor WO baru. |
| 70 | `defaultFormat(): array` *(private static)* | Format default `WO-YYYY-MM-00001`. |
| 89 | `statusLabels(): array` *(static)* | Peta status → label UI. |

---

## 4. Sales

Mencakup **Customer**, **Quotation**, **Customer Order**, **Invoice**, **Payment**. Sumber: [CustomerController.php](app/Http/Controllers/CustomerController.php), [QuotationController.php](app/Http/Controllers/QuotationController.php), [CustomerOrderController.php](app/Http/Controllers/CustomerOrderController.php), [InvoiceController.php](app/Http/Controllers/InvoiceController.php).

> **Catatan desain penting**: tidak ada model/tabel `Quotation` terpisah. `QuotationController` memakai model `CustomerOrder` yang sama, dengan `status = CustomerOrder::STATUS_QUOTATION` (`app/Models/CustomerOrder.php:12`). `generateCustomerOrder()` mengubah baris quotation itu langsung menjadi customer order (ganti `co_number` + `status → STATUS_REGISTERED`) — bukan membuat baris baru.

### Alur Proses

#### Customer
- `index()` (`app/Http/Controllers/CustomerController.php:17`) — pencarian multi-kolom, paginasi 10.
- `store()` (81), `update()` (92), `destroy()` (103) — CRUD standar, `currency_code` di-uppercase & default dari `AppSetting`.

#### Quotation → konversi ke Customer Order
1. **List** — `index()` (`app/Http/Controllers/QuotationController.php:20`): `CustomerOrder::where('status', STATUS_QUOTATION)`.
2. **Simpan** — `store()` (125): buat `CustomerOrder` (`status=QUOTATION`, `co_number` dari `generateQuotationNumber()`) + `CustomerOrderItem` per baris, hitung subtotal.
3. **Edit/Update/Hapus** — `edit()` (97), `update()` (186), `destroy()` (249) — semua di-guard: `abort(404)`/redirect error jika `status !== STATUS_QUOTATION`.
4. **Generate Customer Order (langkah kunci)** — `generateCustomerOrder(CustomerOrder $quotation)` (260): dalam transaksi, nomor baru dari `CustomerOrder::generateNumber()`, `update()` baris yang sama — `co_number` diganti, `status → STATUS_REGISTERED`, `notes` ditambah "Generated from quotation {nomor lama}".

#### Customer Order — lifecycle status
Status: `QUOTATION(0) → REGISTERED(1) → CONFIRMED(2) → PICKING(3) → DELIVERED(4)`, plus `HISTORICAL(9)` (`app/Models/CustomerOrder.php:12-22`).

1. **Simpan CO** — `store()` (`app/Http/Controllers/CustomerOrderController.php:261`): `status=REGISTERED`; per item dihitung stok tersisa & flag `requires_mo`; header `needs_mo_suggestion` di-set bila ada item kurang stok.
2. **Confirm (Registered → Confirmed)** — `confirm()` (416): cek semua part punya BOM aktif (jika tidak, redirect ke `bom.create`). Dalam transaksi: `status → CONFIRMED`; **auto-create `WorkOrder`** per grup part yang punya BOM aktif (457-506); **auto-create `PurchaseVoucher` + item** untuk item yang stoknya kurang (508-540).
3. **Update status (Confirmed → Picking → Delivered)** — `updateStatus()` (551): tidak bisa mundur/lompat. Saat status baru `= DELIVERED` dan belum ada Invoice, **auto-create `Invoice`** (`status=DRAFT`) + `InvoiceItem` dari item CO, `tax_amount` dihitung dari `AppSetting::get('tax_rate')` (574-628).
4. **Undo report** — `undoReport()` (639): hapus Invoice+InvoiceItem terkait, reset `status → REGISTERED`.
5. **Dokumen Delivery Order (PDF)** — `deliveryOrder()` (668): hanya untuk `status >= DELIVERED`; nomor `DO-{YYYYMM}-{id 5 digit}`; render Blade → PDF via dompdf.

#### Invoice / Payment
1. **List** — `index()` (`app/Http/Controllers/InvoiceController.php:33`): `withSum('payments as amount_paid','amount')` untuk hitung `balance_due`.
2. **Simpan** — `store()` (245): `status=DRAFT`, `payment_approval_status=NOT_REQUESTED`; validasi CO harus milik customer yang sama.
3. **Send invoice** — `send()` (375): guard `status=DRAFT`; posting jurnal AR billing via `postInvoiceBillingToGl()` (600) — **DR Accounts Receivable, CR Sales Revenue** (+ CR Sales Tax Payable jika ada pajak), butuh `FiscalPeriod` terbuka + mapping GL account; `status → SENT`; log via `AccountingAuditLogger`.
4. **Request payment** — `requestPayment()` (399): guard status `SENT`/`PARTIALLY_PAID`; `payment_approval_status → PENDING`.
5. **Approve/Reject payment** *(permission `approve.invoice_payment`)* — `approvePayment()` (435) / `rejectPayment()` (547): hanya user manajemen (`canApproveInvoicePayment()`); approve **tidak** menyentuh GL, hanya mengizinkan langkah record payment berikutnya.
6. **Record payment** *(permission sama)* — `recordPayment()` (490): guard `payment_approval_status=APPROVED`; buat `Payment` (nomor dari `Payment::generateNumber()`); posting jurnal settlement via `postPaymentToGl()` (665) — **DR Cash/Bank, CR Accounts Receivable**; `status` invoice jadi `PAID` (lunas) atau `PARTIALLY_PAID`.
7. **Dokumen invoice (PDF)** — `document()` (573).

### Relasi Database

| Tabel | Kolom FK | Referensi | On Delete |
|---|---|---|---|
| `customer_orders` | `customer_id` | `customers.id` | restrict |
| `customer_order_items` | `customer_order_id`, `part_id` | `customer_orders.id`, `parts.id` | cascade, restrict |
| `invoices` | `customer_id`, `customer_order_id` (nullable) | `customers.id`, `customer_orders.id` | restrict, set null |
| `invoices` | `payment_requested_by/approved_by/rejected_by` (nullable) | `users.id` | set null |
| `invoice_items` | `invoice_id`, `part_id` (nullable) | `invoices.id`, `parts.id` | cascade, set null |
| `payments` | `payable_type`+`payable_id` (polymorphic) | model apapun (di sini: `Invoice`) | — |
| `payments` | `journal_entry_id` (nullable), `recorded_by` (nullable) | `journal_entries.id`, `users.id` | set null |

**Relasi Eloquent:**

| Model | Method | File:Baris | Target |
|---|---|---|---|
| Customer | `orders()` | app/Models/Customer.php:26 | hasMany `CustomerOrder` |
| CustomerOrder | `customer()` / `items()` | app/Models/CustomerOrder.php:54 / 59 | belongsTo / hasMany `CustomerOrderItem` |
| CustomerOrderItem | `order()` / `part()` | app/Models/CustomerOrderItem.php:35 / 40 | belongsTo |
| Invoice | `customer()` / `customerOrder()` / `items()` / `payments()` | app/Models/Invoice.php:74 / 79 / 84 / 89 | belongsTo / belongsTo / hasMany / **morphMany** `Payment` |
| InvoiceItem | `invoice()` / `part()` | app/Models/InvoiceItem.php:31 / 36 | belongsTo |
| Payment | `payable()` / `journalEntry()` / `recordedBy()` | app/Models/Payment.php:43 / 48 / 53 | morphTo / belongsTo `JournalEntry` / belongsTo `User` |

**Cara verifikasi di aplikasi:**
- `InvoiceController.php:39-40` — `->with(['customer:id,name', 'customerOrder:id,co_number', 'items.part:id,part_number,name'])->withSum('payments as amount_paid', 'amount')`.
- Halaman: `resources/js/pages/sales/invoices/Index.vue` (invoice + customer + CO + items + amount_paid/balance_due).
- Tinker:
```php
$invoice = App\Models\Invoice::with(['customer', 'customerOrder', 'items.part', 'payments'])->first();
$invoice->payments->sum('amount');
```

### Daftar Fungsi

**app/Http/Controllers/CustomerController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 17 | `index(Request $request): Response` | Daftar customer dengan pencarian multi-kolom. |
| 81 | `store(StoreCustomerRequest $request): RedirectResponse` | Buat customer baru. |
| 92 | `update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse` | Update customer. |
| 103 | `destroy(Customer $customer): RedirectResponse` | Hapus customer. |

**app/Http/Controllers/QuotationController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 20 | `index(Request $request): Response` | Daftar CustomerOrder berstatus quotation. |
| 90 | `create(): Response` | Form quotation baru. |
| 97 | `edit(CustomerOrder $quotation): Response` | Form edit quotation (404 jika bukan quotation). |
| 125 | `store(StoreCustomerOrderRequest $request): RedirectResponse` | Buat quotation + item. |
| 186 | `update(StoreCustomerOrderRequest $request, CustomerOrder $quotation): RedirectResponse` | Update quotation + replace item. |
| 249 | `destroy(CustomerOrder $quotation): RedirectResponse` | Hapus quotation. |
| 260 | `generateCustomerOrder(CustomerOrder $quotation): RedirectResponse` | Konversi quotation → customer order. |
| 292 | `buildFormPayload(array $extra = []): array` *(private)* | Payload umum form (currency, payment terms, customers, parts). |

**app/Http/Controllers/CustomerOrderController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 30 | `index(Request $request): Response` | Daftar CO (bukan quotation) + info invoice + ringkasan planning. |
| 148 | `create(): Response` | Form CO baru. |
| 191 | `edit(CustomerOrder $customerOrder): Response\|RedirectResponse` | Form edit (hanya saat REGISTERED). |
| 261 | `store(StoreCustomerOrderRequest $request): RedirectResponse` | Buat CO status REGISTERED + hitung kebutuhan MO. |
| 337 | `update(UpdateCustomerOrderRequest $request, CustomerOrder $customerOrder): RedirectResponse` | Update CO (hanya saat REGISTERED). |
| 416 | `confirm(CustomerOrder $customerOrder): RedirectResponse` | Konfirmasi CO → auto-buat WO & PV. |
| 551 | `updateStatus(UpdateCustomerOrderStatusRequest $request, CustomerOrder $customerOrder): RedirectResponse` | Majukan status CO; auto-buat Invoice saat Delivered. |
| 639 | `undoReport(CustomerOrder $customerOrder): RedirectResponse` | Kembalikan status ke REGISTERED + hapus invoice terkait. |
| 668 | `deliveryOrder(CustomerOrder $customerOrder): Response\|RedirectResponse` | Generate PDF Delivery Order. |

**app/Http/Controllers/InvoiceController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 33 | `index(Request $request): Response` | Daftar invoice + info pembayaran. |
| 117 | `create(): Response` | Form invoice manual. |
| 170 | `edit(Invoice $invoice): Response\|RedirectResponse` | Form edit (hanya saat Draft). |
| 245 | `store(StoreInvoiceRequest $request): RedirectResponse` | Buat invoice Draft + item + pajak. |
| 308 | `update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse` | Update invoice (hanya Draft). |
| 375 | `send(Invoice $invoice): RedirectResponse` | Draft → Sent + posting jurnal AR/Revenue. |
| 399 | `requestPayment(Request $request, Invoice $invoice): RedirectResponse` | Ajukan approval pembayaran. |
| 435 | `approvePayment(Request $request, Invoice $invoice): RedirectResponse` | Setujui pembayaran (GM/Director). |
| 464 | `newPayment(Invoice $invoice): Response\|RedirectResponse` | Form Record Payment. |
| 490 | `recordPayment(RecordInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse` | Catat pembayaran + posting jurnal settlement. |
| 547 | `rejectPayment(Request $request, Invoice $invoice): RedirectResponse` | Tolak pembayaran (GM/Director). |
| 573 | `document(Invoice $invoice): Response` | Generate PDF invoice. |
| 591 | `taxRate(): float` *(private)* | Ambil tarif pajak dari AppSetting. |
| 600 | `postInvoiceBillingToGl(Invoice $invoice): void` *(private)* | Posting jurnal AR billing. |
| 665 | `postPaymentToGl(Payment $payment, Invoice $invoice): void` *(private)* | Posting jurnal settlement pembayaran. |
| 716 | `isEditable(Invoice $invoice): bool` *(private)* | Cek invoice masih Draft. |
| 724 | `ensureManagementApprover(Request $request): void` *(private)* | Guard hanya GM/Director + permission `approve.invoice_payment`. |

**Models**

| File | Baris | Fungsi | Deskripsi |
|---|---|---|---|
| app/Models/CustomerOrder.php | 64 | `generateNumber(): string` *(static)* | Nomor CO berurutan dari format `co_format`. |
| app/Models/CustomerOrder.php | 76 | `generateQuotationNumber(): string` *(static)* | Nomor quotation berurutan dari format `quotation_format`. |
| app/Models/Invoice.php | 94 | `generateNumber(): string` *(static)* | Nomor invoice berurutan. |
| app/Models/Payment.php | 71 | `generateNumber(): string` *(static)* | Nomor pembayaran berurutan. |

---

## 5. Purchase

Mencakup **Supplier**, **Purchase Voucher (PV)**, **Purchase Order (PO)**, **Arrival/Goods Receipt**. Sumber: [SupplierController.php](app/Http/Controllers/SupplierController.php), [PurchaseOrderController.php](app/Http/Controllers/PurchaseOrderController.php), [PurchaseVoucherController.php](app/Http/Controllers/PurchaseVoucherController.php).

### Alur Proses

#### Supplier
CRUD standar: `index()` (`app/Http/Controllers/SupplierController.php:18`), `store()` (64), `update()` (74), `destroy()` (84 — hard delete, tanpa cek relasi PO/PV).

#### Purchase Voucher — rekomendasi stok → generateFromStock
1. **Rekomendasi stok** — `stockRecommendations()` (`app/Http/Controllers/PurchaseVoucherController.php:254`): part dengan `safety_stock > 0` dan `total_stock < safety_stock` (kekurangan stok), hitung `deficit` → [resources/js/pages/purchase/voucher/StockRecommendation.vue](resources/js/pages/purchase/voucher/StockRecommendation.vue).
2. **Generate dari rekomendasi** — `generateFromStock()` (282): buat `PurchaseVoucher` (`status=DRAFT`, `source=STOCK_RECOMMENDATION`) + `PurchaseVoucherItem` (snapshot `stock_on_hand`).
3. **Manual** — `create()` (70) / `store()` (82): sama, `source` bebas.
4. **Submit** — `submit()` (128): `DRAFT → SUBMITTED`.
5. **Approve/Reject** *(permission `approve.purchase_voucher`)* — `approve()` (143) / `reject()` (167): via `ensureApprover()` (340); hanya PV `SUBMITTED`.
6. **Convert ke PO** — `convertToPo()` (189): hanya PV `APPROVED`. Buat `PurchaseOrder` baru (`status=PENDING_APPROVAL`); tiap line PV yang dipilih (dengan `unit_price` input user) jadi `PurchaseOrderItem` dengan `purchase_voucher_item_id` untuk lacak asal. Jika **semua** item PV sudah punya PO terkait, `PurchaseVoucher.status → CONVERTED` (bisa dikonversi bertahap/multi-PO).
7. **Hapus** — `destroy()` (316): hanya status Draft/Rejected/Cancelled dan belum terhubung PO item.

#### Purchase Order — pembuatan manual & approval manajemen
1. **Simpan** — `store()` (`app/Http/Controllers/PurchaseOrderController.php:142`): `status=PENDING_APPROVAL`, buat `PurchaseOrderItem` per line, `received_quantity=0`.
2. **Approve** *(permission `approve.purchase_order`)* — `approve()` (186): via `ensureManagementApprover()` (509); `status → APPROVED`; lalu **otomatis memanggil `generateWorkOrdersFromPurchaseOrder()`** (542) — membuat Work Order untuk tiap part PO yang punya BOM aktif dan belum punya WO terkait (efek samping auto-MO generation).
3. **Reject** — `reject()` (220): sama syarat approver, wajib `approval_notes`.
4. **Hapus** — `destroy()` (246): via `ensurePurchaseOrderDeletable()` (518) — tidak boleh jika sudah ada arrival report.

#### Arrival / Goods Receipt
1. **Daftar PO siap lapor** — `reportIndex()` (255): PO berstatus Approved/Partial.
2. **Form lapor** — `reportForm()` (311): item PO + `remaining_quantity` (qty - received_quantity) + daftar warehouse.
3. **Submit arrival** — `submitArrival()` (348): guard PO `APPROVED`/`PARTIAL`. Dalam transaksi: buat header `PurchaseArrival`; per baris buat `PurchaseArrivalItem`; **`$poItem->increment('received_quantity', ...)`** (413); **`Stock::firstOrCreate(...)` lalu `increment('quantity', ...)`** (415-425) — menambah stok gudang; **catat `StockMovement`** (`movement_type='purchase_arrival'`, qty positif) (427-435). Setelah loop, PO `status → COMPLETED` jika semua item sudah diterima penuh, atau `PARTIAL` jika belum.
4. **Riwayat** — `logs()` (449): semua `PurchaseArrival` + item + part + warehouse + pelapor.

### Relasi Database

| Tabel | Kolom FK | Referensi | On Delete |
|---|---|---|---|
| `purchase_orders` | `supplier_id` | `suppliers.id` | cascade |
| `purchase_orders` | `approved_by`, `rejected_by` (nullable) | `users.id` | nullOnDelete |
| `purchase_order_items` | `purchase_order_id`, `part_id` | `purchase_orders.id`, `parts.id` | cascade |
| `purchase_order_items` | `purchase_voucher_item_id` (nullable) | `purchase_voucher_items.id` | nullOnDelete |
| `purchase_arrivals` | `purchase_order_id` | `purchase_orders.id` | cascade |
| `purchase_arrivals` | `reported_by` (nullable) | `users.id` | nullOnDelete |
| `purchase_arrival_items` | `purchase_arrival_id`, `purchase_order_item_id` | keduanya | cascade |
| `purchase_arrival_items` | `part_id`, `warehouse_id` (nullable) | `parts.id`, `warehouses.id` | nullOnDelete |
| `purchase_vouchers` | `customer_order_id` (nullable) | `customer_orders.id` | nullOnDelete |
| `purchase_vouchers` | `created_by`, `submitted_by`, `approved_by`, `rejected_by` (nullable) | `users.id` | nullOnDelete |
| `purchase_voucher_items` | `purchase_voucher_id`, `part_id` | keduanya | cascade |

**Relasi Eloquent:**

| Model | Method | File:Baris | Target |
|---|---|---|---|
| Supplier | `partPrices()` / `parts()` | app/Models/Supplier.php:29 / 37 | hasMany `PartSupplierPrice` / belongsToMany `Part` |
| PurchaseOrder | `supplier()` / `items()` / `arrivals()` / `workOrders()` | app/Models/PurchaseOrder.php:51 / 56 / 61 / 66 | belongsTo / hasMany / hasMany / hasMany `WorkOrder` (via `purchase_order_id`) |
| PurchaseOrderItem | `purchaseOrder()` / `part()` / `purchaseVoucherItem()` | app/Models/PurchaseOrderItem.php:32 / 37 / 42 | belongsTo |
| PurchaseArrival | `purchaseOrder()` / `reporter()` / `items()` | app/Models/PurchaseArrival.php:25 / 30 / 35 | belongsTo / belongsTo `User` / hasMany |
| PurchaseArrivalItem | `arrival()` / `purchaseOrderItem()` / `part()` / `warehouse()` | app/Models/PurchaseArrivalItem.php:26 / 31 / 36 / 41 | belongsTo |
| PurchaseVoucher | `items()` / `customerOrder()` / `creator()` | app/Models/PurchaseVoucher.php:39 / 44 / 49 | hasMany / belongsTo / belongsTo `User` |
| PurchaseVoucherItem | `purchaseVoucher()` / `part()` / `purchaseOrderItems()` | app/Models/PurchaseVoucherItem.php:21 / 26 / 31 | belongsTo / belongsTo / hasMany (PO items hasil convert) |

**Cara verifikasi di aplikasi:**
- `PurchaseOrder::query()->with(['supplier', 'items.part', 'workOrders'])` — `PurchaseOrderController.php:38`.
- `PurchaseArrival::query()->with(['purchaseOrder.supplier', 'reporter', 'items.part', 'items.warehouse'])` — `PurchaseOrderController.php:454`.
- Tinker:
```php
$po = App\Models\PurchaseOrder::with(['supplier','items.part','arrivals.items','workOrders'])->first();
$poi = App\Models\PurchaseOrderItem::with('purchaseVoucherItem.purchaseVoucher')->whereNotNull('purchase_voucher_item_id')->first();
$poi->purchaseVoucherItem->purchaseVoucher->pv_number;
```

### Daftar Fungsi

**app/Http/Controllers/SupplierController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 18 | `index(Request $request): Response` | Daftar supplier + pencarian. |
| 64 | `store(StoreSupplierRequest $request): RedirectResponse` | Buat supplier baru. |
| 74 | `update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse` | Update supplier. |
| 84 | `destroy(Supplier $supplier): RedirectResponse` | Hapus supplier. |

**app/Http/Controllers/PurchaseOrderController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 32 | `index(Request $request): Response` | Daftar PO + filter + flag izin approval. |
| 105 | `create(): Response` | Form PO baru. |
| 142 | `store(StorePurchaseOrderRequest $request): RedirectResponse` | Buat PO Pending Approval + item. |
| 186 | `approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse` | Approve PO + auto-buat Work Order. |
| 220 | `reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse` | Reject PO. |
| 246 | `destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse` | Hapus PO jika belum ada arrival. |
| 255 | `reportIndex(Request $request): Response` | Daftar PO siap dilaporkan kedatangannya. |
| 311 | `reportForm(PurchaseOrder $purchaseOrder): Response` | Form pelaporan arrival. |
| 348 | `submitArrival(StorePurchaseArrivalRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse` | Proses laporan kedatangan: arrival + tambah stok + StockMovement + update status PO. |
| 449 | `logs(Request $request): Response` | Riwayat laporan arrival. |
| 509 | `ensureManagementApprover(Request $request): void` *(private)* | Guard GM/Director + `approve.purchase_order`. |
| 518 | `ensurePurchaseOrderDeletable(PurchaseOrder $purchaseOrder): void` *(private)* | Guard PO boleh dihapus. |
| 542 | `generateWorkOrdersFromPurchaseOrder(PurchaseOrder $purchaseOrder, ?int $userId = null): array` *(private)* | Auto-buat WO dari PO + BOM aktif. |

**app/Http/Controllers/PurchaseVoucherController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 26 | `index(Request $request): Response` | Daftar PV + filter. |
| 70 | `create(): Response` | Form PV manual. |
| 82 | `store(StorePurchaseVoucherRequest $request): RedirectResponse` | Buat PV Draft + item. |
| 114 | `show(PurchaseVoucher $purchaseVoucher): Response` | Detail PV. |
| 128 | `submit(PurchaseVoucher $purchaseVoucher): RedirectResponse` | Draft → Submitted. |
| 143 | `approve(Request $request, PurchaseVoucher $purchaseVoucher): RedirectResponse` | Approve PV. |
| 167 | `reject(Request $request, PurchaseVoucher $purchaseVoucher): RedirectResponse` | Reject PV. |
| 189 | `convertToPo(ConvertToPurchaseOrderRequest $request, PurchaseVoucher $purchaseVoucher): RedirectResponse` | Konversi PV Approved → PO baru. |
| 254 | `stockRecommendations(): Response` | Daftar part di bawah safety stock. |
| 282 | `generateFromStock(StorePurchaseVoucherRequest $request): RedirectResponse` | Buat PV otomatis dari rekomendasi stok. |
| 316 | `destroy(PurchaseVoucher $purchaseVoucher): RedirectResponse` | Hapus PV. |
| 340 | `ensureApprover(Request $request): void` *(private)* | Guard `approve.purchase_voucher`. |

**Models**

| File | Baris | Fungsi | Deskripsi |
|---|---|---|---|
| app/Models/PurchaseOrder.php | 71 | `generateNumber(): string` *(static)* | Nomor PO berurutan dari format `po_format`. |
| app/Models/PurchaseOrder.php | 100 | `statusLabels(): array` *(static)* | Peta status PO → label. |
| app/Models/PurchaseVoucher.php | 54 | `generateNumber(): string` *(static)* | Nomor PV berurutan dari format `pv_format`. |
| app/Models/PurchaseVoucher.php | 80 | `statusLabels(): array` *(static)* | Peta status PV → label. |

---

## 6. Accounting

Mencakup **Chart of Accounts**, **Fiscal Period**, **Journal Entry/Line**, **AR Aging**, **GL Setting**, **Tax Setting**, **Audit Trail**. Sumber: 8 controller di [app/Http/Controllers/Accounting/](app/Http/Controllers/Accounting/).

### Alur Proses

#### Chart of Accounts
`index()` (`ChartOfAccountController.php:16`), `store()` (51), `update()` (66), `destroy()` (81 — log audit **sebelum** delete; `journal_lines.chart_of_account_id` cascade, jadi hapus akun ikut menghapus journal line yang memakainya, **tanpa guard "sedang dipakai"**).

#### Fiscal Period
`index()` (`FiscalPeriodController.php:15`), `store()` (49 — `status` in `open,closed`), `update()` (64 — **inilah cara buka/tutup periode**, tidak ada endpoint khusus), `destroy()` (79 — cascade ke `journal_entries` terkait).
`FiscalPeriod::findOpenPeriodForDate()` (`app/Models/FiscalPeriod.php:31`) adalah satu-satunya tempat yang menegakkan aturan "periode harus terbuka" — dipakai oleh posting GL otomatis dari modul Sales.

**Catatan penting**: form Manual Journal **tidak** mengecek status open/closed fiscal period — hanya validasi `exists:fiscal_periods,id`. Jadi user bisa memasukkan jurnal manual ke periode yang sudah `closed` lewat UI.

#### Manual Journal
1. **Buat** — `JournalEntryController::store()` (`app/Http/Controllers/Accounting/JournalEntryController.php:78`): validasi `lines` min 2 baris; **cek debit = credit** (93-100, `ValidationException` jika beda); dalam transaksi buat `JournalEntry` + `JournalLine` per baris (102-120).
2. **Update** — `update()` (128): validasi sama, hapus semua line lama (162) lalu buat ulang (164-172).
3. **Hapus** — `destroy()` (180): cascade ke `journal_lines`.
4. `JournalEntry::generateEntryNumber()` (`app/Models/JournalEntry.php:39`) format `{prefix}-{YYYYMM}-{00001}` — dipakai oleh posting otomatis dari Sales, bukan oleh form manual (yang mewajibkan user mengetik `entry_number` sendiri).

#### Journal Entries / Journal Lines (viewer + CRUD baris)
- `JournalEntryController::index()` (20): eager-load `fiscalPeriod`, `lines.chartOfAccount`.
- `JournalLineController::index()` (`JournalLineController.php:17`): CRUD baris individual — `store()` (60), `update()` (76), `destroy()` (92). **Tidak** tunduk pada cek debit=credit milik `JournalEntryController` — mengubah baris di sini bisa membuat entri jadi tidak seimbang.

#### AR Aging
`ArAgingController::index()` (`ArAgingController.php:12`) — **dihitung live dari `invoices` + `payments`, bukan tabel tersendiri**: query `Invoice::whereIn('status', [SENT, PARTIALLY_PAID])->withSum('payments as amount_paid','amount')`; `balance_due = total_amount - amount_paid`; `days_overdue` dari `due_date` (fallback `invoice_date`); dikelompokkan via `agingBucket()` (63) ke `Not Due`, `1-30`, `31-60`, `61-90`, `90+ Days`.

#### GL Setting & Tax Setting
Keduanya disimpan sebagai key-value di `app_settings` (bukan tabel khusus). `GlSettingController::edit()`/`update()` (15/36) — mapping 4 akun: `gl_ar_account_id`, `gl_sales_revenue_account_id`, `gl_sales_tax_payable_account_id`, `gl_cash_bank_account_id`. `TaxSettingController::edit()`/`update()` (14/22) — `tax_rate`. **Tidak ada** panggilan `AccountingAuditLogger` di kedua controller ini — perubahan setting GL/Tax tidak masuk audit trail.

#### Audit Trail
`AccountingAuditTrailController::index()` (14): daftar `AccountingAuditTrail` + user, `->latest('happened_at')`. `destroy()` (51): hapus baris audit — **tidak mencatat penghapusannya sendiri**. Ditulis lewat helper [app/Support/AccountingAuditLogger.php](app/Support/AccountingAuditLogger.php)`::record()` (10) yang dipanggil dari ChartOfAccount/FiscalPeriod/JournalEntry(manual)/JournalLine controllers.

#### Auto-posting dari modul Sales (integrasi lintas modul)
- `PurchaseVoucherController.php` — **tidak ada** posting GL sama sekali (dicek eksplisit, tidak ditemukan referensi Journal/ChartOfAccount/gl_).
- `InvoiceController::send()` (`app/Http/Controllers/InvoiceController.php:375`) memanggil `postInvoiceBillingToGl()` (600): cek `FiscalPeriod::findOpenPeriodForDate()`, buat `JournalEntry` (status `posted`) + `JournalLine` DR AR / CR Sales Revenue (+ CR Sales Tax Payable jika ada pajak).
- `InvoiceController::recordPayment()` (490) memanggil `postPaymentToGl()` (665): buat `JournalEntry` + `JournalLine` DR Cash/Bank / CR AR, lalu `$payment->journal_entry_id` di-set ke entry ini.

### Relasi Database

| Tabel | Kolom FK | Referensi | On Delete |
|---|---|---|---|
| `journal_entries` | `fiscal_period_id` | `fiscal_periods.id` | cascade |
| `journal_lines` | `journal_entry_id`, `chart_of_account_id` | `journal_entries.id`, `chart_of_accounts.id` | cascade keduanya |
| `accounting_audit_trails` | `user_id` (nullable) | `users.id` | nullOnDelete |

**Relasi Eloquent:**

| Model | Method | File:Baris | Target |
|---|---|---|---|
| ChartOfAccount | `journalLines()` | app/Models/ChartOfAccount.php:21 | hasMany `JournalLine` |
| FiscalPeriod | `journalEntries()` | app/Models/FiscalPeriod.php:26 | hasMany `JournalEntry` |
| FiscalPeriod | `findOpenPeriodForDate(string $date): ?self` | app/Models/FiscalPeriod.php:31 | static — cari periode open yang mencakup tanggal |
| JournalEntry | `fiscalPeriod()` / `lines()` | app/Models/JournalEntry.php:29 / 34 | belongsTo / hasMany |
| JournalLine | `journalEntry()` / `chartOfAccount()` | app/Models/JournalLine.php:26 / 31 | belongsTo |
| AccountingAuditTrail | `user()` | app/Models/AccountingAuditTrail.php:27 | belongsTo |

Catatan: `accounting_audit_trails.subject_type`/`subject_id` adalah pointer polymorphic manual (bukan `morphTo()` Eloquent) — controller menampilkannya sebagai string `"{subject_type}#{subject_id}"` (`AccountingAuditTrailController.php:36`), bukan relasi resolvable.

**Cara verifikasi di aplikasi:**
- `JournalEntryController::index()` — `->with(['fiscalPeriod:id,code', 'lines.chartOfAccount:id,code,name'])` (`JournalEntryController.php:25`).
- Halaman `/accounting/journal-entries` menampilkan entri + kode fiscal period + baris debit/kredit + nama akun sekaligus.
- Tinker:
```php
$entry = App\Models\JournalEntry::with('fiscalPeriod', 'lines.chartOfAccount')->latest()->first();
$entry->lines->pluck('chartOfAccount.name', 'line_type');
```

### Daftar Fungsi

**app/Http/Controllers/Accounting/ChartOfAccountController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 16 | `index(Request $request): Response` | Daftar akun + pencarian. |
| 51 | `store(Request $request): RedirectResponse` | Buat akun baru + audit trail. |
| 66 | `update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse` | Update akun + audit trail. |
| 81 | `destroy(ChartOfAccount $chartOfAccount): RedirectResponse` | Audit trail lalu hapus (cascade journal_lines). |

**app/Http/Controllers/Accounting/FiscalPeriodController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 15 | `index(Request $request): Response` | Daftar periode fiskal. |
| 49 | `store(Request $request): RedirectResponse` | Buat periode baru + audit trail. |
| 64 | `update(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse` | Update periode (termasuk buka/tutup) + audit trail. |
| 79 | `destroy(FiscalPeriod $fiscalPeriod): RedirectResponse` | Audit trail lalu hapus (cascade journal_entries). |

**app/Http/Controllers/Accounting/JournalEntryController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 20 | `index(Request $request): Response` | Daftar jurnal + baris-barisnya. |
| 70 | `manualJournal(): Response` | Form input jurnal manual. |
| 78 | `store(Request $request): RedirectResponse` | Cek debit=credit, buat entri + baris (transaksi). |
| 128 | `update(Request $request, JournalEntry $journalEntry): RedirectResponse` | Cek ulang balance, replace baris. |
| 180 | `destroy(JournalEntry $journalEntry): RedirectResponse` | Audit trail lalu hapus (cascade lines). |

**app/Http/Controllers/Accounting/JournalLineController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 17 | `index(Request $request): Response` | Daftar baris jurnal + pencarian. |
| 60 | `store(Request $request): RedirectResponse` | Buat satu baris jurnal langsung (tanpa cek balance). |
| 76 | `update(Request $request, JournalLine $journalLine): RedirectResponse` | Update satu baris jurnal. |
| 92 | `destroy(JournalLine $journalLine): RedirectResponse` | Audit trail lalu hapus baris. |

**app/Http/Controllers/Accounting/ArAgingController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 12 | `index(): Response` | Hitung & tampilkan laporan AR Aging dari invoice belum lunas. |
| 63 | `agingBucket(int $daysOverdue): string` *(private)* | Kelompokkan hari terlambat ke bucket umur piutang. |

**app/Http/Controllers/Accounting/GlSettingController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 15 | `edit(): Response` | Form mapping akun GL. |
| 36 | `update(Request $request): RedirectResponse` | Simpan mapping akun GL ke `app_settings`. |
| 52 | `intOrNull(mixed $value): ?int` *(private)* | Konversi nilai setting ke integer/null. |

**app/Http/Controllers/Accounting/TaxSettingController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 14 | `edit(): Response` | Form tarif pajak saat ini. |
| 22 | `update(Request $request): RedirectResponse` | Simpan `tax_rate` ke `app_settings`. |

**app/Http/Controllers/Accounting/AccountingAuditTrailController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 14 | `index(Request $request): Response` | Daftar audit trail + pencarian. |
| 51 | `destroy(AccountingAuditTrail $accountingAuditTrail): RedirectResponse` | Hapus satu baris audit trail. |

**Support**

| File | Baris | Fungsi | Deskripsi |
|---|---|---|---|
| app/Support/AccountingAuditLogger.php | 10 | `record(string $action, Model $subject, ?string $details = null): void` *(static)* | Buat satu baris `accounting_audit_trails`. |
| app/Models/JournalEntry.php | 39 | `generateEntryNumber(): string` *(static)* | Nomor entri otomatis `{prefix}-{YYYYMM}-{00001}`. |
| app/Http/Controllers/InvoiceController.php | 600 | `postInvoiceBillingToGl(Invoice $invoice): void` *(private)* | Posting jurnal AR billing saat invoice dikirim. |
| app/Http/Controllers/InvoiceController.php | 665 | `postPaymentToGl(Payment $payment, Invoice $invoice): void` *(private)* | Posting jurnal settlement saat pembayaran dicatat. |

---

## 7. Settings & Access Control

Mencakup **App Setting**, **General Setting**, **Profile**, **Role Access**, **Security/2FA**. Sumber: [app/Http/Controllers/Settings/](app/Http/Controllers/Settings/), [app/Models/User.php](app/Models/User.php), [routes/settings.php](routes/settings.php).

> **Catatan arsitektur**: aplikasi ini **tidak** memakai `spatie/laravel-permission`. Tidak ada tabel `roles`/`permissions`. Role = kolom string `users.role` (`staff|admin|gm|director`, default `staff`). Permission = kolom JSON `users.permissions` yang **hanya menyimpan override** terhadap template default per role — key yang tidak pernah disimpan jatuh ke default `permissionsTemplateForRole()`.

### Alur Proses

#### App Setting (format penomoran dokumen)
`GET settings/app` ([routes/settings.php:45](routes/settings.php#L45)) — **hanya middleware `auth`**, tidak ada `permission:` khusus, semua user login bisa akses. `AppSettingController::edit()` (`app/Http/Controllers/Settings/AppSettingController.php:14`) membaca `wo_format`/`po_format`/`co_format`/`quotation_format`/`pv_format` dari `AppSetting`, fallback ke method default privat masing-masing (82-150). `update()` (34) memvalidasi struktur (`prefix`, `components[].type` in `prefix|year|month|sequential`, `separator`) lalu `AppSetting::set()` per key.

#### General Setting (mata uang & payment terms)
`GET settings/general` ([routes/settings.php:29](routes/settings.php#L29)) — middleware `permission:menu.settings.general`. `GeneralSettingController::edit()` (`app/Http/Controllers/Settings/GeneralSettingController.php:15`): auto-seed currency `IDR` jika tabel `currencies` kosong; sinkronkan `is_default`. `update()` (79), `storeCurrency()` (100), `setDefaultCurrency()` (130). Payment terms: `paymentTerms()` (140), `storePaymentTerm()` (148), `updatePaymentTerm()` (166), `destroyPaymentTerm()` (192) — semua berujung ke `savePaymentTerms()` (237) yang menulis ulang array JSON penuh ke `AppSetting::set('payment_terms_options', ...)`.

#### Profile
`ProfileController::edit()` (`app/Http/Controllers/Settings/ProfileController.php:20`), `update()` (31 — email berubah → `email_verified_at` di-null-kan), `destroy()` (47 — hapus akun + logout + invalidate session).

#### Role Access (penetapan role & permission)
Di-gate **dobel**: middleware route `permission:menu.settings.role_access` ([routes/settings.php:39](routes/settings.php#L39)) **dan** pengecekan internal `authorizeManagementAccess()` (`app/Http/Controllers/Settings/RoleAccessController.php:107`) yang mensyaratkan `isManagement()` + permission yang sama.
1. `edit()` (19): semua user + `resolvedPermissions()` masing-masing (hasil merge default template + override tersimpan), daftar role, `User::permissionLabels()`, `User::permissionsTemplateForRole()`.
2. `update(Request $request, User $user)` (55): server membangun ulang permission mulai dari `permissionsTemplateForRole($role)` lalu meng-override tiap key yang ada di `permissionLabels()` dengan input user — **mencegah user menyuntikkan key permission asing**. Simpan langsung ke kolom JSON `permissions`.
3. `store()` (82): buat user baru dari halaman ini, password di-hash manual (`Hash::make()`, bukan lewat Fortify), permission awal dari template role.
4. **Enforcement runtime**: setiap route ber-middleware `permission:xxx` diproses `EnsureUserPermission::handle()` ([app/Http/Middleware/EnsureUserPermission.php:15](app/Http/Middleware/EnsureUserPermission.php#L15)) → `$user->hasPermission($permission)` → `resolvedPermissions()[$key]` (`app/Models/User.php:171-194`). Hasil `resolvedPermissions()` juga di-share ke semua halaman Vue lewat `HandleInertiaRequests::share()` (`app/Http/Middleware/HandleInertiaRequests.php:42-48`) untuk show/hide menu sidebar tanpa request tambahan.

#### Security / Two-Factor Authentication
`GET settings/security` ([routes/settings.php:20](routes/settings.php#L20)). `SecurityController::edit()` (`app/Http/Controllers/Settings/SecurityController.php:31`): kirim status 2FA (`hasEnabledTwoFactorAuthentication()`, dari trait Fortify). Aktivasi/nonaktifasi 2FA (QR, kode konfirmasi, recovery codes) ditangani route bawaan **Laravel Fortify**, bukan controller custom. `update()` (50): ganti password (`PasswordUpdateRequest`), otomatis di-hash lewat cast `'password' => 'hashed'`.

### Relasi Database

| Tabel | Kolom relevan | Migrasi |
|---|---|---|
| `users` | `role` string(30) default `staff` | [2026_04_12_000004](database/migrations/2026_04_12_000004_add_role_to_users_table.php) |
| `users` | `permissions` JSON nullable | [2026_04_12_000007](database/migrations/2026_04_12_000007_add_permissions_to_users_table.php) |
| `users` | `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` | [2025_08_14_170933](database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php) |
| `app_settings` | `key` (unique), `value` (text nullable) | [2026_03_15_000009](database/migrations/2026_03_15_000009_create_app_settings_table.php) |

**Tidak ada tabel `roles`/`permissions` dan tidak ada relasi Eloquent `belongsTo`/`belongsToMany` antar `User` dan role/permission** — peta role→permission default sepenuhnya array PHP statis di `User::permissionsTemplateForRole()` (`app/Models/User.php:125`), bukan data database.

**Cara verifikasi di `tinker`:**
```php
$u = App\Models\User::find(1);
$u->hasPermission('menu.sales');      // app/Models/User.php:191
$u->resolvedPermissions();            // app/Models/User.php:171 — matriks final (default + override)
$u->permissions;                      // kolom JSON mentah (hanya override, cast array)
$u->role;                             // staff|admin|gm|director
$u->isManagement();                   // app/Models/User.php:92

App\Models\AppSetting::get('default_currency_code'); // app/Models/AppSetting.php:15 (cache forever)
App\Models\AppSetting::set('inventory_low_stock_threshold', 5); // app/Models/AppSetting.php:26
```
**Catatan cache**: `AppSetting::get()` memakai `Cache::rememberForever("app_setting.{$key}", ...)` — jika data diubah langsung lewat DB (bukan lewat `AppSetting::set()`), nilai lama tetap terbaca sampai cache di-`forget` manual.

### Daftar Fungsi

**app/Http/Controllers/Settings/AppSettingController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 14 | `edit(): Response` | Form pengaturan format penomoran WO/PO/CO/Quotation/PV. |
| 34 | `update(Request $request): RedirectResponse` | Validasi & simpan kelima format ke `app_settings`. |
| 82 | `defaultWoFormat(): array` *(private)* | Default format nomor WO. |
| 96 | `defaultPoFormat(): array` *(private)* | Default format nomor PO. |
| 110 | `defaultCoFormat(): array` *(private)* | Default format nomor CO. |
| 124 | `defaultQuotationFormat(): array` *(private)* | Default format nomor Quotation. |
| 138 | `defaultPvFormat(): array` *(private)* | Default format nomor PV. |

**app/Http/Controllers/Settings/GeneralSettingController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 15 | `edit(): Response` | Halaman General Settings (currency default + daftar). |
| 79 | `update(Request $request): RedirectResponse` | Ubah currency default aplikasi. |
| 100 | `storeCurrency(Request $request): RedirectResponse` | Tambah currency baru. |
| 130 | `setDefaultCurrency(Currency $currency): RedirectResponse` | Jadikan currency tertentu default. |
| 140 | `paymentTerms(): Response` | Halaman daftar payment terms. |
| 148 | `storePaymentTerm(Request $request): RedirectResponse` | Tambah payment term. |
| 166 | `updatePaymentTerm(Request $request, int $index): RedirectResponse` | Ubah payment term. |
| 192 | `destroyPaymentTerm(int $index): RedirectResponse` | Hapus payment term. |
| 209 | `paymentTermList(): array` *(private)* | Baca & normalisasi daftar payment terms. |
| 237 | `savePaymentTerms(array $terms): void` *(private)* | Simpan ulang seluruh daftar payment terms. |
| 242 | `normalizePaymentTerm(string $term): string` *(private)* | Rapikan teks term. |
| 250 | `containsPaymentTerm(array $terms, string $term): bool` *(private)* | Cek duplikasi term (case-insensitive). |

**app/Http/Controllers/Settings/ProfileController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 20 | `edit(Request $request): Response` | Halaman profil user. |
| 31 | `update(ProfileUpdateRequest $request): RedirectResponse` | Update profil; reset verifikasi email jika email berubah. |
| 47 | `destroy(ProfileDeleteRequest $request): RedirectResponse` | Hapus akun + logout. |

**app/Http/Controllers/Settings/RoleAccessController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 19 | `edit(Request $request): Response` | Halaman manajemen role & akses semua user. |
| 55 | `update(Request $request, User $user): RedirectResponse` | Ubah role & matriks permission user (dinormalisasi terhadap template). |
| 82 | `store(Request $request): RedirectResponse` | Buat user baru dari halaman Role Access. |
| 107 | `authorizeManagementAccess(Request $request): void` *(private)* | Guard hanya user manajemen + permission `menu.settings.role_access`. |

**app/Http/Controllers/Settings/SecurityController.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 20 | `middleware(): array` *(static)* | Tambah middleware `password.confirm` untuk action `edit` jika 2FA aktif. |
| 31 | `edit(TwoFactorAuthenticationRequest $request): Response` | Halaman status keamanan/2FA. |
| 50 | `update(PasswordUpdateRequest $request): RedirectResponse` | Update password user. |

**app/Models/User.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 79 | `casts(): array` *(protected)* | Cast `permissions` → array, `password` → hashed. |
| 92 | `isManagement(): bool` | Cek role termasuk level manajemen (admin/gm/director). |
| 102 | `defaultPermissions(): array` *(static)* | Matriks permission default (fallback) semua key. |
| 125 | `permissionsTemplateForRole(string $role): array` *(static)* | Template permission default per role. |
| 148 | `permissionLabels(): array` *(static)* | Label deskriptif tiap key permission untuk UI. |
| 171 | `resolvedPermissions(): array` | Gabungkan permission tersimpan dengan template role → matriks final. |
| 191 | `hasPermission(string $key): bool` | Cek user punya permission tertentu. |
| 199 | `canApprovePurchaseOrder(): bool` | Cek user berhak approve/reject PO. |
| 207 | `canApproveInvoicePayment(): bool` | Cek user berhak approve/reject pembayaran invoice. |
| 215 | `canApprovePurchaseVoucher(): bool` | Cek user berhak approve/reject PV. |

**app/Models/AppSetting.php**

| Baris | Fungsi | Deskripsi |
|---|---|---|
| 15 | `get(string $key, mixed $default = null): mixed` *(static)* | Ambil nilai setting (cache forever) dengan fallback default. |
| 26 | `set(string $key, mixed $value): void` *(static)* | Simpan (upsert) nilai setting + invalidate cache. |

**Middleware & wiring pendukung**

| File | Baris | Fungsi | Deskripsi |
|---|---|---|---|
| app/Http/Middleware/EnsureUserPermission.php | 15 | `handle(Request $request, Closure $next, string $permission): Response` | Middleware `permission:xxx`, abort 403 jika tidak punya izin. |
| app/Http/Middleware/HandleInertiaRequests.php | 37 | `share(Request $request): array` | Broadcast `auth.user.permissions` ke semua halaman Vue. |
| app/Actions/Fortify/CreateNewUser.php | 20 | `create(array $input): User` | Buat user saat registrasi publik Fortify. |
| app/Actions/Fortify/ResetUserPassword.php | 19 | `reset(User $user, array $input): void` | Reset password lewat alur lupa password Fortify. |

---

## Catatan Tambahan

- Halaman `settings/app`, `settings/profile`, dan `settings/security` **tidak** dilindungi middleware `permission:` — hanya `auth` (dan `verified` untuk sebagian). Semua user yang login bisa mengubah format penomoran dokumen WO/PO/CO/Quotation/PV.
- Dua key `AppSetting` yang dipakai Dashboard (`inventory_low_stock_threshold`, `inventory_overstock_threshold`) tidak punya form pengaturan khusus di controller manapun — kemungkinan diset manual lewat `tinker`/seeder.
- Pola *full-resync* (hapus semua baris anak lalu buat ulang saat update) dipakai konsisten di banyak modul: Part (supplier prices & stocks), BOM (items), Quotation/Customer Order (items), Invoice (items), Journal Entry (lines) — bukan diff/merge parsial.
- Proyek ini memakai `laravel/wayfinder` untuk generate helper route TypeScript. Saat menjalankan ulang generator-nya, gunakan flag `--with-form` — tanpa flag ini, helper `.form` yang dipakai di beberapa halaman Vue akan hilang/rusak.
