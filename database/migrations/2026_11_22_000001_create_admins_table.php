<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->string('role', 48)->default('super_admin');
            $table->string('role_name', 100)->default('Super Administrator');
            $table->string('badge_number', 48)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_secret', 100)->nullable();
            $table->string('api_token', 128)->nullable()->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
