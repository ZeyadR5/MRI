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
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('period'); // morning, afternoon, night
            $table->string('category'); // contrast, spine_brain, joints, other
            $table->integer('slot_number')->nullable(); // Can be null for custom manual slots
            $table->time('start_time'); // وقت بداية السلوت
            $table->integer('duration_minutes'); // مدة السلوت بالدقائق
            $table->boolean('is_custom')->default(false); // هل هو ميعاد يدوي مخصص؟
            $table->date('specific_date')->nullable(); // لو مخصص يبقى مرتبط بيوم معين
            $table->timestamps();

            $table->index(['period', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
