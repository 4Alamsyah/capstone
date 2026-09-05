<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('supplier_id')->constrained('users')->nullOnDelete();
            $table->string('quo_no', 50)->nullable()->after('po_number');
            $table->string('term_payment', 100)->nullable()->after('currency_code');
            $table->string('department', 100)->nullable()->after('term_payment');
            $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['quo_no', 'term_payment', 'department', 'discount', 'tax_amount']);
        });
    }
};
