<?php

namespace App\Http\Controllers;

use App\Models\MasterData;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    protected function stageKeys(): array
    {
        return [
            'psikotes' => 'Psikotes',
            'hc_interview' => 'HC Interview',
            'user_interview' => 'User Interview',
            'offering_letter' => 'Offering Letter',
            'mcu' => 'MCU',
            'interview_bod' => 'Interview BOD',
            'hiring' => 'Hiring',
        ];
    }

    protected function statusKeys(): array
    {
        return [
            'TIDAK LULUS' => 'TIDAK LULUS',
            'CANCEL' => 'CANCEL',

        ];
    }

    public function index()
    {
        $stageKeys = $this->stageKeys();
        $statusKeys = $this->statusKeys();

        $masterData = MasterData::where('type', 'candidate_stage_status')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();

        $departments = Department::orderBy('name')->get();
        $total = $masterData->count();

        return view('masterdata.index', compact('stageKeys', 'statusKeys', 'masterData', 'departments', 'total'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stage_name' => ['required', Rule::in(array_keys($this->stageKeys()))],
            'status' => ['required', Rule::in(array_keys($this->statusKeys()))],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'total' => ['required', 'integer', 'min:0'],
        ]);

        $key = sprintf('%s|%s', $validated['stage_name'], $validated['status']);
        $sortOrder = array_search($validated['stage_name'], array_keys($this->stageKeys()), true);
        $sortOrder = $sortOrder !== false ? $sortOrder : 0;

        MasterData::updateOrCreate(
            [
                'type' => 'candidate_stage_status',
                'key' => $key,
                'year' => $validated['year'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
            ],
            [
                'label' => $validated['status'],
                'value' => (string) $validated['total'],
                'sort_order' => $sortOrder,
                'active' => true,
            ]
        );

        return redirect()->route('masterdata.index')->with('success', 'Master data berhasil disimpan.');
    }

    public function update(Request $request, MasterData $masterData)
    {
        $validated = $request->validate([
            'stage_name' => ['required', Rule::in(array_keys($this->stageKeys()))],
            'status' => ['required', Rule::in(array_keys($this->statusKeys()))],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'total' => ['required', 'integer', 'min:0'],
        ]);

        $key = sprintf('%s|%s', $validated['stage_name'], $validated['status']);
        $existing = MasterData::where('type', 'candidate_stage_status')
            ->where('key', $key)
            ->where('year', $validated['year'] ?? null)
            ->where('department_id', $validated['department_id'] ?? null)
            ->where('id', '!=', $masterData->id)
            ->first();

        if ($existing) {
            return back()
                ->withErrors(['status' => 'Kombinasi stage, status, tahun, dan departemen sudah ada.'])
                ->withInput();
        }

        $sortOrder = array_search($validated['stage_name'], array_keys($this->stageKeys()), true);
        $sortOrder = $sortOrder !== false ? $sortOrder : 0;

        $masterData->update([
            'type' => 'candidate_stage_status',
            'key' => $key,
            'label' => $validated['status'],
            'value' => (string) $validated['total'],
            'year' => $validated['year'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);

        return redirect()->route('masterdata.index')->with('success', 'Master data berhasil diperbarui.');
    }

    public function destroy(MasterData $masterData)
    {
        $masterData->delete();

        return redirect()->route('masterdata.index')->with('success', 'Master data berhasil dihapus.');
    }
}
