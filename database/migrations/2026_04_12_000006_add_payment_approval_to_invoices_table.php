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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedTinyInteger('payment_approval_status')->default(0)->after('status')->index();
            $table->foreignId('payment_requested_by')->nullable()->after('payment_approval_status')->constrained('users')->nullOnDelete();
            $table->dateTime('payment_requested_at')->nullable()->after('payment_requested_by');
            $table->foreignId('payment_approved_by')->nullable()->after('payment_requested_at')->constrained('users')->nullOnDelete();
            $table->dateTime('payment_approved_at')->nullable()->after('payment_approved_by');
            $table->foreignId('payment_rejected_by')->nullable()->after('payment_approved_at')->constrained('users')->nullOnDelete();
            $table->dateTime('payment_rejected_at')->nullable()->after('payment_rejected_by');
            $table->text('payment_approval_notes')->nullable()->after('notes');
            $table->dateTime('paid_at')->nullable()->after('payment_approval_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_requested_by');
            $table->dropConstrainedForeignId('payment_approved_by');
            $table->dropConstrainedForeignId('payment_rejected_by');
            $table->dropColumn('payment_requested_at');
            $table->dropColumn('payment_approved_at');
            $table->dropColumn('payment_rejected_at');
            $table->dropColumn('payment_approval_status');
            $table->dropColumn('payment_approval_notes');
            $table->dropColumn('paid_at');
        });
    }
};
