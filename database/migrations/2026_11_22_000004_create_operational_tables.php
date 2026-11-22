<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('booking_reference', 32)->nullable()->index();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48)->index();
            $table->string('actor_type', 32)->default('system'); // user, driver, admin, system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('notes')->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::create('driver_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->double('lat');
            $table->double('lng');
            $table->double('heading')->default(0.0);
            $table->double('speed')->default(0.0);
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['driver_id', 'recorded_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name', 120)->default('System');
            $table->string('action', 64)->index();
            $table->string('entity_type', 64)->index();
            $table->string('entity_id', 64)->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('fares', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_type', 32)->unique();
            $table->decimal('base_fare', 10, 2)->default(250.00);
            $table->decimal('per_km_rate', 10, 2)->default(25.00);
            $table->double('base_distance_km')->default(5.0);
            $table->decimal('night_surcharge_multiplier', 4, 2)->default(1.25);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 8)->default('INR');
            $table->string('payment_method', 32)->default('free_government_108'); // upi, cash, card, free_government_108
            $table->string('status', 32)->default('completed'); // pending, completed, waived, failed
            $table->string('transaction_reference', 64)->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment')->nullable();
            $table->string('review_type', 48)->default('emergency_trip');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 32);
            $table->string('relationship', 64)->default('Family');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fares');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('driver_location_history');
        Schema::dropIfExists('booking_status_logs');
    }
};
