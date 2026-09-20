<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['company_id', 'id'], 'users_company_id_id_unique');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('founder_user_id')->nullable();
            $table->foreign(['id', 'founder_user_id'], 'companies_founder_same_company_foreign')
                ->references(['company_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign('companies_founder_same_company_foreign');
            $table->dropColumn('founder_user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_company_id_id_unique');
        });
    }
};
