<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->nullable();
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign(['company_id', 'user_id'], 'activity_logs_actor_company_foreign')
                ->references(['company_id', 'id'])->on('users')->restrictOnDelete();
            $table->index(['company_id', 'action', 'created_at'], 'activity_logs_company_action_created_index');
            $table->index(['company_id', 'user_id', 'created_at'], 'activity_logs_company_actor_created_index');
            $table->index(['company_id', 'subject_type', 'subject_id'], 'activity_logs_company_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
