<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Part;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

test('customer order list reflects current stock availability without requiring an edit', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $customer = Customer::query()->create([
        'name' => 'PT Availability',
        'currency_code' => 'IDR',
    ]);

    $part = Part::query()->create([
        'part_number' => 'AVAIL-001',
        'name' => 'Availability Part',
        'selling_price' => 10000,
        'inventory_type' => 'material',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-AVAIL',
        'name' => 'Availability Warehouse',
    ]);

    $stock = Stock::query()->create([
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    $order = CustomerOrder::query()->create([
        'co_number' => 'CO-AVAIL-00001',
        'customer_id' => $customer->id,
        'status' => CustomerOrder::STATUS_REGISTERED,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'currency_code' => 'IDR',
        'subtotal' => 50000,
        'needs_mo_suggestion' => false,
        'notes' => null,
    ]);

    CustomerOrderItem::query()->create([
        'customer_order_id' => $order->id,
        'part_id' => $part->id,
        'quantity' => 5,
        'unit' => 'PCS',
        'unit_price' => 10000,
        'line_total' => 50000,
        'stock_on_hand' => 10,
        'requires_mo' => false,
    ]);

    $stock->update(['quantity' => 2]);

    $this->actingAs($user)
        ->get(route('sales.customer-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders', 1)
            ->where('orders.0.co_number', 'CO-AVAIL-00001')
            ->where('orders.0.needs_mo_suggestion', true)
        );

    $order->refresh();
    expect($order->needs_mo_suggestion)->toBeFalse();
});
