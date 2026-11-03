<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->decimal('quarterly_quota', 16, 2)->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('round_robin')->default(true);
        });

        Schema::create('snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('until');
            $table->timestamps();
            $table->index(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snoozes');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('round_robin'));
        Schema::table('company_user', fn (Blueprint $t) => $t->dropColumn('quarterly_quota'));
    }
};
