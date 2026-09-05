<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrder\StoreCustomerOrderRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Currency;
use App\Models\Part;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $orderFrom = $request->string('order_from')->toString();
        $orderTo = $request->string('order_to')->toString();

        $quotes = CustomerOrder::query()
            ->with(['customer', 'items.part'])
            ->where('status', CustomerOrder::STATUS_QUOTATION)
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('co_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.part', function ($partQuery) use ($search): void {
                            $partQuery->where('part_number', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($orderFrom !== '', function ($builder) use ($orderFrom): void {
                $builder->whereDate('order_date', '>=', $orderFrom);
            })
            ->when($orderTo !== '', function ($builder) use ($orderTo): void {
                $builder->whereDate('order_date', '<=', $orderTo);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('sales/quotations/Index', [
            'quotations' => collect($quotes->items())->map(fn (CustomerOrder $quote): array => [
                'id' => $quote->id,
                'quotation_number' => $quote->co_number,
                'order_date' => $quote->order_date?->format('Y-m-d'),
                'delivery_date' => $quote->delivery_date?->format('Y-m-d'),
                'currency_code' => $quote->currency_code,
                'subtotal' => (string) $quote->subtotal,
                'customer' => [
                    'id' => $quote->customer?->id,
                    'name' => $quote->customer?->name,
                ],
                'items' => $quote->items->map(fn (CustomerOrderItem $item): array => [
                    'id' => $item->id,
                    'part_number' => $item->part?->part_number,
                    'part_name' => $item->part?->name,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
            ])->values(),
            'filters' => [
                'search' => $search,
                'order_from' => $orderFrom,
                'order_to' => $orderTo,
            ],
            'pagination' => [
                'current_page' => $quotes->currentPage(),
                'last_page' => $quotes->lastPage(),
                'per_page' => $quotes->perPage(),
                'total' => $quotes->total(),
                'from' => $quotes->firstItem(),
                'to' => $quotes->lastItem(),
                'prev_page_url' => $quotes->previousPageUrl(),
                'next_page_url' => $quotes->nextPageUrl(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sales/quotations/Create', $this->buildFormPayload([
            'nextQuotationNumber' => CustomerOrder::generateQuotationNumber(),
        ]));
    }

    public function edit(CustomerOrder $quotation): Response
    {
        if ($quotation->status !== CustomerOrder::STATUS_QUOTATION) {
            abort(404);
        }

        $quotation->loadMissing(['items.part']);

        return Inertia::render('sales/quotations/Edit', $this->buildFormPayload([
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->co_number,
                'customer_id' => $quotation->customer_id,
                'order_date' => $quotation->order_date?->format('Y-m-d'),
                'delivery_date' => $quotation->delivery_date?->format('Y-m-d'),
                'shipping_address' => $quotation->shipping_address,
                'payment_terms' => $quotation->payment_terms,
                'currency_code' => $quotation->currency_code,
                'notes' => $quotation->notes,
                'lines' => $quotation->items->map(fn (CustomerOrderItem $item): array => [
                    'part_id' => $item->part_id,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                ])->values(),
            ],
        ]));
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $partIds = collect($validated['lines'])->pluck('part_id')->all();

            $parts = Part::query()
                ->whereIn('id', $partIds)
                ->withSum('stocks as total_stock', 'quantity')
                ->get(['id'])
                ->keyBy('id');

            $quotation = CustomerOrder::query()->create([
                'co_number' => CustomerOrder::generateQuotationNumber(),
                'customer_id' => $validated['customer_id'],
                'status' => CustomerOrder::STATUS_QUOTATION,
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'subtotal' => 0,
                'needs_mo_suggestion' => false,
                'notes' => $validated['notes'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach ($validated['lines'] as $line) {
                $part = $parts->get($line['part_id']);

                if (! $part instanceof Part) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                CustomerOrderItem::query()->create([
                    'customer_order_id' => $quotation->id,
                    'part_id' => $part->id,
                    'quantity' => $quantity,
                    'unit' => strtoupper((string) ($line['unit'] ?? 'PCS')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'stock_on_hand' => (int) ($part->total_stock ?? 0),
                    'requires_mo' => false,
                ]);
            }

            $quotation->update([
                'subtotal' => $subtotal,
            ]);
        });

        return to_route('sales.quotations.index');
    }

    public function update(StoreCustomerOrderRequest $request, CustomerOrder $quotation): RedirectResponse
    {
        if ($quotation->status !== CustomerOrder::STATUS_QUOTATION) {
            return to_route('sales.quotations.index')->with('error', 'Data bukan quotation.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $quotation): void {
            $partIds = collect($validated['lines'])->pluck('part_id')->all();

            $parts = Part::query()
                ->whereIn('id', $partIds)
                ->withSum('stocks as total_stock', 'quantity')
                ->get(['id'])
                ->keyBy('id');

            $quotation->update([
                'customer_id' => $validated['customer_id'],
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'currency_code' => strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR'))),
                'notes' => $validated['notes'] ?? null,
            ]);

            $quotation->items()->delete();

            $subtotal = 0.0;

            foreach ($validated['lines'] as $line) {
                $part = $parts->get($line['part_id']);

                if (! $part instanceof Part) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                CustomerOrderItem::query()->create([
                    'customer_order_id' => $quotation->id,
                    'part_id' => $part->id,
                    'quantity' => $quantity,
                    'unit' => strtoupper((string) ($line['unit'] ?? 'PCS')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'stock_on_hand' => (int) ($part->total_stock ?? 0),
                    'requires_mo' => false,
                ]);
            }

            $quotation->update([
                'subtotal' => $subtotal,
            ]);
        });

        return to_route('sales.quotations.index')->with('success', 'Quotation berhasil diupdate.');
    }

    public function destroy(CustomerOrder $quotation): RedirectResponse
    {
        if ($quotation->status !== CustomerOrder::STATUS_QUOTATION) {
            return to_route('sales.quotations.index')->with('error', 'Data bukan quotation.');
        }

        $quotation->delete();

        return to_route('sales.quotations.index')->with('success', 'Quotation berhasil dihapus.');
    }

    public function document(CustomerOrder $quotation): \Symfony\Component\HttpFoundation\Response
    {
        if ($quotation->status !== CustomerOrder::STATUS_QUOTATION) {
            abort(404);
        }

        $quotation->loadMissing([
            'customer:id,name,address,shipping_address,payment_terms',
            'items.part:id,part_number,name',
        ]);

        $pdf = Pdf::loadView('documents.quotation', [
            'quotation' => $quotation,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Quotation-' . $quotation->co_number . '.pdf');
    }

    public function generateCustomerOrder(CustomerOrder $quotation): RedirectResponse
    {
        if ($quotation->status !== CustomerOrder::STATUS_QUOTATION) {
            return to_route('sales.quotations.index')->with('error', 'Data bukan quotation atau sudah digenerate.');
        }

        $quotationNumber = $quotation->co_number;
        $newCustomerOrderNumber = null;

        DB::transaction(function () use ($quotation, $quotationNumber, &$newCustomerOrderNumber): void {
            $newCustomerOrderNumber = CustomerOrder::generateNumber();

            $notes = trim((string) ($quotation->notes ?? ''));
            $generatedNote = 'Generated from quotation '.$quotationNumber;

            $quotation->update([
                'co_number' => $newCustomerOrderNumber,
                'quotation_number' => $quotationNumber,
                'status' => CustomerOrder::STATUS_REGISTERED,
                'project_code' => $quotation->project_code ?? CustomerOrder::generateProjectCode(),
                'notes' => $notes === '' ? $generatedNote : $notes.PHP_EOL.$generatedNote,
            ]);
        });

        return to_route('sales.customer-orders.index')->with(
            'success',
            'Quotation '.$quotationNumber.' berhasil digenerate menjadi CO '.$newCustomerOrderNumber.'.',
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function buildFormPayload(array $extra = []): array
    {
        return array_merge([
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'paymentTermsOptions' => collect(json_decode((string) AppSetting::get('payment_terms_options', '[]'), true))
                ->filter(fn ($term): bool => is_string($term) && trim($term) !== '')
                ->map(fn ($term): string => trim((string) $term))
                ->unique(fn (string $term): string => mb_strtolower($term))
                ->values(),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'shipping_address', 'payment_terms', 'currency_code'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'shipping_address' => $customer->shipping_address,
                    'payment_terms' => $customer->payment_terms,
                    'currency_code' => $customer->currency_code,
                ]),
            'parts' => Part::query()
                ->withSum('stocks as total_stock', 'quantity')
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'category', 'selling_price'])
                ->map(fn (Part $part): array => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'name' => $part->name,
                    'category' => $part->category,
                    'selling_price' => (float) $part->selling_price,
                    'stock_on_hand' => (int) ($part->total_stock ?? 0),
                ]),
        ], $extra);
    }
}
