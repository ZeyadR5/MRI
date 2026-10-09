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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->date('booking_date'); // تاريخ الحجز
            $table->foreignId('time_slot_id')->constrained('time_slots')->onDelete('cascade');
            $table->string('patient_name')->nullable(); // اسم المريض
            $table->string('phone')->nullable(); // رقم التليفون
            $table->string('exam_type')->nullable(); // نوع الفحص التفصيلي (قطنية، عنقية، مخ، ركبة...)
            $table->string('exam_details')->nullable(); // تفاصيل إضافية
            $table->enum('payment_type', ['cash', 'insurance', 'contract'])->default('cash'); // نقدي / تأمين / تعاقد
            $table->text('notes')->nullable(); // ملاحظات
            $table->enum('status', ['booked', 'completed', 'cancelled', 'no_show'])->default('booked');
            $table->timestamps();

            $table->unique(['booking_date', 'time_slot_id']); // سلوت واحد لكل يوم
            $table->index('booking_date');
            $table->index('patient_name');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
