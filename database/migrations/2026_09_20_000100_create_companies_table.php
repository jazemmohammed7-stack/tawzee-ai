<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing identities need an approved company mapping, never an invented tenant.
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Existing users require an approved company assignment before these migrations.');
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('currency_code', 3)->default('YER');
            $table->string('timezone', 64)->default('Asia/Aden');
            $table->boolean('allow_negative_stock')->default(false);
            $table->string('status', 32)->default('pending_setup');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
