<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stageNames = [
            ['type' => 'candidate_stage', 'key' => 'psikotes', 'label' => 'Psikotes', 'sort_order' => 1],
            ['type' => 'candidate_stage', 'key' => 'hc_interview', 'label' => 'HC Interview', 'sort_order' => 2],
            ['type' => 'candidate_stage', 'key' => 'user_interview', 'label' => 'User Interview', 'sort_order' => 3],
            ['type' => 'candidate_stage', 'key' => 'offering_letter', 'label' => 'Offering Letter', 'sort_order' => 4],
            ['type' => 'candidate_stage', 'key' => 'mcu', 'label' => 'MCU', 'sort_order' => 5],
            ['type' => 'candidate_stage', 'key' => 'interview_bod', 'label' => 'Interview BOD', 'sort_order' => 6],
            ['type' => 'candidate_stage', 'key' => 'hiring', 'label' => 'Hiring', 'sort_order' => 7],
        ];

        $statusValues = [
            ['type' => 'candidate_status', 'key' => 'LULUS', 'label' => 'LULUS', 'sort_order' => 1],
            ['type' => 'candidate_status', 'key' => 'TIDAK LULUS', 'label' => 'TIDAK LULUS', 'sort_order' => 2],
        ];

        foreach (array_merge($stageNames, $statusValues) as $item) {
            MasterData::updateOrCreate(
                ['type' => $item['type'], 'key' => $item['key']],
                $item
            );
        }
    }
}
