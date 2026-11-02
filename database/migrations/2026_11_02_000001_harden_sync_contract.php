<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->string('payload_hash', 64)->nullable()->after('payload');
        });

        Schema::create('sync_changes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 10);
            $table->json('payload');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['pharmacy_id', 'id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_changes');

        Schema::table('sync_events', function (Blueprint $table) {
            $table->dropColumn('payload_hash');
        });
    }
};
