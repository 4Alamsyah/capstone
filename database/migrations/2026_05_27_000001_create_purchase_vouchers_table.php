<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('pv_number', 50)->unique();
            $table->unsignedTinyInteger('status')->default(1); // 1=Draft,2=Submitted,3=Approved,4=Converted,8=Rejected,9=Cancelled
            $table->string('source', 20)->default('manual'); // 'manual','co_confirmation','stock_recommendation'
            $table->foreignId('customer_order_id')->nullable()->constrained('customer_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->date('required_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_vouchers');
    }
};
