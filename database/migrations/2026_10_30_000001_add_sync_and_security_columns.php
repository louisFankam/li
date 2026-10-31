<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->unique()->after('sale_number');
            $table->foreignId('cancelled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
        });

        foreach (['products', 'customers', 'insurance_companies', 'insurance_rules', 'stock_levels'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('version')->default(0)->index();
            });
        }

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_event_id')->unique();
            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('payload');
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'status']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('details')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_events');
        Schema::dropIfExists('devices');

        foreach (['products', 'customers', 'insurance_companies', 'insurance_rules', 'stock_levels'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['client_request_id', 'cancelled_at', 'cancel_reason']);
        });
    }
};
