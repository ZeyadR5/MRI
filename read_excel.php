<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/Copy of شيت الرنين ).xlsx';
$spreadsheet = IOFactory::load($file);

$sheetNames = $spreadsheet->getSheetNames();
echo "Total sheets: " . count($sheetNames) . "\n";
echo "Sheet names (first 10): " . implode(', ', array_slice($sheetNames, 0, 10)) . "\n";
echo "Sheet names (last 10): " . implode(', ', array_slice($sheetNames, -10)) . "\n\n";

// Find a sheet with actual data (patient names filled)
foreach ($sheetNames as $index => $name) {
    $sheet = $spreadsheet->getSheet($index);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    
    // Check if this sheet has data in column C (name column)
    $hasData = false;
    for ($row = 2; $row <= min($highestRow, 20); $row++) {
        $nameCell = $sheet->getCell('C' . $row)->getValue();
        if ($nameCell !== null && $nameCell !== '') {
            $hasData = true;
            break;
        }
    }
    
    if ($hasData) {
        echo "=== Sheet WITH DATA: $name (Rows: $highestRow, Cols: $highestCol) ===\n";
        // Print ALL rows with data
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            foreach (range('A', $highestCol) as $col) {
                $cell = $sheet->getCell($col . $row);
                $value = $cell->getFormattedValue();
                if ($value !== null && $value !== '') {
                    $rowData[$col] = $value;
                }
            }
            if (!empty($rowData)) {
                echo "Row $row: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        echo "\n";
        // Only show first 3 sheets with data
        static $count = 0;
        $count++;
        if ($count >= 3) break;
    }
}

// Also show merged cells info
echo "\n=== Merged Cells Info (first sheet with data) ===\n";
foreach ($sheetNames as $index => $name) {
    $sheet = $spreadsheet->getSheet($index);
    $nameCell = $sheet->getCell('C2')->getValue();
    if ($nameCell !== null && $nameCell !== '') {
        $mergedCells = $sheet->getMergeCells();
        if (!empty($mergedCells)) {
            echo "Merged cells in '$name': " . json_encode($mergedCells, JSON_UNESCAPED_UNICODE) . "\n";
        }
        break;
    }
}
