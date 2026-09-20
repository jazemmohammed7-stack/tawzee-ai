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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('type', 32);
            $table->bigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'type']);
        });

        // Standard CHECK syntax supported by both target engines, MySQL/MariaDB and PostgreSQL.
        DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_next_positive CHECK (next_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
