<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number_id');
            $table->string('display_phone');
            $table->string('waba_id')->nullable();
            $table->text('access_token')->nullable(); // encrypted cast
            $table->string('mode')->default('simulated'); // live|simulated
            $table->timestamps();

            $table->unique(['company_id', 'phone_number_id']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_number_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open|pending|closed
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['contact_id', 'whatsapp_number_id']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('wamid')->unique()->nullable();
            $table->string('direction'); // in|out
            $table->string('type')->default('text'); // text|image|document|template|note|system
            $table->text('body')->nullable();
            $table->jsonb('media')->nullable();
            $table->string('status')->default('queued'); // queued|sent|delivered|read|failed
            $table->jsonb('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_number_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language', 8)->default('en_US');
            $table->string('category')->default('UTILITY');
            $table->text('body');
            $table->jsonb('variables')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();

            $table->unique(['whatsapp_number_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('whatsapp_numbers');
    }
};
