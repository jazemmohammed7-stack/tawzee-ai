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
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Assign existing users to companies through an approved data migration first.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->index()->constrained('companies')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn(['company_id', 'is_active']);
        });
    }
};
