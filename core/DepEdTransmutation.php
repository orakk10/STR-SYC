<?php
// core/DepEdTransmutation.php

class DepEdTransmutation {
    // Official DepEd Transmutation Table mapping Initial Grade (0-100) to Transmuted Grade
    private static $transmutationTable = [
        100 => 100, 98.40 => 99, 96.80 => 98, 95.20 => 97, 93.60 => 96,
        92.00 => 95, 90.40 => 94, 88.80 => 93, 87.20 => 92, 85.60 => 91,
        84.00 => 90, 82.40 => 89, 80.80 => 88, 79.20 => 87, 77.60 => 86,
        76.00 => 85, 74.40 => 84, 72.80 => 83, 71.20 => 82, 69.60 => 81,
        68.00 => 80, 66.40 => 79, 64.80 => 78, 63.20 => 77, 61.60 => 76,
        60.00 => 75, 56.00 => 74, 52.00 => 73, 48.00 => 72, 44.00 => 71,
        40.00 => 70, 36.00 => 69, 32.00 => 68, 28.00 => 67, 24.00 => 66,
        20.00 => 65, 16.00 => 64, 12.00 => 63,  8.00 => 62,  4.00 => 61,
         0.00 => 60
    ];

    public static function transmute($initialGrade) {
        foreach (self::$transmutationTable as $minInitial => $transmuted) {
            if ($initialGrade >= $minInitial) {
                return $transmuted;
            }
        }
        return 60;
    }

    public static function getLetterGrade($transmutedGrade) {
        if ($transmutedGrade >= 90) return 'O';  // Outstanding
        if ($transmutedGrade >= 85) return 'VS'; // Very Satisfactory
        if ($transmutedGrade >= 80) return 'S';  // Satisfactory
        if ($transmutedGrade >= 75) return 'FS'; // Fairly Satisfactory
        return 'Did Not Meet Expectations';
    }
}
?>