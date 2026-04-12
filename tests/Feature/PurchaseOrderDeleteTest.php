<?php

use App\Models\PurchaseArrival;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;

test('admin can delete a pending purchase order', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier Delete Test',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-DEL-00001',
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        'order_date' => now()->toDateString(),
        'expected_date' => null,
        'currency_code' => 'IDR',
        'subtotal' => 0,
    ]);

    $response = $this->actingAs($user)->delete(route('purchase.po.destroy', ['purchaseOrder' => $purchaseOrder->id]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'PO berhasil dihapus.');
    $this->assertDatabaseMissing('purchase_orders', ['id' => $purchaseOrder->id]);
});

test('purchase order with arrival cannot be deleted', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier Arrival Test',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-DEL-00002',
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_APPROVED,
        'order_date' => now()->toDateString(),
        'expected_date' => null,
        'currency_code' => 'IDR',
        'subtotal' => 0,
    ]);

    PurchaseArrival::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'reported_by' => $user->id,
        'arrival_date' => now()->toDateString(),
        'notes' => null,
    ]);

    $response = $this->actingAs($user)->delete(route('purchase.po.destroy', ['purchaseOrder' => $purchaseOrder->id]));

    $response->assertSessionHasErrors('purchase_order');
    $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id]);
});
