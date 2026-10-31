<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('discount_reason')->nullable()->after('discount_percent');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable()->after('discount_approved_by');
        });

        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->bigInteger('reimbursed_amount')->default(0)->after('insurer_part');
            $table->timestamp('reimbursed_at')->nullable()->after('reimbursed_amount');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('status');
            $table->string('payment_status')->default('non_paye')->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'payment_status']);
        });

        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropColumn(['reimbursed_amount', 'reimbursed_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn(['discount_reason', 'discount_approved_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
