<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone_e164')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('manual'); // whatsapp|web|referral|manual
            $table->jsonb('tags')->nullable();
            $table->jsonb('custom')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->timestamp('score_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'phone_e164']);
            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_e164')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->default('manual'); // whatsapp|web|referral|manual
            $table->string('status')->default('new');    // new|contacted|qualified|converted|lost
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contacts');
    }
};
