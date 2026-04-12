<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('shipping_address', 'like', "%{$search}%")
                        ->orWhere('payment_terms', 'like', "%{$search}%")
                        ->orWhere('currency_code', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('sales/customers/Index', [
            'customers' => collect($customers->items())->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'shipping_address' => $customer->shipping_address,
                'payment_terms' => $customer->payment_terms,
                'currency_code' => $customer->currency_code,
                'created_at' => (string) $customer->created_at,
            ])->values(),
            'filters' => [
                'search' => $search,
            ],
            'defaultCurrency' => (string) AppSetting::get('default_currency_code', 'IDR'),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
            'paymentTermsOptions' => collect(json_decode((string) AppSetting::get('payment_terms_options', '[]'), true))
                ->filter(fn ($term): bool => is_string($term) && trim($term) !== '')
                ->map(fn ($term): string => trim((string) $term))
                ->unique(fn (string $term): string => mb_strtolower($term))
                ->values(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
                'prev_page_url' => $customers->previousPageUrl(),
                'next_page_url' => $customers->nextPageUrl(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['currency_code'] = strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR')));

        Customer::query()->create($validated);

        return to_route('sales.customers.index');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();

        $validated['currency_code'] = strtoupper((string) ($validated['currency_code'] ?? AppSetting::get('default_currency_code', 'IDR')));

        $customer->update($validated);

        return to_route('sales.customers.index');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return to_route('sales.customers.index');
    }
}
