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
                create table "transfers" (
                    "id" integer primary key autoincrement not null,
                    "number" varchar not null,
                    "transfer_date" date not null,
                    "from_warehouse_id" integer not null,
                    "to_warehouse_id" integer not null,
                    "status" varchar not null default 'draft' check ("status" in ('draft', 'posted')),
                    "created_at" datetime,
                    "updated_at" datetime,
                    constraint "transfers_number_unique" unique ("number"),
                    constraint "transfers_from_warehouse_id_foreign"
                        foreign key ("from_warehouse_id") references "warehouses" ("id") on delete restrict,
                    constraint "transfers_to_warehouse_id_foreign"
                        foreign key ("to_warehouse_id") references "warehouses" ("id") on delete restrict,
                    constraint "transfers_warehouses_different"
                        check ("from_warehouse_id" <> "to_warehouse_id")
                )
                SQL);

            return;
        }

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('transfer_date');
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamps();
        });

        DB::statement(
            'alter table transfers add constraint transfers_warehouses_different '
            .'check (from_warehouse_id <> to_warehouse_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
