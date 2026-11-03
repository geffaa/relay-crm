<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->jsonb('points'); // [{text, message_ids[]}]
            $table->text('next_step')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('model');
            $table->string('prompt_version', 12);
            $table->foreignId('covers_until_message_id')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('lead_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->jsonb('reasons'); // [{label, signal, weight}]
            $table->unsignedInteger('structured_part');
            $table->integer('ai_adjustment'); // -15..+30
            $table->string('model')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contact_id', 'created_at']);
        });

        Schema::create('score_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('from')->nullable();
            $table->unsignedInteger('to');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // summarize|draft_reply|score
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->string('status')->default('ok'); // ok|error
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('score_overrides');
        Schema::dropIfExists('lead_scores');
        Schema::dropIfExists('ai_summaries');
    }
};
