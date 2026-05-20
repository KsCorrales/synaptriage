<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('category');
            $table->string('customer_tier');
            $table->integer('response_time_expectation'); // hours
            $table->string('priority');                   // ground truth for training
            $table->string('predicted_priority')->nullable();
            $table->float('confidence_score')->nullable();
            $table->string('triage_status')->default('pending'); // pending | complete | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};