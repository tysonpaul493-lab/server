<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('address');
            $table->double('lat');
            $table->double('lng');
            $table->string('emergency_phone', 32)->default('108');
            $table->json('emergency_services')->nullable();
            $table->string('trauma_level', 64)->default('Level 1 Trauma Care');
            $table->boolean('has_trauma_center')->default(true);
            $table->boolean('has_icu_bed')->default(true);
            $table->unsignedSmallInteger('total_icu_beds')->default(50);
            $table->unsignedSmallInteger('available_icu_beds')->default(12);
            $table->unsignedSmallInteger('total_general_beds')->default(200);
            $table->unsignedSmallInteger('available_general_beds')->default(45);
            $table->boolean('has_cardiac_care')->default(true);
            $table->boolean('has_stroke_unit')->default(true);
            $table->boolean('has_blood_bank')->default(true);
            $table->string('operating_hours', 64)->default('24x7 Emergency');
            $table->boolean('verified')->default(true);
            $table->timestamps();

            $table->index(['lat', 'lng']);
        });

        Schema::create('hospital_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('facility_name', 100);
            $table->string('facility_code', 48)->nullable();
            $table->string('capacity_status', 32)->default('available'); // available, full, maintenance
            $table->unsignedSmallInteger('available_units')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_facilities');
        Schema::dropIfExists('hospitals');
    }
};
