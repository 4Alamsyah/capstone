<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Part;
use App\Models\User;

test('quotation can be generated into customer order from quotation list action', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $customer = Customer::query()->create([
        'name' => 'PT Generate CO',
        'currency_code' => 'IDR',
    ]);

    $part = Part::query()->create([
        'part_number' => 'GEN-CO-001',
        'name' => 'Generated CO Part',
        'selling_price' => 150000,
        'inventory_type' => 'material',
    ]);

    $quotation = CustomerOrder::query()->create([
        'co_number' => 'QT-202604-00001',
        'customer_id' => $customer->id,
        'status' => CustomerOrder::STATUS_QUOTATION,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'currency_code' => 'IDR',
        'subtotal' => 300000,
        'needs_mo_suggestion' => false,
        'notes' => 'Initial quotation note',
    ]);

    CustomerOrderItem::query()->create([
        'customer_order_id' => $quotation->id,
        'part_id' => $part->id,
        'quantity' => 2,
        'unit' => 'PCS',
        'unit_price' => 150000,
        'line_total' => 300000,
        'stock_on_hand' => 0,
        'requires_mo' => false,
    ]);

    $response = $this->actingAs($user)
        ->post(route('sales.quotations.generate-customer-order', ['quotation' => $quotation->id]));

    $response->assertRedirect(route('sales.customer-orders.index'));

    $quotation->refresh();

    expect($quotation->status)->toBe(CustomerOrder::STATUS_REGISTERED);
    expect($quotation->co_number)->not->toBe('QT-202604-00001');
    expect($quotation->co_number)->toStartWith('CO-');
    expect((string) $quotation->notes)->toContain('Generated from quotation QT-202604-00001');
    expect($quotation->project_code)->not->toBeNull();
});
