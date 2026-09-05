<?php

namespace App\Http\Controllers;

use App\Http\Requests\Part\StorePartRequest;
use App\Http\Requests\Part\UpdatePartRequest;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PartSupplierPrice;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\ToolLoan;
use App\Models\Uom;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PartController extends Controller
{
    private const TEMPLATE_HEADERS = [
        'Part Number', 'Part Name', 'Category', 'Inventory Type', 'UOM Code',
        'Description', 'Selling Price', 'Safety Stock', 'Supplier Name', 'Purchase Price',
        'Warehouse Code', 'Initial Stock',
    ];

    private const TEMPLATE_COLUMNS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];

    /**
     * Show list of parts and supplier prices.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $parts = Part::query()
            ->with(['supplierPrices.supplier', 'defaultUom'])
            ->withSum('stocks as total_stock', 'quantity')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('part_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplierPrices.supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $partItems = collect($parts->items())->map(function (Part $part): array {
            return [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'category' => $part->category,
                'inventory_type' => $part->inventory_type,
                'default_uom_id' => $part->default_uom_id,
                'default_uom_code' => $part->defaultUom?->code,
                'default_warehouse_id' => $part->default_warehouse_id,
                'description' => $part->description,
                'selling_price' => (float) $part->selling_price,
                'safety_stock' => (int) $part->safety_stock,
                'total_stock' => (int) ($part->total_stock ?? 0),
                'suppliers' => $part->supplierPrices->map(function (PartSupplierPrice $price): array {
                    return [
                        'id' => $price->id,
                        'supplier_id' => $price->supplier_id,
                        'supplier_name' => $price->supplier?->name,
                        'purchase_price' => (float) $price->purchase_price,
                    ];
                })->values(),
                'stocks' => $part->stocks()
                    ->with('warehouse:id,code,name')
                    ->get()
                    ->map(function (Stock $stock): array {
                        return [
                            'id' => $stock->id,
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_code' => $stock->warehouse?->code,
                            'warehouse_name' => $stock->warehouse?->name,
                            'quantity' => (int) $stock->quantity,
                        ];
                    })->values(),
            ];
        })->values();

        return Inertia::render('parts/List', [
            'parts' => $partItems,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'uoms' => Uom::query()->orderBy('code')->get(['id', 'code', 'name']),
            'defaultCurrency' => Currency::currentDefault(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $parts->currentPage(),
                'last_page' => $parts->lastPage(),
                'per_page' => $parts->perPage(),
                'total' => $parts->total(),
                'from' => $parts->firstItem(),
                'to' => $parts->lastItem(),
                'prev_page_url' => $parts->previousPageUrl(),
                'next_page_url' => $parts->nextPageUrl(),
            ],
            'importResult' => session('import_result'),
        ]);
    }

    /**
     * Export parts (respecting the current search filter) to an Excel file.
     * The file uses the same layout as the import template, so it can be
     * edited and re-imported to bulk-update parts.
     */
    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search'));

        $parts = Part::query()
            ->with(['supplierPrices.supplier', 'stocks.warehouse', 'defaultUom'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('part_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('part_number')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Parts');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        $rows = [];

        foreach ($parts as $part) {
            $suppliers = $part->supplierPrices;
            $stocks = $part->stocks;
            $lineCount = max($suppliers->count(), $stocks->count(), 1);

            for ($i = 0; $i < $lineCount; $i++) {
                $supplier = $suppliers->get($i);
                $stock = $stocks->get($i);

                $rows[] = [
                    $part->part_number,
                    $i === 0 ? $part->name : '',
                    $i === 0 ? $part->category : '',
                    $i === 0 ? $part->inventory_type : '',
                    $i === 0 ? $part->defaultUom?->code : '',
                    $i === 0 ? $part->description : '',
                    $i === 0 ? (float) $part->selling_price : '',
                    $i === 0 ? (int) $part->safety_stock : '',
                    $supplier?->supplier?->name,
                    $supplier ? (float) $supplier->purchase_price : '',
                    $stock?->warehouse?->code,
                    $stock ? (int) $stock->quantity : '',
                ];
            }
        }

        $sheet->fromArray($rows, null, 'A2');

        foreach (self::TEMPLATE_COLUMNS as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'parts-'.now()->format('Y-m-d-His').'.xlsx');
    }

    /**
     * Download a blank template (with example rows) used to bulk-add parts via import.
     */
    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        $sheet->fromArray([
            ['PN-0001', 'Baut M6x20', 'purchase', 'material', 'PCS', 'Baut hex M6 panjang 20mm', 500, 100, 'PT Sumber Jaya', 350, 'WH-01', 200],
            ['PN-0002', 'Bracket Motor', 'manufacture', 'material', 'PCS', 'Bracket hasil produksi', 25000, 10, '', '', 'WH-01', 5],
        ], null, 'A2');

        for ($row = 2; $row <= 200; $row++) {
            $categoryValidation = $sheet->getCell("C{$row}")->getDataValidation();
            $categoryValidation->setType(DataValidation::TYPE_LIST);
            $categoryValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $categoryValidation->setAllowBlank(true);
            $categoryValidation->setShowDropDown(true);
            $categoryValidation->setShowErrorMessage(true);
            $categoryValidation->setErrorTitle('Category tidak valid');
            $categoryValidation->setError('Gunakan "purchase" atau "manufacture".');
            $categoryValidation->setFormula1('"purchase,manufacture"');

            $inventoryValidation = $sheet->getCell("D{$row}")->getDataValidation();
            $inventoryValidation->setType(DataValidation::TYPE_LIST);
            $inventoryValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $inventoryValidation->setAllowBlank(true);
            $inventoryValidation->setShowDropDown(true);
            $inventoryValidation->setShowErrorMessage(true);
            $inventoryValidation->setErrorTitle('Inventory Type tidak valid');
            $inventoryValidation->setError('Gunakan "material" atau "tool".');
            $inventoryValidation->setFormula1('"material,tool"');
        }

        foreach (self::TEMPLATE_COLUMNS as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'parts-import-template.xlsx');
    }

    /**
     * Import parts (and optionally their supplier prices / warehouse stock) from an Excel file.
     *
     * Each row upserts a part by part_number. Core fields (name/category/inventory_type/...)
     * are only required the first time a part_number is seen; a part_number can repeat across
     * several rows to attach additional supplier prices or warehouse stock to the same part -
     * this is exactly how export() lays out parts that have more than one supplier or warehouse.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'File tidak dapat dibaca. Pastikan formatnya sesuai template.',
            ]);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $startIndex = isset($rows[0]) && $this->looksLikeHeaderRow($rows[0]) ? 1 : 0;

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $startIndex, &$created, &$updated, &$errors): void {
            /** @var array<string, Part> $partsInBatch */
            $partsInBatch = [];

            for ($i = $startIndex; $i < count($rows); $i++) {
                $lineNumber = $i + 1;
                $row = $rows[$i];

                $partNumber = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                $category = strtolower(trim((string) ($row[2] ?? '')));
                $inventoryType = strtolower(trim((string) ($row[3] ?? '')));
                $uomCode = trim((string) ($row[4] ?? ''));
                $description = trim((string) ($row[5] ?? ''));
                $sellingPriceRaw = trim((string) ($row[6] ?? ''));
                $safetyStockRaw = trim((string) ($row[7] ?? ''));
                $supplierName = trim((string) ($row[8] ?? ''));
                $purchasePriceRaw = trim((string) ($row[9] ?? ''));
                $warehouseCode = trim((string) ($row[10] ?? ''));
                $initialStockRaw = trim((string) ($row[11] ?? ''));

                $rowIsBlank = $partNumber === '' && $name === '' && $category === '' && $inventoryType === ''
                    && $supplierName === '' && $warehouseCode === '';

                if ($rowIsBlank) {
                    continue;
                }

                if ($partNumber === '') {
                    $errors[] = "Baris {$lineNumber}: Part Number wajib diisi.";

                    continue;
                }

                $part = $partsInBatch[$partNumber] ?? Part::query()->where('part_number', $partNumber)->first();
                $isNewPart = $part === null;

                if ($isNewPart) {
                    if ($name === '') {
                        $errors[] = "Baris {$lineNumber}: Part Name wajib diisi untuk part baru '{$partNumber}'.";

                        continue;
                    }

                    if (! in_array($category, [Part::CATEGORY_PURCHASE, Part::CATEGORY_MANUFACTURE], true)) {
                        $errors[] = "Baris {$lineNumber}: Category '{$category}' tidak valid (gunakan purchase/manufacture).";

                        continue;
                    }

                    if (! in_array($inventoryType, [Part::INVENTORY_TYPE_MATERIAL, Part::INVENTORY_TYPE_TOOL], true)) {
                        $errors[] = "Baris {$lineNumber}: Inventory Type '{$inventoryType}' tidak valid (gunakan material/tool).";

                        continue;
                    }
                } elseif ($name !== '' || $category !== '' || $inventoryType !== '') {
                    if ($category !== '' && ! in_array($category, [Part::CATEGORY_PURCHASE, Part::CATEGORY_MANUFACTURE], true)) {
                        $errors[] = "Baris {$lineNumber}: Category '{$category}' tidak valid (gunakan purchase/manufacture).";

                        continue;
                    }

                    if ($inventoryType !== '' && ! in_array($inventoryType, [Part::INVENTORY_TYPE_MATERIAL, Part::INVENTORY_TYPE_TOOL], true)) {
                        $errors[] = "Baris {$lineNumber}: Inventory Type '{$inventoryType}' tidak valid (gunakan material/tool).";

                        continue;
                    }
                }

                $uomId = null;

                if ($uomCode !== '') {
                    $uom = Uom::query()->whereRaw('LOWER(code) = ?', [strtolower($uomCode)])->first();

                    if (! $uom) {
                        $errors[] = "Baris {$lineNumber}: UOM Code '{$uomCode}' tidak ditemukan.";

                        continue;
                    }

                    $uomId = $uom->id;
                }

                if ($sellingPriceRaw !== '' && (! is_numeric($sellingPriceRaw) || (float) $sellingPriceRaw < 0)) {
                    $errors[] = "Baris {$lineNumber}: Selling Price harus berupa angka >= 0.";

                    continue;
                }

                if ($safetyStockRaw !== '' && (! is_numeric($safetyStockRaw) || (int) $safetyStockRaw < 0)) {
                    $errors[] = "Baris {$lineNumber}: Safety Stock harus berupa angka >= 0.";

                    continue;
                }

                if ($isNewPart) {
                    $part = Part::query()->create([
                        'part_number' => $partNumber,
                        'name' => $name,
                        'category' => $category,
                        'inventory_type' => $inventoryType,
                        'default_uom_id' => $uomId,
                        'description' => $description !== '' ? $description : null,
                        'selling_price' => $sellingPriceRaw !== '' ? (float) $sellingPriceRaw : 0,
                        'safety_stock' => $safetyStockRaw !== '' ? (int) $safetyStockRaw : 0,
                    ]);
                    $created++;
                } else {
                    if (! isset($partsInBatch[$partNumber])) {
                        $part->update([
                            'name' => $name !== '' ? $name : $part->name,
                            'category' => $category !== '' ? $category : $part->category,
                            'inventory_type' => $inventoryType !== '' ? $inventoryType : $part->inventory_type,
                            'default_uom_id' => $uomCode !== '' ? $uomId : $part->default_uom_id,
                            'description' => $description !== '' ? $description : $part->description,
                            'selling_price' => $sellingPriceRaw !== '' ? (float) $sellingPriceRaw : $part->selling_price,
                            'safety_stock' => $safetyStockRaw !== '' ? (int) $safetyStockRaw : $part->safety_stock,
                        ]);
                        $updated++;
                    }
                }

                $partsInBatch[$partNumber] = $part;

                if ($supplierName !== '') {
                    $supplier = Supplier::query()->whereRaw('LOWER(name) = ?', [strtolower($supplierName)])->first();

                    if (! $supplier) {
                        $errors[] = "Baris {$lineNumber}: Supplier '{$supplierName}' tidak ditemukan.";

                        continue;
                    }

                    if ($purchasePriceRaw !== '' && (! is_numeric($purchasePriceRaw) || (float) $purchasePriceRaw < 0)) {
                        $errors[] = "Baris {$lineNumber}: Purchase Price harus berupa angka >= 0.";

                        continue;
                    }

                    PartSupplierPrice::query()->updateOrCreate(
                        ['part_id' => $part->id, 'supplier_id' => $supplier->id],
                        ['purchase_price' => $purchasePriceRaw !== '' ? (float) $purchasePriceRaw : 0]
                    );
                }

                if ($warehouseCode !== '') {
                    $warehouse = Warehouse::query()->whereRaw('LOWER(code) = ?', [strtolower($warehouseCode)])->first();

                    if (! $warehouse) {
                        $errors[] = "Baris {$lineNumber}: Warehouse Code '{$warehouseCode}' tidak ditemukan.";

                        continue;
                    }

                    if ($initialStockRaw !== '' && (! is_numeric($initialStockRaw) || (int) $initialStockRaw < 0)) {
                        $errors[] = "Baris {$lineNumber}: Initial Stock harus berupa angka >= 0.";

                        continue;
                    }

                    Stock::query()->updateOrCreate(
                        ['part_id' => $part->id, 'warehouse_id' => $warehouse->id],
                        ['quantity' => $initialStockRaw !== '' ? (int) $initialStockRaw : 0]
                    );
                }
            }
        });

        return back()->with('import_result', [
            'created' => $created,
            'updated' => $updated,
            'errors' => array_slice($errors, 0, 50),
            'errorCount' => count($errors),
        ]);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        return strcasecmp(trim((string) ($row[0] ?? '')), 'Part Number') === 0;
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Show part registration page.
     */
    public function create(): Response
    {
        return Inertia::render('parts/Register', [
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name', 'location']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'uoms' => Uom::query()->orderBy('code')->get(['id', 'code', 'name']),
            'defaultCurrency' => Currency::currentDefault(),
        ]);
    }

    /**
     * Store a new part along with supplier prices and initial stock.
     */
    public function store(StorePartRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $part = Part::query()->create([
                'part_number' => $validated['part_number'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'inventory_type' => $validated['inventory_type'],
                'default_uom_id' => $validated['default_uom_id'] ?? null,
                'default_warehouse_id' => $validated['default_warehouse_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'selling_price' => $validated['selling_price'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            collect($validated['suppliers'] ?? [])
                ->unique('supplier_id')
                ->each(function (array $supplierRow) use ($part): void {
                    PartSupplierPrice::query()->create([
                        'part_id' => $part->id,
                        'supplier_id' => $supplierRow['supplier_id'],
                        'purchase_price' => $supplierRow['purchase_price'],
                    ]);
                });

            $stockRows = collect($validated['stocks'] ?? [])
                ->filter(fn (array $stock): bool => (int) $stock['quantity'] > 0)
                ->groupBy('warehouse_id')
                ->map(fn (Collection $rows): int => $rows->sum('quantity'));

            foreach ($stockRows as $warehouseId => $quantity) {
                Stock::query()->create([
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                ]);
            }
        });

        return to_route('parts.index');
    }

    /**
     * Update an existing part and its supplier prices.
     */
    public function update(UpdatePartRequest $request, Part $part): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $part): void {
            $part->update([
                'part_number' => $validated['part_number'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'inventory_type' => $validated['inventory_type'],
                'default_uom_id' => $validated['default_uom_id'] ?? null,
                'default_warehouse_id' => $validated['default_warehouse_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'selling_price' => $validated['selling_price'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            $part->supplierPrices()->delete();

            collect($validated['suppliers'] ?? [])
                ->unique('supplier_id')
                ->each(function (array $supplierRow) use ($part): void {
                    PartSupplierPrice::query()->create([
                        'part_id' => $part->id,
                        'supplier_id' => $supplierRow['supplier_id'],
                        'purchase_price' => $supplierRow['purchase_price'],
                    ]);
                });

            $part->stocks()->delete();

            collect($validated['stocks'] ?? [])
                ->filter(fn (array $stock): bool => (int) $stock['quantity'] > 0)
                ->unique('warehouse_id')
                ->each(function (array $stockRow) use ($part): void {
                    Stock::query()->create([
                        'part_id' => $part->id,
                        'warehouse_id' => $stockRow['warehouse_id'],
                        'quantity' => $stockRow['quantity'],
                    ]);
                });
        });

        return to_route('parts.index', $request->only('search', 'page'));
    }

    /**
     * Delete a part.
     */
    public function destroy(Request $request, Part $part): RedirectResponse
    {
        $part->delete();

        return to_route('parts.index', $request->only('search', 'page'));
    }

    /**
     * Show every part with its current total stock, editable inline and saved in bulk -
     * a physical stock-count (opname) worksheet rather than the per-warehouse ledger in stock().
     */
    public function stockOpname(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $category = $request->string('category')->toString();

        if (! in_array($category, [Part::CATEGORY_PURCHASE, Part::CATEGORY_MANUFACTURE], true)) {
            $category = '';
        }

        $parts = Part::query()
            ->with('defaultUom:id,code')
            ->withSum('stocks as total_stock', 'quantity')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('part_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('part_number')
            ->get();

        return Inertia::render('parts/StockOpname', [
            'parts' => $parts->map(fn (Part $part): array => [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'category' => $part->category,
                'inventory_type' => $part->inventory_type,
                'uom_code' => $part->defaultUom?->code,
                'quantity' => (int) ($part->total_stock ?? 0),
            ])->values(),
            'filters' => [
                'search' => $search,
                'category' => $category,
            ],
        ]);
    }

    /**
     * Save physical-count adjustments from the stock opname worksheet. Each part's total
     * is reconciled against a single resolved warehouse (its default, or wherever it
     * already carries stock) and the delta is logged as a stock_opname movement.
     */
    public function updateStockOpname(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'updates.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['updates'] as $update) {
                $part = Part::query()->findOrFail($update['part_id']);
                $warehouseId = $this->resolveOpnameWarehouseId($part);

                if ($warehouseId === null) {
                    continue;
                }

                $stock = Stock::query()->firstOrCreate(
                    ['part_id' => $part->id, 'warehouse_id' => $warehouseId],
                    ['quantity' => 0],
                );

                $newQuantity = (int) $update['quantity'];
                $delta = $newQuantity - (int) $stock->quantity;

                if ($delta === 0) {
                    continue;
                }

                $stock->update(['quantity' => $newQuantity]);

                StockMovement::query()->create([
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => 'stock_opname',
                    'quantity_change' => $delta,
                    'notes' => 'Penyesuaian stock opname',
                ]);
            }
        });

        return back()->with('success', 'Stock opname berhasil disimpan.');
    }

    /**
     * "Pemutihan" - zero out stock across all warehouses for parts matching the chosen
     * category filter (or every part if none is given), logging the write-off amount
     * for each affected stock row.
     */
    public function zeroStockOpname(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in([Part::CATEGORY_PURCHASE, Part::CATEGORY_MANUFACTURE])],
        ]);

        $category = $validated['category'] ?? null;

        DB::transaction(function () use ($category): void {
            Stock::query()
                ->where('quantity', '!=', 0)
                ->when($category !== null, fn ($query) => $query->whereHas('part', fn ($partQuery) => $partQuery->where('category', $category)))
                ->get()
                ->each(function (Stock $stock): void {
                    $oldQuantity = (int) $stock->quantity;
                    $stock->update(['quantity' => 0]);

                    StockMovement::query()->create([
                        'part_id' => $stock->part_id,
                        'warehouse_id' => $stock->warehouse_id,
                        'movement_type' => 'stock_opname_zero',
                        'quantity_change' => -$oldQuantity,
                        'notes' => 'Pemutihan stock opname',
                    ]);
                });
        });

        $scopeLabel = match ($category) {
            Part::CATEGORY_PURCHASE => 'part purchase',
            Part::CATEGORY_MANUFACTURE => 'part manufacture',
            default => 'semua part',
        };

        return back()->with('success', "Stock {$scopeLabel} berhasil dinolkan (pemutihan).");
    }

    /**
     * The single warehouse a part's opname total is reconciled against: its declared
     * default, else wherever it already carries the most stock, else the first
     * warehouse on file - there's normally just one, so this rarely has to guess.
     */
    private function resolveOpnameWarehouseId(Part $part): ?int
    {
        if ($part->default_warehouse_id !== null) {
            return $part->default_warehouse_id;
        }

        $existingStock = $part->stocks()->orderByDesc('quantity')->first();

        if ($existingStock !== null) {
            return $existingStock->warehouse_id;
        }

        return Warehouse::query()->orderBy('id')->value('id');
    }

    /**
     * Show stock by warehouse and total quantity.
     */
    public function stock(): Response
    {
        $stocks = Stock::query()
            ->with(['part:id,part_number,name,inventory_type', 'warehouse:id,code,name'])
            ->orderByDesc('quantity')
            ->get()
            ->map(function (Stock $stock): array {
                return [
                    'id' => $stock->id,
                    'part_number' => $stock->part?->part_number,
                    'part_name' => $stock->part?->name,
                    'inventory_type' => $stock->part?->inventory_type,
                    'warehouse_code' => $stock->warehouse?->code,
                    'warehouse_name' => $stock->warehouse?->name,
                    'quantity' => (int) $stock->quantity,
                ];
            });

        $stockSummary = Stock::query()
            ->join('parts', 'parts.id', '=', 'stocks.part_id')
            ->selectRaw('stocks.warehouse_id, parts.inventory_type, SUM(stocks.quantity) as total_quantity')
            ->groupBy('stocks.warehouse_id', 'parts.inventory_type')
            ->with('warehouse:id,code,name')
            ->get()
            ->map(function (Stock $stock): array {
                return [
                    'warehouse_code' => $stock->warehouse?->code,
                    'warehouse_name' => $stock->warehouse?->name,
                    'inventory_type' => $stock->inventory_type,
                    'total_quantity' => (int) $stock->total_quantity,
                ];
            });

        $history = StockMovement::query()
            ->with(['part:id,part_number,name,inventory_type', 'warehouse:id,code,name', 'workOrder:id,wo_number', 'toolLoan:id,borrower_name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (StockMovement $movement): array {
                return [
                    'id' => $movement->id,
                    'part_number' => $movement->part?->part_number,
                    'part_name' => $movement->part?->name,
                    'inventory_type' => $movement->part?->inventory_type,
                    'warehouse_code' => $movement->warehouse?->code,
                    'warehouse_name' => $movement->warehouse?->name,
                    'work_order_id' => $movement->workOrder?->id,
                    'wo_number' => $movement->workOrder?->wo_number,
                    'tool_loan_id' => $movement->tool_loan_id,
                    'borrower_name' => $movement->toolLoan?->borrower_name,
                    'movement_type' => $movement->movement_type,
                    'quantity_change' => (float) $movement->quantity_change,
                    'notes' => $movement->notes,
                    'created_at' => $movement->created_at->format('Y-m-d H:i'),
                ];
            });

        $activeToolLoans = ToolLoan::query()
            ->with(['part:id,part_number,name', 'warehouse:id,code,name'])
            ->where('status', ToolLoan::STATUS_BORROWED)
            ->latest('borrowed_at')
            ->get()
            ->map(function (ToolLoan $loan): array {
                $remainingQuantity = max(0, (int) $loan->borrowed_quantity - (int) $loan->returned_quantity);

                return [
                    'id' => $loan->id,
                    'part_id' => $loan->part_id,
                    'part_number' => $loan->part?->part_number,
                    'part_name' => $loan->part?->name,
                    'warehouse_id' => $loan->warehouse_id,
                    'warehouse_code' => $loan->warehouse?->code,
                    'warehouse_name' => $loan->warehouse?->name,
                    'borrower_name' => $loan->borrower_name,
                    'borrowed_quantity' => (int) $loan->borrowed_quantity,
                    'returned_quantity' => (int) $loan->returned_quantity,
                    'remaining_quantity' => $remainingQuantity,
                    'borrowed_at' => $loan->borrowed_at?->format('Y-m-d H:i'),
                    'due_at' => $loan->due_at?->format('Y-m-d H:i'),
                    'notes' => $loan->notes,
                ];
            });

        $toolParts = Part::query()
            ->where('inventory_type', Part::INVENTORY_TYPE_TOOL)
            ->orderBy('name')
            ->get(['id', 'part_number', 'name']);

        return Inertia::render('parts/Stock', [
            'stocks' => $stocks,
            'summary' => $stockSummary,
            'history' => $history,
            'active_tool_loans' => $activeToolLoans,
            'tool_parts' => $toolParts,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
