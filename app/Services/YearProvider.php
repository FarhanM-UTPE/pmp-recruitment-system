<?php

namespace App\Services;

use App\Models\MPPSubmission;
use App\Models\Application;

class YearProvider
{
    public static function availableYears(): array
    {
        // Years from MPP submissions
        $mppYears = MPPSubmission::select('year')->distinct()->pluck('year')->filter()->values();

        // Years stored on applications (mpp_year)
        $applicationYears = Application::select('mpp_year')->distinct()->pluck('mpp_year')->filter()->values();

        // Merge MPP years and application mpp_years, normalize to integers to avoid duplicate string/int entries
        $yearsCollection = $mppYears->merge($applicationYears)
            ->filter()
            ->map(fn($y) => (int) $y)
            ->unique()
            ->sortDesc()
            ->values();

        return $yearsCollection->toArray();
    }
}
