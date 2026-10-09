<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        $branches = ['zaraeein', 'hadeeth_madina'];
        $slots = [];
        $now = now();

        foreach ($branches as $branch) {
            // === فترة الصبح - الصبغة ===
            $morningTimes = ['08:00', '09:15', '10:15'];
            foreach ($morningTimes as $i => $time) {
                $slots[] = [
                    'branch' => $branch,
                    'period' => 'morning',
                    'category' => 'contrast',
                    'slot_number' => $i + 1,
                    'start_time' => $time,
                    'duration_minutes' => 60,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // === فترة الضهر - فقرات ومخ ===
            $startHour = 11;
            $startMin = 10;
            for ($i = 0; $i < 17; $i++) {
                $min = $startMin + ($i * 10);
                $hour = $startHour + intdiv($min, 60);
                $min = $min % 60;
                $slots[] = [
                    'branch' => $branch,
                    'period' => 'afternoon',
                    'category' => 'spine_brain',
                    'slot_number' => $i + 1,
                    'start_time' => sprintf('%02d:%02d', $hour, $min),
                    'duration_minutes' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // === فترة الضهر - مفاصل ===
            $jointStartHour = 14;
            $jointStartMin = 0;
            for ($i = 0; $i < 6; $i++) {
                $min = $jointStartMin + ($i * 30);
                $hour = $jointStartHour + intdiv($min, 60);
                $min = $min % 60;
                $slots[] = [
                    'branch' => $branch,
                    'period' => 'afternoon',
                    'category' => 'joints',
                    'slot_number' => $i + 1,
                    'start_time' => sprintf('%02d:%02d', $hour, $min),
                    'duration_minutes' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // === فترة الليل - فقرات ومخ ===
            $nightSpineStart = 17 * 60 + 10;
            for ($i = 0; $i < 18; $i++) {
                $totalMin = $nightSpineStart + ($i * 10);
                $hour = intdiv($totalMin, 60);
                $min = $totalMin % 60;
                $slots[] = [
                    'branch' => $branch,
                    'period' => 'night',
                    'category' => 'spine_brain',
                    'slot_number' => $i + 1,
                    'start_time' => sprintf('%02d:%02d', $hour, $min),
                    'duration_minutes' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // === فترة الليل - مفاصل ===
            $nightJointStart = 20 * 60;
            for ($i = 0; $i < 6; $i++) {
                $totalMin = $nightJointStart + ($i * 30);
                $hour = intdiv($totalMin, 60);
                $min = $totalMin % 60;
                $slots[] = [
                    'branch' => $branch,
                    'period' => 'night',
                    'category' => 'joints',
                    'slot_number' => $i + 1,
                    'start_time' => sprintf('%02d:%02d', $hour, $min),
                    'duration_minutes' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Only insert if no base slots exist or re-seed
        $existingCount = DB::table('time_slots')->whereNull('specific_date')->count();
        if ($existingCount === 0) {
            DB::table('time_slots')->insert($slots);
        } else {
            // Ensure branch column is set for existing slots
            DB::table('time_slots')->whereNull('branch')->update(['branch' => 'zaraeein']);
            // Add missing branch slots for hadeeth_madina if absent
            $madinaCount = DB::table('time_slots')->where('branch', 'hadeeth_madina')->count();
            if ($madinaCount === 0) {
                $madinaSlots = array_filter($slots, fn($s) => $s['branch'] === 'hadeeth_madina');
                DB::table('time_slots')->insert(array_values($madinaSlots));
            }
        }
    }
}
