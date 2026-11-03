<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type'); // Contact|Deal
            $table->foreignId('subject_id');
            $table->string('type'); // note|message|stage_change|task|ai_summary|assignment|score_override|lead_conversion
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('body')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
