<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'device_id')) {
            Schema::table('customers', fn (Blueprint $table) => $table->text('device_id')->nullable());
        }

        $locations = [
            'regions' => ['napa_region_id', []],
            'districts' => ['napa_district_id', ['region_id']],
            'wards' => ['napa_ward_id', ['region_id', 'district_id']],
            'streets' => ['napa_street_id', ['region_id', 'district_id', 'ward_id']],
            'street_roads' => ['napa_street_road_id', ['street_id']],
        ];

        foreach ($locations as $name => [$externalId, $parents]) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) use ($externalId, $parents) {
                    $table->id();
                    $table->string($externalId)->unique();
                    $table->string('name');
                    $table->string('postcode')->nullable();
                    foreach ($parents as $parent) {
                        $table->foreignId($parent)->constrained();
                    }
                });
            }
        }

        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                foreach (['region_id', 'district_id', 'ward_id', 'street_id', 'street_road_id'] as $parent) {
                    $table->foreignId($parent)->constrained();
                }
                $table->string('contact_name');
                $table->string('phone_number');
                foreach (['optional_phone_number', 'number', 'code', 'trimedCode', 'postcode'] as $field) {
                    $table->string($field)->nullable();
                }
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('isDefault')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_id')) {
            Schema::table('orders', fn (Blueprint $table) => $table->foreignId('shipping_id')->nullable()->constrained('addresses')->nullOnDelete());
        }

        Schema::table('payments', fn (Blueprint $table) => $table->text('redirect_url')->nullable());
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('redirect_url'));
        // Location tables and fields may predate this migration and contain delivery records.
    }
};
