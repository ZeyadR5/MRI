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
        // 1. Add branch to time_slots
        Schema::table('time_slots', function (Blueprint $table) {
            if (!Schema::hasColumn('time_slots', 'branch')) {
                $table->string('branch')->default('zaraeein')->after('id');
                $table->index(['branch', 'period', 'category']);
            }
        });

        // 2. Add branch to bookings
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'branch')) {
                $table->string('branch')->default('zaraeein')->after('id');
                $table->index(['branch', 'booking_date']);
            }
        });

        // 3. Add role and username to users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('password');
            }
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('email');
            }
        });

        // 4. Create audit_logs table
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name');
                $table->string('user_role');
                $table->string('action'); // e.g. إنشاء حجز, تعديل حجز, حذف حجز, تسجيل دخول
                $table->text('description');
                $table->string('ip_address')->nullable();
                $table->string('branch')->nullable();
                $table->timestamps();

                $table->index(['created_at', 'action']);
                $table->index('user_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'username']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('branch');
        });

        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropColumn('branch');
        });
    }
};
