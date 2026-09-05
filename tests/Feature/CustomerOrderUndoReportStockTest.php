<?php

use App\Models\Bom;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Part;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderReport;
use App\Models\WorkOrderReportProduction;

test('undo report on a customer order reverses its manufacture order stock movements', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $customer = Customer::query()->create([
        'name' => 'PT Undo Report',
        'currency_code' => 'IDR',
    ]);

    $finishedPart = Part::query()->create([
        'part_number' => 'FIN-001',
        'name' => 'Finished Part',
        'selling_price' => 10000,
        'inventory_type' => 'material',
    ]);

    $componentPart = Part::query()->create([
        'part_number' => 'COMP-001',
        'name' => 'Component Part',
        'selling_price' => 1000,
        'inventory_type' => 'material',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-UNDO',
        'name' => 'Undo Warehouse',
    ]);

    $bom = Bom::query()->create([
        'part_id' => $finishedPart->id,
        'name' => 'BOM Finished Part',
        'is_active' => true,
        'planning_strategy' => Bom::PLANNING_STRATEGY_STOCK_DRIVEN,
    ]);

    $order = CustomerOrder::query()->create([
        'co_number' => 'CO-UNDO-00001',
        'customer_id' => $customer->id,
        'status' => CustomerOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'currency_code' => 'IDR',
        'subtotal' => 50000,
        'needs_mo_suggestion' => false,
        'notes' => null,
    ]);

    $workOrder = WorkOrder::query()->create([
        'wo_number' => 'MO-UNDO-00001',
        'bom_id' => $bom->id,
        'quantity' => 5,
        'status' => 'completed',
        'notes' => "Auto generated from {$order->co_number} for part #{$finishedPart->id}",
    ]);

    // Component stock left over after 20 units were consumed by the report below.
    $componentStock = Stock::query()->create([
        'part_id' => $componentPart->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 30,
    ]);

    // Finished stock produced by the report below.
    $finishedStock = Stock::query()->create([
        'part_id' => $finishedPart->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
    ]);

    $report = WorkOrderReport::query()->create([
        'work_order_id' => $workOrder->id,
        'reported_by' => $user->id,
        'previous_status' => 'draft',
        'new_status' => 'completed',
        'good_quantity' => 5,
        'reject_quantity' => 0,
    ]);

    WorkOrderReportProduction::query()->create([
        'work_order_report_id' => $report->id,
        'work_order_id' => $workOrder->id,
        'bom_item_id' => null,
        'part_id' => $finishedPart->id,
        'bom_id' => $bom->id,
        'warehouse_id' => $warehouse->id,
        'good_quantity' => 5,
        'reject_quantity' => 0,
    ]);

    StockMovement::query()->create([
        'part_id' => $finishedPart->id,
        'warehouse_id' => $warehouse->id,
        'work_order_id' => $workOrder->id,
        'work_order_report_id' => $report->id,
        'movement_type' => 'produce',
        'quantity_change' => 5,
        'notes' => 'Produced by '.$workOrder->wo_number,
    ]);

    StockMovement::query()->create([
        'part_id' => $componentPart->id,
        'warehouse_id' => $warehouse->id,
        'work_order_id' => $workOrder->id,
        'work_order_report_id' => $report->id,
        'movement_type' => 'consume',
        'quantity_change' => -20,
        'notes' => 'Consumed by '.$workOrder->wo_number,
    ]);

    $this->actingAs($user)
        ->post("/sales/customer-orders/{$order->id}/undo-report")
        ->assertRedirect('/sales/customer-orders');

    $order->refresh();
    $workOrder->refresh();
    $componentStock->refresh();
    $finishedStock->refresh();

    expect($order->status)->toBe(CustomerOrder::STATUS_REGISTERED);
    expect($workOrder->status)->toBe('draft');
    expect((float) $componentStock->quantity)->toBe(50.0);
    expect((float) $finishedStock->quantity)->toBe(0.0);
    expect(WorkOrderReport::query()->where('work_order_id', $workOrder->id)->count())->toBe(0);
    expect(WorkOrderReportProduction::query()->where('work_order_id', $workOrder->id)->count())->toBe(0);
    expect(StockMovement::query()->where('work_order_id', $workOrder->id)->count())->toBe(0);
});

test('undo report is blocked when reversing would push stock below zero', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $customer = Customer::query()->create([
        'name' => 'PT Undo Blocked',
        'currency_code' => 'IDR',
    ]);

    $finishedPart = Part::query()->create([
        'part_number' => 'FIN-002',
        'name' => 'Finished Part 2',
        'selling_price' => 10000,
        'inventory_type' => 'material',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-BLOCK',
        'name' => 'Blocked Warehouse',
    ]);

    $bom = Bom::query()->create([
        'part_id' => $finishedPart->id,
        'name' => 'BOM Finished Part 2',
        'is_active' => true,
        'planning_strategy' => Bom::PLANNING_STRATEGY_STOCK_DRIVEN,
    ]);

    $order = CustomerOrder::query()->create([
        'co_number' => 'CO-UNDO-00002',
        'customer_id' => $customer->id,
        'status' => CustomerOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'currency_code' => 'IDR',
        'subtotal' => 50000,
        'needs_mo_suggestion' => false,
        'notes' => null,
    ]);

    $workOrder = WorkOrder::query()->create([
        'wo_number' => 'MO-UNDO-00002',
        'bom_id' => $bom->id,
        'quantity' => 5,
        'status' => 'completed',
        'notes' => "Auto generated from {$order->co_number} for part #{$finishedPart->id}",
    ]);

    // All 5 produced units have already shipped out - only 1 left in stock.
    $finishedStock = Stock::query()->create([
        'part_id' => $finishedPart->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    $report = WorkOrderReport::query()->create([
        'work_order_id' => $workOrder->id,
        'reported_by' => $user->id,
        'previous_status' => 'draft',
        'new_status' => 'completed',
        'good_quantity' => 5,
        'reject_quantity' => 0,
    ]);

    StockMovement::query()->create([
        'part_id' => $finishedPart->id,
        'warehouse_id' => $warehouse->id,
        'work_order_id' => $workOrder->id,
        'work_order_report_id' => $report->id,
        'movement_type' => 'produce',
        'quantity_change' => 5,
        'notes' => 'Produced by '.$workOrder->wo_number,
    ]);

    $this->actingAs($user)
        ->post("/sales/customer-orders/{$order->id}/undo-report")
        ->assertRedirect('/sales/customer-orders');

    $order->refresh();
    $finishedStock->refresh();

    expect($order->status)->toBe(CustomerOrder::STATUS_CONFIRMED);
    expect((float) $finishedStock->quantity)->toBe(1.0);
    expect(WorkOrderReport::query()->where('work_order_id', $workOrder->id)->count())->toBe(1);
});
