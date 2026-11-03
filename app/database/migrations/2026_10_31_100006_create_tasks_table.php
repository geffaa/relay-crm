<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type')->nullable(); // Contact|Deal|Conversation
            $table->foreignId('subject_id')->nullable();
            $table->string('title');
            $table->string('kind')->default('manual'); // manual|unreplied|stale_deal|close_date
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'assignee_id', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
