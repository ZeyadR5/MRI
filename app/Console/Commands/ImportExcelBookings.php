<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\TimeSlot;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class ImportExcelBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mri:import-excel {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import MRI bookings from the Excel sheet into the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '-1'); // Allow unlimited memory for the large Excel file
        
        $filePath = $this->argument('file') ?? base_path('Copy of شيت الرنين ).xlsx');

        if (!file_exists($filePath)) {
            $this->error("File not found at: {$filePath}");
            return 1;
        }

        $this->info("Loading Excel file... (this may take a minute)");
        
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            $this->error("Failed to load Excel file: " . $e->getMessage());
            return 1;
        }

        $sheetNames = $spreadsheet->getSheetNames();
        $this->info("Found " . count($sheetNames) . " sheets.");

        // We will cache time slots to avoid massive DB queries
        // Structure: $slotsCache[$period][$category][$time] = $timeSlotId
        $slotsCache = [];
        $allSlots = TimeSlot::whereNull('specific_date')->get();
        foreach ($allSlots as $slot) {
            // Using substr to get HH:MM
            $timeKey = substr($slot->start_time, 0, 5);
            $slotsCache[$slot->period][$slot->category][$timeKey] = $slot->id;
        }

        $importedCount = 0;
        $skippedCount = 0;

        foreach ($sheetNames as $index => $sheetName) {
            // Try to parse the sheet name as a date
            $date = $this->parseDateFromSheetName($sheetName);
            if (!$date) {
                $this->warn("Skipping sheet '{$sheetName}' - cannot parse as valid date.");
                continue;
            }

            $this->info("Processing sheet: {$sheetName} (Parsed as: {$date->format('Y-m-d')})");
            
            // Cleanup previous runs for this date
            Booking::where('booking_date', $date->format('Y-m-d'))->delete();
            TimeSlot::where('specific_date', $date->format('Y-m-d'))->where('is_custom', true)->delete();
            
            $sheet = $spreadsheet->getSheet($index);
            $highestRow = $sheet->getHighestRow();
            
            // Collect rows
            $bookingsToInsert = [];
            
            $bookedSlotsForDay = []; // Track used slots for the day
            
            for ($row = 2; $row <= $highestRow; $row++) {
                $timeValue = $sheet->getCell('B' . $row)->getFormattedValue();
                $patientName = $sheet->getCell('C' . $row)->getValue();
                
                // Skip if no time or no patient name, or patient name is just '0'
                if (empty($timeValue) || empty($patientName) || $patientName == '0' || trim($patientName) === '') {
                    continue;
                }

                $phone = $sheet->getCell('D' . $row)->getValue();
                $categoryText = $sheet->getCell('E' . $row)->getValue();
                $examDetails = $sheet->getCell('F' . $row)->getValue();
                $paymentText = $sheet->getCell('G' . $row)->getValue();
                $notes = $sheet->getCell('H' . $row)->getValue();

                // Clean time value (sometimes it's HH:MM, sometimes it's an Excel fraction)
                $timeStr = $this->cleanTimeValue($timeValue);
                if (!$timeStr) {
                    continue;
                }

                // Map Category
                $category = 'other';
                $period = 'morning';
                
                if (str_contains($categoryText, 'صبغه') || str_contains($categoryText, 'صبغة')) {
                    $category = 'contrast';
                    $period = 'morning';
                } elseif (str_contains($categoryText, 'فقرات') || str_contains($categoryText, 'مخ')) {
                    $category = 'spine_brain';
                } elseif (str_contains($categoryText, 'مفاصل')) {
                    $category = 'joints';
                }
                
                // Determine period based on time hour
                $hour = (int) substr($timeStr, 0, 2);
                if ($hour >= 8 && $hour < 11) {
                    $period = 'morning';
                } elseif ($hour >= 11 && $hour < 17) {
                    $period = 'afternoon';
                } else {
                    $period = 'night'; // Includes 17:00 to 02:30
                }

                // Map Payment
                $paymentType = 'cash';
                if (str_contains($paymentText, 'تامين') || str_contains($paymentText, 'تأمين')) {
                    $paymentType = 'insurance';
                } elseif (str_contains($paymentText, 'تعاقد')) {
                    $paymentType = 'contract';
                }

                // Find matching time slot
                $timeSlotId = $slotsCache[$period][$category][$timeStr] ?? null;
                $isCustom = false;
                
                // If it doesn't match a template OR it's already used today, create a custom slot
                if (!$timeSlotId || in_array($timeSlotId, $bookedSlotsForDay)) {
                    // Create a custom time slot for this specific booking
                    $customSlot = TimeSlot::create([
                        'period' => $period,
                        'category' => $category,
                        'start_time' => $timeStr,
                        'duration_minutes' => ($category === 'joints' ? 30 : ($category === 'contrast' ? 60 : 10)),
                        'is_custom' => true,
                        'specific_date' => $date->format('Y-m-d'),
                    ]);
                    $timeSlotId = $customSlot->id;
                    $isCustom = true;
                }

                $bookedSlotsForDay[] = $timeSlotId;

                // Prepare booking record
                $bookingsToInsert[] = [
                    'booking_date' => $date->format('Y-m-d'),
                    'time_slot_id' => $timeSlotId,
                    'patient_name' => (string) $patientName,
                    'phone' => $phone ? (string) $phone : null,
                    'exam_type' => $categoryText ? (string) $categoryText : null,
                    'exam_details' => $examDetails ? (string) $examDetails : null,
                    'payment_type' => $paymentType,
                    'notes' => $notes ? (string) $notes : null,
                    'status' => 'completed', // Assume old bookings are completed
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            if (!empty($bookingsToInsert)) {
                // Insert new ones
                foreach (array_chunk($bookingsToInsert, 50) as $chunk) {
                    Booking::insert($chunk);
                }
                $importedCount += count($bookingsToInsert);
                $this->line("  -> Imported " . count($bookingsToInsert) . " bookings.");
            } else {
                $skippedCount++;
            }
        }

        $this->info("Import completed!");
        $this->info("Total bookings imported: {$importedCount}");
        $this->info("Empty/Invalid sheets skipped: {$skippedCount}");
        
        return 0;
    }

    private function parseDateFromSheetName($name)
    {
        $name = trim($name);
        
        // Try exact formats like "19-4-2025"
        try {
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $name, $matches)) {
                return Carbon::createFromFormat('d-m-Y', $name);
            }
            
            // Try formats like "21-12" (Assume current year or 2025 if it's future/past depending on context)
            // Let's assume year 2024 or 2025 based on the other sheets
            if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $name, $matches)) {
                // If the month is 12, maybe it's 2024, if it's early months maybe 2025. 
                // We'll just append 2024 as default for missing years if month > 6, else 2025.
                $month = (int)$matches[2];
                $year = $month > 6 ? 2024 : 2025; 
                return Carbon::createFromDate($year, $month, (int)$matches[1]);
            }
        } catch (\Exception $e) {
            return null;
        }
        
        return null;
    }

    private function cleanTimeValue($timeValue)
    {
        if (empty($timeValue)) return null;
        
        // If it's formatted as "HH:MM" or "H:MM"
        if (preg_match('/^(\d{1,2}):(\d{2})/', $timeValue, $matches)) {
            $h = (int)$matches[1];
            $m = (int)$matches[2];
            return sprintf('%02d:%02d', $h, $m);
        }
        
        return null;
    }
}
