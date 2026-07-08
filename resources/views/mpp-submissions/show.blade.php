@extends('layouts.app')

@section('content')
<style>
    .dotted-underline {
        background-image: linear-gradient(to right, #6b7280 33%, rgba(255, 255, 255, 0) 0%);
        background-position: left bottom;
        background-size: 5px 1px;
        background-repeat: repeat-x;
    }
    .preview-table {
        color: #1f2937;
    }
    .form-section-title {
        background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
        letter-spacing: 0.03em;
    }
    .approval-title-cell {
        background-color: #f9fafb;
        font-size: 0.75rem;
    }
    .approval-status-cell {
        min-height: 82px;
        font-size: 0.95rem;
        color: #111827;
        background-color: #ffffff;
    }
    .approval-name-cell {
        background-color: #f8fafc;
        font-size: 0.78rem;
    }
    .approval-group-divider {
        border-bottom-width: 2px;
        border-bottom-color: #9ca3af;
    }
</style>
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Detail Pengajuan MPP</h1>
                <p class="mt-2 text-gray-600">{{ $mppSubmission->department->name }}</p>
            </div>
            <a href="{{ url('/mpp-submissions') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 cursor-pointer border-none inline-block no-underline">
                ← Kembali
            </a>
        </div>

        <!-- Floating Toast Notifications -->
        <div id="toast-container" class="fixed z-50 flex flex-col gap-3 items-end" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999;">
            @if ($message = Session::get('success'))
            <div class="toast-notification bg-white border-l-4 border-green-500 shadow-xl rounded-md p-4 w-80 transform transition-all duration-500 ease-out translate-x-full opacity-0">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                        <p class="text-sm font-medium text-gray-800">{{ $message }}</p>
                    </div>
                    <button onclick="this.closest('.toast-notification').remove()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Loading Indicator -->
                <div class="w-full bg-gray-200 h-1 mt-3 rounded-full overflow-hidden">
                    <div class="toast-progress bg-green-500 h-full" style="width: 100%; transition: width 3s linear;"></div>
                </div>
            </div>
            @endif

            @if ($message = Session::get('error'))
            <div class="toast-notification bg-white border-l-4 border-red-500 shadow-xl rounded-md p-4 w-80 transform transition-all duration-500 ease-out translate-x-full opacity-0">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                        <p class="text-sm font-medium text-gray-800">{{ $message }}</p>
                    </div>
                    <button onclick="this.closest('.toast-notification').remove()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Loading Indicator -->
                <div class="w-full bg-gray-200 h-1 mt-3 rounded-full overflow-hidden">
                    <div class="toast-progress bg-red-500 h-full" style="width: 100%; transition: width 3s linear;"></div>
                </div>
            </div>
            @endif
        </div>

        <!-- Added ID here for DOM Replacement -->
        <div id="main-content-wrapper" style="display:contents;">
            <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Informasi Pengajuan</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Departemen</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->department->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Versi Form</p>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ ($mppSubmission->form_version ?? 'old') === 'new' ? 'Baru' : 'Lama' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Dibuat Oleh</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->createdByUser->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Tanggal Dibuat</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->created_at->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </div>

            @if (($mppSubmission->form_version ?? 'old') === 'new')
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Detail Form MPP</h2>

                @php
                    $requesterName = $mppSubmission->createdByUser->name ?? '-';
                    $requesterDepartment = $mppSubmission->department->name ?? '-';
                    $tanggalPengajuan = optional($mppSubmission->created_at)->format('d M Y') ?: '-';
                    $jenisKelaminLabel = $mppSubmission->jenis_kelamin === 'laki_laki' ? 'Laki-laki' : ($mppSubmission->jenis_kelamin === 'perempuan' ? 'Perempuan' : '-');
                    $statusPerkawinanLabel = $mppSubmission->status_perkawinan === 'kawin' ? 'Kawin' : ($mppSubmission->status_perkawinan === 'tidak_kawin' ? 'Tidak Kawin' : '-');
                    $isPenggantianKaryawan = $mppSubmission->alasan_penambahan_manpower === 'penggantian_karyawan';
                    $kesesuaianManPowerPlanLabel = $mppSubmission->kesesuaian_man_power_plan === 'sesuai_mpp'
                        ? 'Sesuai Man Power Plan'
                        : ($mppSubmission->kesesuaian_man_power_plan === 'di_luar_mpp' ? 'Di Luar Man Power Plan' : '-');
                    $selectedPendidikanRows = collect([
                        ['label' => 'SMK', 'selected' => (bool) data_get($mppSubmission->pendidikan_requirements, 'smk.selected'), 'major' => data_get($mppSubmission->pendidikan_requirements, 'smk.major')],
                        ['label' => 'D3', 'selected' => (bool) data_get($mppSubmission->pendidikan_requirements, 'd3.selected'), 'major' => data_get($mppSubmission->pendidikan_requirements, 'd3.major')],
                        ['label' => 'S1', 'selected' => (bool) data_get($mppSubmission->pendidikan_requirements, 's1.selected'), 'major' => data_get($mppSubmission->pendidikan_requirements, 's1.major')],
                    ])->filter(fn($item) => $item['selected'])->values();
                    $statusPegawaiLabel = collect($mppSubmission->status_pegawai ?? [])->map(function ($item) {
                        return match ($item) {
                            'sementara_3_bulan' => 'Sementara 3 Bulan',
                            'sementara_6_bulan' => 'Sementara 6 Bulan',
                            'sementara_12_bulan' => 'Sementara 12 Bulan',
                            'sementara_18_bulan' => 'Sementara 18 Bulan',
                            default => str_replace('_', ' ', (string) $item),
                        };
                    })->implode(', ');
                    $fasilitasLabels = collect($mppSubmission->fasilitas_dibutuhkan ?? [])->map(function ($item) {
                        $item = (string) $item;

                        if (str_starts_with($item, 'others:')) {
                            $custom = trim(substr($item, 7));

                            return $custom !== '' ? 'Others: ' . $custom : 'Others';
                        }

                        return match ($item) {
                            'computer' => 'Computer',
                            'meja_dan_kursi_kerja' => 'Meja dan Kursi Kerja',
                            'seragam_apd' => 'Seragam & APD',
                            'safety_shoes' => 'Safety Shoes',
                            'extra_fooding' => 'Extra Fooding',
                            'safety_helmet' => 'Safety Helmet',
                            'others' => 'Others',
                            default => ucwords(str_replace('_', ' ', $item)),
                        };
                    })->filter()->values();
                    $requesterDeptHeadName = optional(
                        \App\Models\User::role('kepala departemen')
                            ->where('department_id', $mppSubmission->department_id)
                            ->active()
                            ->first()
                    )->name ?? '-';
                    $picRecruitmentFallbackName = optional(
                        \App\Models\User::where('email', 'hc2@pmp.com')
                            ->active()
                            ->first()
                    )->approval_signer_name ?? 'Nanda Abdi Firdausi';
                    $storedApprovals = collect($mppSubmission->approvalStages ?? [])
                        ->sortBy('stage_index')
                        ->values()
                        ->map(function ($stage) {
                            return [
                                'role' => (string) ($stage->role ?? ''),
                                'required_roles' => array_values((array) ($stage->required_roles ?? [])),
                                'name' => $stage->name,
                                'approver_user_id' => $stage->approver_user_id,
                                'decision' => (string) ($stage->decision ?? 'pending'),
                                'digitally_signed' => (bool) $stage->digitally_signed,
                                'signature_label' => $stage->signature_label,
                                'signed_at' => optional($stage->signed_at)->toDateTimeString(),
                            ];
                        });

                    $formatApprovalTitle = function ($rawRole) {
                        $normalized = strtolower(trim((string) $rawRole));

                        return match (true) {
                            str_contains($normalized, 'div. head') => 'Disetujui Oleh Div. Head',
                            str_contains($normalized, 'hcd div') => 'Disetujui Oleh HCD Div. Head',
                            str_contains($normalized, 'hcd dept') => 'Diketahui Oleh HCD Dept. Head',
                            str_contains($normalized, 'pic recruitment') => 'Diterima Oleh PIC Recruitment',
                            str_contains($normalized, 'executive') => 'Disetujui Oleh Executive',
                            $normalized !== '' => (string) $rawRole,
                            default => '-',
                        };
                    };

                    $approvalTitles = $storedApprovals
                        ->map(fn($stored) => $formatApprovalTitle(data_get($stored, 'role', '')))
                        ->values();

                    if ($approvalTitles->isEmpty()) {
                        $approvalTitles = collect([
                            'Diminta Oleh',
                            'Diketahui Oleh Dept. Head',
                            'Disetujui Oleh Div. Head',
                            'Disetujui Oleh HCD Div. Head',
                            'Diketahui Oleh HCD Dept. Head',
                            'Diterima Oleh PIC Recruitment',
                            'Disetujui Oleh Executive',
                            'Disetujui Oleh Executive',
                        ]);

                        if (($mppSubmission->kesesuaian_man_power_plan ?? null) === 'di_luar_mpp') {
                            $approvalTitles->push('Disetujui Oleh Executive');
                        }
                    }

                    // Backward compatibility: legacy snapshots may still contain Executive 3
                    // (9 approval items). Only in that case, remove Executive 3 so indexes
                    // align with the current 8-step workflow.
                    if (
                        $storedApprovals->count() > count($approvalTitles)
                        && ($mppSubmission->kesesuaian_man_power_plan ?? null) !== 'di_luar_mpp'
                    ) {
                        $storedApprovals = $storedApprovals
                            ->reject(function ($stored) {
                                $role = strtolower((string) data_get($stored, 'role', ''));
                                return str_contains($role, 'executive 3');
                            })
                            ->values();

                        $approvalTitles = $approvalTitles->take($storedApprovals->count())->values();
                    }

                    $approvalRows = collect($approvalTitles)->map(function ($title, $index) use ($storedApprovals, $requesterName, $requesterDeptHeadName, $picRecruitmentFallbackName, $approvableStageIndexes) {
                        $stored = $storedApprovals->get($index, []);
                        $decision = strtolower((string) data_get($stored, 'decision', ''));
                        $isSigned = (bool) data_get($stored, 'digitally_signed');
                        $name = data_get($stored, 'name');
                        $normalizedName = strtolower(trim((string) $name));
                        $decisionKey = match ($decision) {
                            'approved' => 'approved',
                            'disapproved' => 'disapproved',
                            default => $isSigned ? 'approved' : 'pending',
                        };

                        $status = match ($decisionKey) {
                            'approved' => 'Approved',
                            'disapproved' => 'Disapproved',
                            default => 'Pending',
                        };

                        if (empty($name) && $index === 0) {
                            $name = $requesterName;
                        }

                        if (empty($name) && $index === 1) {
                            $name = $requesterDeptHeadName;
                        }

                        // Backward compatibility for old snapshots that used generic DIC label.
                        if (($index === 6 || $index === 7) && (empty($name) || $normalizedName === 'dic account')) {
                            $name = $index === 6 ? 'Rimba Kusumadilaga' : 'Teguh Patmuryanto';
                        }

                        $isOutsideMpp = ($mppSubmission->kesesuaian_man_power_plan ?? null) === 'di_luar_mpp';

                        if ($isOutsideMpp && $index === 8 && empty($name)) {
                            $name = 'DIC MS Engineering SCM';
                        }

                        // Ensure PIC Recruitment row has a readable default approver name.
                        $picRecruitmentIndex = 5;
                        if ($index === $picRecruitmentIndex && empty($name)) {
                            $name = $picRecruitmentFallbackName;
                        }

                        return [
                            'index' => $index,
                            'title' => $title,
                            'status' => $status,
                            'decision_key' => $decisionKey,
                            'name' => $name ?: '-',
                            'signed' => $status === 'Approved',
                            'can_act' => in_array($index, $approvableStageIndexes ?? [], true),
                        ];
                    });

                    // Target visual layout:
                    // Row 1: 0,1,2 (requester/dept/div)
                    // Row 2: executive stages
                    // Row 3: HCD Div, HCD Dept, PIC
                    $approvalLayouts = collect();
                    if ($approvalRows->count() === 8) {
                        $approvalLayouts = collect([
                            ['type' => 'three', 'items' => $approvalRows->slice(0, 3)->values()],
                            ['type' => 'two-full', 'items' => $approvalRows->slice(6, 2)->values()],
                            ['type' => 'three', 'items' => $approvalRows->slice(3, 3)->values()],
                        ]);
                    } elseif ($approvalRows->count() === 9) {
                        $approvalLayouts = collect([
                            ['type' => 'three', 'items' => $approvalRows->slice(0, 3)->values()],
                            ['type' => 'three', 'items' => $approvalRows->slice(6, 3)->values()],
                            ['type' => 'three', 'items' => $approvalRows->slice(3, 3)->values()],
                        ]);
                    } else {
                        $approvalLayouts = $approvalRows
                            ->chunk(3)
                            ->map(fn($group) => ['type' => 'three', 'items' => $group->pad(3, null)->values()])
                            ->values();
                    }

                    $requesterApprovalName = data_get(
                        $approvalRows->firstWhere('index', 0),
                        'name',
                        $requesterName
                    );

                @endphp

                <div class="mb-8 border border-gray-300 rounded-md overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-300 p-3">
                        <div class="shrink-0 flex items-center">
                            <img src="{{ asset('images/Logo Patria.png') }}" alt="Logo" class="object-contain" style="height: 56px; width: auto;">
                        </div>
                        <div class="text-center">
                            <p class="text-base font-bold tracking-wide">PERMINTAAN KARYAWAN</p>
                            <p class="text-base font-bold tracking-wide">OUTSOURCING</p>
                            <p class="text-xs font-semibold mt-1">Human Capital Department</p>
                        </div>
                        <div class="shrink-0">
                            <div class="border border-gray-700 px-3 py-2 text-xs font-semibold uppercase whitespace-nowrap">Confidential</div>
                        </div>
                    </div>

                    <table class="w-full border-collapse text-sm preview-table">
                        <tbody>
                            <tr>
                                <td class="w-1/2 align-top border-r border-b border-gray-300 p-3">
                                    <span class="font-semibold">Kepada:</span> HC Department
                                </td>
                                <td class="w-1/2 border-b border-gray-300 p-0">
                                    <table class="w-full border-collapse text-sm">
                                        <tbody>
                                            <tr>
                                                <td class="w-1/2 border-r border-b border-gray-300 p-2"><span class="font-semibold">Dari:</span> {{ $requesterApprovalName }}</td>
                                                <td class="w-1/2 border-b border-gray-300 p-2"></td>
                                            </tr>
                                            <tr>
                                                <td class="w-1/2 border-r border-gray-300 p-2"><span class="font-semibold">Department/Div:</span> {{ $requesterDepartment }}</td>
                                                <td class="w-1/2 p-2"><span class="font-semibold">Tanggal:</span> {{ $tanggalPengajuan }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">JABATAN YANG DIPERLUKAN:</td>
                            </tr>

                            <tr>
                                <td class="w-1/2 align-top border-r border-b border-gray-300 p-3">
                                    <div class="space-y-1">
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Nama Jabatan:</span> {{ optional($mppSubmission->vacancy)->name ?: ($mppSubmission->custom_jabatan_name ?: '-') }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Atasan Langsung:</span> {{ $requesterName }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Golongan:</span> {{ $mppSubmission->golongan ?: '-' }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Status Pegawai:</span> {{ $statusPegawaiLabel ?: '-' }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Lokasi:</span> {{ $mppSubmission->lokasi_pekerjaan === 'head_office' ? 'Head Office' : ($mppSubmission->lokasi_pekerjaan === 'cabang' ? 'Cabang' : '-') }}</p>
                                    </div>
                                </td>
                                <td class="w-1/2 align-top border-b border-gray-300 p-3">
                                    <div class="space-y-1">
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Tanggal Mulai Bekerja:</span> {{ optional($mppSubmission->tanggal_mulai_bekerja)->format('d M Y') ?: '-' }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Jumlah Yang Diminta:</span> {{ !is_null($mppSubmission->jumlah_diminta) ? $mppSubmission->jumlah_diminta . ' orang' : '-' }}</p>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">ALASAN PENAMBAHAN MANPOWER:</td>
                            </tr>

                            <tr>
                                <td class="w-1/2 align-top border-r border-b border-gray-300 p-3">
                                    @if ($isPenggantianKaryawan)
                                        <div class="space-y-1">
                                            <p class="font-semibold">Penggantian Karyawan:</p>
                                            <p class="dotted-underline pb-1 mb-0.5">1. Nama Karyawan yang diganti: {{ $mppSubmission->nama_karyawan_diganti ?: '-' }}</p>
                                            <p class="dotted-underline pb-1 mb-0.5">2. Tanggal Keluar: {{ optional($mppSubmission->tanggal_keluar)->format('d M Y') ?: '-' }}</p>
                                            <p class="dotted-underline pb-1 mb-0.5">3. Alasan Penggantian: {{ $mppSubmission->alasan_penggantian ?: '-' }}</p>
                                        </div>
                                    @else
                                        <p class="font-semibold">Penggantian Karyawan:</p>
                                        <p class="dotted-underline pb-1 mb-0.5">-</p>
                                    @endif
                                </td>
                                <td class="w-1/2 align-top border-b border-gray-300 p-3">
                                    @if (!$isPenggantianKaryawan)
                                        <div class="space-y-1">
                                            <p class="font-semibold">Penambahan Karyawan Baru:</p>
                                            <p class="dotted-underline pb-1 mb-0.5">1. Jumlah Karyawan yang ada: {{ !is_null($mppSubmission->jumlah_karyawan_ada) ? $mppSubmission->jumlah_karyawan_ada . ' orang' : '-' }}</p>
                                            <p class="dotted-underline pb-1 mb-0.5">2. Kesesuaian Man Power Plan</p>
                                            <p class="pl-4 dotted-underline pb-1 mb-0.5">
                                                @if ($mppSubmission->kesesuaian_man_power_plan === 'sesuai_mpp')
                                                    Sesuai Man Power Plan
                                                @elseif ($mppSubmission->kesesuaian_man_power_plan === 'di_luar_mpp')
                                                    Di Luar Man Power Plan (Alasan: {{ $mppSubmission->alasan_kesesuaian ?: '-' }})
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    @else
                                        <p class="font-semibold">Penambahan Karyawan Baru:</p>
                                        <p class="dotted-underline pb-1 mb-0.5">-</p>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">PERSYARATAN JABATAN:</td>
                            </tr>

                            <tr>
                                <td class="w-1/2 align-top border-r border-b border-gray-300 p-3">
                                    <div class="space-y-2">
                                        <p class="font-semibold">Pendidikan Terakhir:</p>
                                        @forelse ($selectedPendidikanRows as $pendidikan)
                                            <p class="flex items-start gap-2 dotted-underline pb-1 mb-0.5">
                                                <span class="inline-block w-10">{{ $pendidikan['label'] }}</span>
                                                <span>Jurusan: {{ $pendidikan['major'] ?: '-' }}</span>
                                            </p>
                                        @empty
                                            <p class="dotted-underline pb-1 mb-0.5">-</p>
                                        @endforelse

                                        <div class="pt-1">
                                            <p class="font-semibold">Keahlian Khusus:</p>
                                            <p class="dotted-underline pb-1 mb-0.5">{{ $mppSubmission->keahlian_khusus ?: '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="w-1/2 align-top border-b border-gray-300 p-3">
                                    <div class="space-y-2">
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Jenis Kelamin:</span> {{ $jenisKelaminLabel }}</p>
                                        <p class="dotted-underline pb-1 mb-0.5"><span class="font-semibold">Status Perkawinan:</span> {{ $statusPerkawinanLabel }}</p>
                                        <div class="pt-1">
                                            <p class="font-semibold">Pengalaman Kerja:</p>
                                            <p class="dotted-underline pb-1 mb-0.5">{{ $mppSubmission->pengalaman_kerja ?: '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">URAIAN JABATAN:</td>
                            </tr>

                            <tr>
                                <td colspan="2" class="align-top border-b border-gray-300 p-3">
                                    <div class="space-y-1">
                                        @forelse (($mppSubmission->uraian_jabatan ?? []) as $uraian)
                                            <p class="dotted-underline pb-1 mb-0.5">{{ $loop->iteration }}. {{ $uraian }}</p>
                                        @empty
                                            <p class="dotted-underline pb-1 mb-0.5">-</p>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">FASILITAS YANG DIBUTUHKAN:</td>
                            </tr>

                            <tr>
                                <td colspan="2" class="align-top border-b border-gray-300 p-3">
                                    <div class="space-y-1">
                                        @forelse ($fasilitasLabels as $fasilitas)
                                            <p class="dotted-underline pb-1 mb-0.5">{{ $loop->iteration }}. {{ $fasilitas }}</p>
                                        @empty
                                            <p class="dotted-underline pb-1 mb-0.5">-</p>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="border-b border-gray-300 form-section-title p-2 font-bold uppercase text-center">APPROVAL:</td>
                            </tr>

                            <tr>
                                <td colspan="2" class="align-top border-b border-gray-300 p-0">
                                    <table class="w-full border-collapse text-sm table-fixed">
                                        <tbody>
                                            @foreach ($approvalLayouts as $approvalLayout)
                                                @php $isLastApprovalGroup = $loop->last; @endphp
                                                @if (data_get($approvalLayout, 'type') === 'two-full')
                                                    @php $approvalPair = collect(data_get($approvalLayout, 'items', []))->pad(2, null); @endphp
                                                    <tr>
                                                        <td colspan="3" class="p-0 border-b border-gray-300">
                                                            <table class="w-full border-collapse text-sm table-fixed">
                                                                <tr>
                                                                    @foreach ($approvalPair as $approvalColumn)
                                                                        <td class="w-1/2 border-r border-gray-300 px-2 py-1 text-center font-semibold leading-tight last:border-r-0 approval-title-cell">
                                                                            {{ data_get($approvalColumn, 'title', '') }}
                                                                        </td>
                                                                    @endforeach
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="p-0">
                                                            <table class="w-full border-collapse text-sm table-fixed">
                                                                <tr>
                                                                    @foreach ($approvalPair as $approvalColumn)
                                                                        @php
                                                                            $status = data_get($approvalColumn, 'status');
                                                                            $statusClass = $status === 'Approved'
                                                                                ? 'text-green-700 bg-green-50 border border-green-200'
                                                                                : ($status === 'Disapproved'
                                                                                    ? 'text-red-700 bg-red-50 border border-red-200'
                                                                                    : 'text-amber-700 bg-amber-50 border border-amber-200');
                                                                        @endphp
                                                                        <td class="w-1/2 border-r border-gray-300 px-2 py-6 text-center align-middle font-semibold last:border-r-0 approval-status-cell">
                                                                            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md text-sm {{ $statusClass }} {{ data_get($approvalColumn, 'status') ? '' : 'opacity-0' }}">
                                                                                {{ data_get($approvalColumn, 'status', '-') }}
                                                                            </span>
                                                                        </td>
                                                                    @endforeach
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="p-0 border-t border-gray-300">
                                                            <table class="w-full border-collapse text-sm table-fixed">
                                                                <tr>
                                                                    @foreach ($approvalPair as $approvalColumn)
                                                                        <td class="w-1/2 border-r border-gray-300 px-2 py-1 text-center font-semibold leading-tight last:border-r-0 approval-name-cell {{ $isLastApprovalGroup ? '' : 'approval-group-divider' }}">
                                                                            {{ data_get($approvalColumn, 'name', '') }}
                                                                        </td>
                                                                    @endforeach
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                @else
                                                    @php $approvalGroup = collect(data_get($approvalLayout, 'items', []))->pad(3, null); @endphp
                                                    <tr>
                                                        @foreach ($approvalGroup as $approvalColumn)
                                                            <td class="w-1/3 border-r border-b border-gray-300 px-2 py-1 text-center font-semibold leading-tight last:border-r-0 approval-title-cell">
                                                                {{ data_get($approvalColumn, 'title', '') }}
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                    <tr>
                                                        @foreach ($approvalGroup as $approvalColumn)
                                                            @php
                                                                $status = data_get($approvalColumn, 'status');
                                                                $statusClass = $status === 'Approved'
                                                                    ? 'text-green-700 bg-green-50 border border-green-200'
                                                                    : ($status === 'Disapproved'
                                                                        ? 'text-red-700 bg-red-50 border border-red-200'
                                                                        : 'text-amber-700 bg-amber-50 border border-amber-200');
                                                            @endphp
                                                            <td class="w-1/3 border-r border-gray-300 px-2 py-6 text-center align-middle font-semibold last:border-r-0 approval-status-cell">
                                                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md text-sm {{ $statusClass }} {{ data_get($approvalColumn, 'status') ? '' : 'opacity-0' }}">
                                                                    {{ data_get($approvalColumn, 'status', '-') }}
                                                                </span>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                    <tr>
                                                        @foreach ($approvalGroup as $approvalColumn)
                                                            <td class="w-1/3 border-r border-t border-gray-300 px-2 py-1 text-center font-semibold leading-tight last:border-r-0 approval-name-cell {{ $isLastApprovalGroup ? '' : 'approval-group-divider' }}">
                                                                {{ data_get($approvalColumn, 'name', '') }}
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if(!empty($approvableStageIndexes ?? []))
                    <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="space-y-3">
                            @foreach($approvalRows as $approvalRow)
                                @continue(!in_array($approvalRow['index'], $approvableStageIndexes ?? [], true))
                                <div class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-4 py-3">
                                    <p class="text-sm md:text-base font-semibold text-gray-800">{{ $approvalRow['title'] }}</p>
                                    <div class="flex items-center gap-2">
                                        @if(($approvalRow['decision_key'] ?? 'pending') === 'pending')
                                            <form action="{{ route('mpp-submissions.approve-stage', $mppSubmission) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="stage_index" value="{{ $approvalRow['index'] }}">
                                                <button type="submit" class="px-4 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700 text-sm md:text-base font-semibold shadow-sm hover:shadow">Approve</button>
                                            </form>
                                            <form action="{{ route('mpp-submissions.disapprove-stage', $mppSubmission) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="stage_index" value="{{ $approvalRow['index'] }}">
                                                <button type="submit" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 text-sm md:text-base font-semibold shadow-sm hover:shadow">Disapprove</button>
                                            </form>
                                        @else
                                            @php
                                                $statusClass = ($approvalRow['decision_key'] ?? 'pending') === 'approved'
                                                    ? 'text-green-700 bg-green-50 border border-green-200'
                                                    : 'text-red-700 bg-red-50 border border-red-200';
                                            @endphp
                                            <span class="inline-flex items-center justify-center px-3 py-1.5 rounded-md text-sm font-semibold {{ $statusClass }}">
                                                {{ $approvalRow['status'] }}
                                            </span>
                                            <details class="relative">
                                                <summary class="list-none inline-flex items-center justify-center h-9 w-9 rounded-md border border-gray-300 text-gray-600 hover:text-gray-900 hover:border-gray-400 cursor-pointer bg-white shadow-sm" title="Ubah keputusan" aria-label="Ubah keputusan">
                                                    <i class="fas fa-pencil-alt text-sm"></i>
                                                </summary>
                                                <div class="absolute right-0 mt-2 w-40 rounded-md border border-gray-200 bg-white shadow-lg p-2 z-20">
                                                    <div class="space-y-1">
                                                        <form action="{{ route('mpp-submissions.approve-stage', $mppSubmission) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="stage_index" value="{{ $approvalRow['index'] }}">
                                                            <button type="submit" class="w-full px-3 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-semibold">Approve</button>
                                                        </form>
                                                        <form action="{{ route('mpp-submissions.disapprove-stage', $mppSubmission) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="stage_index" value="{{ $approvalRow['index'] }}">
                                                            <button type="submit" class="w-full px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 text-sm font-semibold">Disapprove</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (auth()->user()->hasAnyRole(['team_hc', 'team_hc_2']))
                    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <h3 class="text-sm font-semibold text-blue-900 mb-2">Sinkronisasi Jabatan ke Master Data</h3>
                        <p class="text-xs text-blue-800 mb-3">
                            Gunakan ini setelah admin membuat vacancy baru di master data, agar MPP ini memakai vacancy yang sudah resmi.
                        </p>
                        <form action="{{ route('mpp-submissions.sync-vacancy', $mppSubmission) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            @csrf
                            <div class="md:col-span-2">
                                <label for="sync_vacancy_id" class="block text-xs font-medium text-gray-700 mb-1">Pilih Vacancy Master Data</label>
                                <select id="sync_vacancy_id" name="vacancy_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                    <option value="">Pilih Vacancy</option>
                                    @foreach (($departmentVacancies ?? collect()) as $vacancyOption)
                                        <option value="{{ $vacancyOption->id }}" @selected(optional($mppSubmission->vacancy)->id === $vacancyOption->id)>
                                            {{ $vacancyOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                    Update Vacancy MPP
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
            @endif

            <!-- Vacancies Table -->
            @if (($mppSubmission->form_version ?? 'old') !== 'new')
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Posisi & Dokumen</h2>
                <div class="space-y-4">
                    @forelse ($mppSubmission->vacancies as $vacancy)
                        @php
                            $requiredDocType = $vacancy->pivot->vacancy_status === 'OSPKWT' ? 'A1' : 'B1';
                            $document = $vacancy->getDocument($requiredDocType);
                            $isDepartmentUser = auth()->user()->hasRole('kepala departemen');
                            $isTeamHC = auth()->user()->hasAnyRole(['team_hc', 'team_hc_2']);
                        @endphp
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Left Side: Position Info -->
                                <div class="md:col-span-2">
                                    <h3 class="text-lg font-bold text-gray-900">{{ $vacancy->name }}</h3>
                                    <div class="flex items-center gap-4 mt-2">
                                        <span class="text-sm text-gray-600">
                                            <i class="fas fa-users mr-1"></i>
                                            {{ $vacancy->pivot->needed_count ?? '-' }} orang
                                        </span>
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if($vacancy->pivot->vacancy_status === 'OSPKWT') bg-blue-100 text-blue-800
                                            @elseif($vacancy->pivot->vacancy_status === 'OS') bg-purple-100 text-purple-800 @endif
                                            ">
                                            {{ $vacancy->pivot->vacancy_status }}
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        @if($vacancy->pivot->proposal_status === 'approved')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Disetujui</span>
                                        @elseif($vacancy->pivot->proposal_status === 'rejected')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800" title="{{ $vacancy->pivot->rejection_reason }}">Ditolak</span>
                                        @elseif($vacancy->pivot->proposal_status === 'pending_hc2_approval')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">Menunggu Approval HC2</span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">Menunggu Approval HC1</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Right Side: Documents & Actions -->
                                <div class="space-y-3">
                                    <!-- Document Info -->
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Dokumen {{ $requiredDocType }}:</span>
                                        <div class="mt-1 flex items-center gap-2">
                                            @if ($document)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ {{ $document->status }}</span>
                                                <a href="{{ route('vacancy-documents.preview', [$vacancy, $document]) }}" class="text-blue-600 hover:text-blue-900 text-xs font-medium" target="_blank">[Preview]</a>
                                                @if (($isDepartmentUser && auth()->user()->department_id === $vacancy->department_id && $document->status === 'pending') || $isTeamHC)
                                                <form action="{{ route('vacancy-documents.destroy', [$vacancy, $document]) }}" method="POST" class="inline action-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium" onclick="return confirm('Yakin ingin menghapus dokumen ini?')">[Hapus]</button>
                                                </form>
                                                @endif
                                            @else
                                                @if (($isDepartmentUser && auth()->user()->department_id === $vacancy->department_id) || $isTeamHC)
                                                    <a href="{{ route('vacancy-documents.upload', $vacancy) }}?mpp_submission_id={{ $mppSubmission->id }}" class="inline-flex items-center gap-1 text-green-600 hover:text-green-800 text-sm font-medium">
                                                        <i class="fas fa-upload"></i> Upload Dokumen
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">✗ Belum ada</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Approval Actions -->
                                    @can('approve-mpp-submission')
                                        @if(auth()->user()->hasRole('team_hc') && $vacancy->pivot->proposal_status === 'pending')
                                        <div class="flex gap-2 pt-2 border-t border-gray-200">
                                            <form action="{{ route('mpp-submissions.approve-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors">Approve (HC1)</button>
                                            </form>
                                            <form action="{{ route('mpp-submissions.reject-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" class="rejection-reason-input">
                                                <button type="submit" class="w-full px-3 py-1.5 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition-colors">Reject</button>
                                            </form>
                                        </div>
                                        @elseif(auth()->user()->hasRole('team_hc_2') && $vacancy->pivot->proposal_status === 'pending_hc2_approval')
                                        <div class="flex gap-2 pt-2 border-t border-gray-200">
                                            <form action="{{ route('mpp-submissions.approve-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors">Approve (HC2)</button>
                                            </form>
                                            <form action="{{ route('mpp-submissions.reject-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" class="rejection-reason-input">
                                                <button type="submit" class="w-full px-3 py-1.5 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition-colors">Reject</button>
                                            </form>
                                        </div>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-folder-open text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">Tidak ada posisi yang diajukan</h3>
                            <p class="text-gray-500">Belum ada posisi yang ditambahkan ke pengajuan MPP ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @endif

            <!-- Approval History -->
            @if ($mppSubmission->approvalHistories->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Riwayat Aktivitas</h2>
                <div class="space-y-4">
                    @foreach ($mppSubmission->approvalHistories->sortByDesc('created_at') as $history)
                    <div class="flex gap-4 pb-4 border-b last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                <span class="text-sm font-medium text-blue-800">{{ substr($history->user->name, 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="flex-grow">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $history->user->name }}
                                <span class="text-gray-600">{{ strtoupper($history->action) }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ $history->created_at->format('d M Y H:i') }}</p>
                            @if ($history->notes)
                            <p class="text-sm text-gray-700 mt-1">{{ $history->notes }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            </div>
        </div>

    </div>
</div>

<script>
function initToasts() {
    const toasts = document.querySelectorAll('.toast-notification');
    toasts.forEach(toast => {
        const progressBar = toast.querySelector('.toast-progress');
        
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        });
        
        if (progressBar) {
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 300); 
        }

        setTimeout(() => {
            toast.classList.remove('translate-x-0', 'opacity-100');
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, 3300);
    });
}

document.addEventListener("DOMContentLoaded", function() {
    initToasts();

    document.addEventListener('submit', async function(e) {
        if (!e.target.classList.contains('action-form')) return;
        
        e.preventDefault();
        const form = e.target;
        
        if (form.action.includes('reject')) {
            const reason = prompt("Masukkan alasan penolakan:");
            if (!reason || reason.trim() === "") return; 
            form.querySelector('.rejection-reason-input').value = reason;
        }

        try {
            // const methodInput = form.querySelector('input[name="_method"]');
            // const fetchMethod = methodInput ? methodInput.value : 'POST';

            // const response = await fetch(form.action, {
            //     method: fetchMethod,
            //     body: new FormData(form),
            //     headers: { 'X-Requested-With': 'XMLHttpRequest' }
            // });

            const fetchMethod = form.method || 'POST';

            const response = await fetch(form.action, {
                method: fetchMethod,
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                document.getElementById('main-content-wrapper').innerHTML = doc.getElementById('main-content-wrapper').innerHTML;
                
                document.getElementById('toast-container').innerHTML = doc.getElementById('toast-container').innerHTML;
                
                initToasts();
            } else {
                form.submit(); 
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            form.submit();
        }
    });
});
</script>
@endsection