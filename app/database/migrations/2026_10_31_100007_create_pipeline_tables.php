<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->unsignedInteger('stale_after_days')->nullable();
            $table->string('kind')->default('open'); // open|won|lost
            $table->timestamps();

            $table->index(['pipeline_id', 'position']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('expected_close')->nullable();
            $table->timestamp('stage_entered_at')->useCurrent();
            $table->string('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('pipelines');
    }
};
