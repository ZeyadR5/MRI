<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->integer('age')->nullable();
    $table->string('gender')->nullable();
    $table->float('height')->nullable();
    $table->float('weight')->nullable();
    $table->float('sugar')->nullable();
    $table->float('blood_pressure')->nullable();
    $table->timestamp('recorded_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
