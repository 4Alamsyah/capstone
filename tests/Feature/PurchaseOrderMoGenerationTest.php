<?php

use App\Models\Bom;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createPartWithBom(string $partNumber, string $partName): array
{
    $part = Part::query()->create([
        'part_number' => $partNumber,
        'name' => $partName,
        'description' => null,
        'selling_price' => 0,
    ]);

    $bom = Bom::query()->create([
        'part_id' => $part->id,
        'name' => $partName.' BOM',
        'description' => null,
        'is_active' => true,
    ]);

    return [$part, $bom];
}

test('approved purchase order automatically creates linked work order', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier MO Auto Test',
    ]);

    [$part, $bom] = createPartWithBom('FG-001', 'Finished Good');

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-MO-00001',
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        'order_date' => now()->toDateString(),
        'expected_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'IDR',
        'subtotal' => 0,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'part_id' => $part->id,
        'quantity' => 12,
        'unit' => 'PCS',
        'unit_price' => 0,
        'line_total' => 0,
        'received_quantity' => 0,
        'remarks' => null,
    ]);

    $response = $this->actingAs($user)->post(route('purchase.po.approve', $purchaseOrder), [
        'approval_notes' => 'approved for testing',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrder->id,
        'status' => PurchaseOrder::STATUS_APPROVED,
    ]);

    $workOrder = \App\Models\WorkOrder::query()->where('purchase_order_id', $purchaseOrder->id)->first();

    expect($workOrder)->not->toBeNull();
    expect($workOrder?->bom_id)->toBe($bom->id);
    expect((float) $workOrder?->quantity)->toBe(12.0);

    $this->get(route('purchase.po.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchase/po/Index')
            ->has('purchaseOrders', 1)
            ->where('purchaseOrders.0.po_number', 'PO-MO-00001')
            ->where('purchaseOrders.0.projects.0', $workOrder?->wo_number)
        );
});

test('work order detail shows no source po for manual work order', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    [$part, $bom] = createPartWithBom('FG-002', 'Manual Finished Good');

    $workOrder = \App\Models\WorkOrder::query()->create([
        'wo_number' => 'WO-MANUAL-00001',
        'bom_id' => $bom->id,
        'purchase_order_id' => null,
        'quantity' => 3,
        'status' => 'draft',
        'scheduled_date' => null,
        'notes' => 'manual mo',
    ]);

    $this->actingAs($user)
        ->get(route('work-orders.show', $workOrder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/Show')
            ->where('workOrder.purchase_order.po_number', null)
        );
});
