<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                create table "transfer_items" (
                    "id" integer primary key autoincrement not null,
                    "transfer_id" integer not null,
                    "product_id" integer not null,
                    "quantity" decimal(15, 3) not null,
                    "created_at" datetime,
                    "updated_at" datetime,
                    constraint "transfer_items_transfer_product_unique" unique ("transfer_id", "product_id"),
                    constraint "transfer_items_transfer_id_foreign"
                        foreign key ("transfer_id") references "transfers" ("id") on delete restrict,
                    constraint "transfer_items_product_id_foreign"
                        foreign key ("product_id") references "products" ("id") on delete restrict,
                    constraint "transfer_items_quantity_positive" check ("quantity" > 0)
                )
                SQL);

            return;
        }

        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->unique(['transfer_id', 'product_id']);
        });

        DB::statement(
            'alter table transfer_items add constraint transfer_items_quantity_positive check (quantity > 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
