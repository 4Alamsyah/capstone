<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Part;
use App\Models\PartSupplierPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

function createPurchasablePart(string $partNumber, string $name, float $stockQuantity, ?Supplier $supplier = null, ?float $price = null): Part
{
    $part = Part::query()->create([
        'part_number' => $partNumber,
        'name' => $name,
        'category' => Part::CATEGORY_PURCHASE,
        'description' => null,
        'selling_price' => 0,
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-'.$partNumber,
        'name' => 'Warehouse '.$partNumber,
    ]);

    Stock::query()->create([
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $stockQuantity,
    ]);

    if ($supplier && $price !== null) {
        PartSupplierPrice::query()->create([
            'part_id' => $part->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => $price,
        ]);
    }

    return $part;
}

test('submitting a voucher with generate_po creates a PO only for the short parts', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $supplier = Supplier::query()->create(['name' => 'Supplier Auto PO Test']);

    $shortPart = createPurchasablePart('SH-001', 'Short Part', stockQuantity: 5, supplier: $supplier, price: 1000);
    $sufficientPart = createPurchasablePart('SF-001', 'Sufficient Part', stockQuantity: 100);

    $pv = PurchaseVoucher::query()->create([
        'pv_number' => 'PV-AUTO-00001',
        'status' => PurchaseVoucher::STATUS_DRAFT,
        'source' => PurchaseVoucher::SOURCE_MANUAL,
    ]);

    $shortItem = PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $shortPart->id,
        'quantity' => 20,
        'unit' => 'PCS',
        'stock_on_hand' => 5,
    ]);

    PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $sufficientPart->id,
        'quantity' => 10,
        'unit' => 'PCS',
        'stock_on_hand' => 100,
    ]);

    $response = $this->actingAs($user)->post(route('purchase.voucher.submit', $pv), [
        'generate_po' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('purchase_vouchers', [
        'id' => $pv->id,
        'status' => PurchaseVoucher::STATUS_SUBMITTED,
    ]);

    $po = PurchaseOrder::query()->where('supplier_id', $supplier->id)->first();

    expect($po)->not->toBeNull();
    expect($po->items)->toHaveCount(1);

    $poItem = $po->items->first();
    expect($poItem->part_id)->toBe($shortPart->id);
    expect((float) $poItem->quantity)->toBe(20.0);
    expect((float) $poItem->unit_price)->toBe(1000.0);
    expect((float) $poItem->line_total)->toBe(20000.0);
    expect($poItem->purchase_voucher_item_id)->toBe($shortItem->id);

    // The sufficient-stock item was never touched.
    $this->assertDatabaseMissing('purchase_order_items', [
        'part_id' => $sufficientPart->id,
    ]);
});

test('submitting a voucher without generate_po does not create any PO', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $supplier = Supplier::query()->create(['name' => 'Supplier No Auto PO']);
    $shortPart = createPurchasablePart('SH-002', 'Short Part 2', stockQuantity: 0, supplier: $supplier, price: 500);

    $pv = PurchaseVoucher::query()->create([
        'pv_number' => 'PV-AUTO-00002',
        'status' => PurchaseVoucher::STATUS_DRAFT,
        'source' => PurchaseVoucher::SOURCE_MANUAL,
    ]);

    PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $shortPart->id,
        'quantity' => 5,
        'unit' => 'PCS',
        'stock_on_hand' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('purchase.voucher.submit', $pv), [
        'generate_po' => false,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseCount('purchase_orders', 0);
});

test('auto-generated PO converts the voucher when every item is short', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $supplier = Supplier::query()->create(['name' => 'Supplier Full Convert']);
    $part = createPurchasablePart('SH-003', 'Fully Short Part', stockQuantity: 0, supplier: $supplier, price: 250);

    $pv = PurchaseVoucher::query()->create([
        'pv_number' => 'PV-AUTO-00003',
        'status' => PurchaseVoucher::STATUS_DRAFT,
        'source' => PurchaseVoucher::SOURCE_MANUAL,
    ]);

    PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $part->id,
        'quantity' => 8,
        'unit' => 'PCS',
        'stock_on_hand' => 0,
    ]);

    $this->actingAs($user)->post(route('purchase.voucher.submit', $pv), [
        'generate_po' => true,
    ]);

    $this->assertDatabaseHas('purchase_vouchers', [
        'id' => $pv->id,
        'status' => PurchaseVoucher::STATUS_CONVERTED,
    ]);
});

test('auto-generated PO copies quotation number, payment terms, and delivery date from the linked customer order', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $supplier = Supplier::query()->create(['name' => 'Supplier CO Linked']);
    $shortPart = createPurchasablePart('SH-005', 'CO Linked Part', stockQuantity: 0, supplier: $supplier, price: 750);

    $customer = Customer::query()->create(['name' => 'Customer CO Linked']);

    $co = CustomerOrder::query()->create([
        'co_number' => 'CO-AUTO-00001',
        'quotation_number' => 'QUO-AUTO-00001',
        'customer_id' => $customer->id,
        'status' => CustomerOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(14)->toDateString(),
        'payment_terms' => 'Net 30',
    ]);

    $pv = PurchaseVoucher::query()->create([
        'pv_number' => 'PV-AUTO-00005',
        'status' => PurchaseVoucher::STATUS_DRAFT,
        'source' => PurchaseVoucher::SOURCE_CO_CONFIRMATION,
        'customer_order_id' => $co->id,
    ]);

    PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $shortPart->id,
        'quantity' => 6,
        'unit' => 'PCS',
        'stock_on_hand' => 0,
    ]);

    $this->actingAs($user)->post(route('purchase.voucher.submit', $pv), [
        'generate_po' => true,
    ]);

    $po = PurchaseOrder::query()->where('supplier_id', $supplier->id)->first();

    expect($po)->not->toBeNull();
    expect($po->quo_no)->toBe('QUO-AUTO-00001');
    expect($po->term_payment)->toBe('Net 30');
    expect($po->expected_date->toDateString())->toBe($co->delivery_date->toDateString());
});

test('no PO is generated when the short part has no supplier price on file', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $part = createPurchasablePart('SH-004', 'Priceless Part', stockQuantity: 0);

    $pv = PurchaseVoucher::query()->create([
        'pv_number' => 'PV-AUTO-00004',
        'status' => PurchaseVoucher::STATUS_DRAFT,
        'source' => PurchaseVoucher::SOURCE_MANUAL,
    ]);

    PurchaseVoucherItem::query()->create([
        'purchase_voucher_id' => $pv->id,
        'part_id' => $part->id,
        'quantity' => 3,
        'unit' => 'PCS',
        'stock_on_hand' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('purchase.voucher.submit', $pv), [
        'generate_po' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseHas('purchase_vouchers', [
        'id' => $pv->id,
        'status' => PurchaseVoucher::STATUS_SUBMITTED,
    ]);
});
