<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status', 24)->default('ACTIVE');
            });
        }

        if (!Schema::hasTable('admin_alert_states')) {
            Schema::create('admin_alert_states', function (Blueprint $table) {
                $table->id();
                $table->string('alert_key', 64)->unique();
                $table->boolean('is_acknowledged')->default(false);
                $table->boolean('is_resolved')->default(false);
                $table->string('updated_by', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alert_states');
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
