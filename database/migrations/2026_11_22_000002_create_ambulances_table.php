<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulances', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 32)->unique();
            $table->string('vehicle_type', 32)->default('als'); // als, bls, transport, neonatal
            $table->string('vehicle_type_display', 100)->default('Advanced Life Support (ALS)');
            $table->string('model', 120)->default('Force Traveller ICU Ambulance');
            $table->unsignedSmallInteger('year')->default(2024);
            $table->string('status', 32)->default('available')->index(); // available, busy, maintenance, offline
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('hospital_affiliation', 150)->default('108 Emergency Ambulance Network');
            $table->json('equipment_list')->nullable();
            $table->double('lat')->default(11.0210);
            $table->double('lng')->default(76.9380);
            $table->double('heading')->default(0.0);
            $table->double('speed')->default(0.0);
            $table->string('fuel_level', 16)->default('85%');
            $table->string('oxygen_level', 16)->default('100%');
            $table->timestamp('last_maintenance_date')->nullable();
            $table->timestamp('last_telemetry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulances');
    }
};
