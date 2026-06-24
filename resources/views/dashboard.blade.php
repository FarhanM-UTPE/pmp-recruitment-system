@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Recruitment')
@section('page-subtitle', 'Overview rekrutmen dan kandidat')

@push('header-filters')
    <div class="flex items-center gap-2">
        <label for="year" class="text-sm font-medium text-gray-700 hidden sm:block">Tahun:</label>
        <select name="year" id="yearFilter"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[80px]">
            @foreach ($availableYears as $yearOption)
                <option value="{{ $yearOption }}" {{ $year == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
            @endforeach
        </select>
    </div>
@endpush

@push('styles')
    {{-- CSS untuk Kalender dan Modal --}}
    <style>
        .calendar-table {
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .calendar-cell-wrapper {
            border: 1px solid #e5e7eb;
            position: relative;
            padding: 2px;
        }

        .calendar-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 3rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            color: #334155;
            font-weight: 500;
            position: relative;
        }

        .calendar-cell:hover:not(.other-month):not(.selected) {
            background-color: #f1f5f9;
        }

        .calendar-cell.other-month {
            color: #94a3b8;
            cursor: default;
        }

        .calendar-cell.today {
            background-color: #dbeafe;
            color: #2563eb;
            font-weight: 700;
        }

        .calendar-cell.selected {
            background-color: #2563eb;
            color: #fff;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        }

        .calendar-cell.has-event::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: #0891b2;
        }

        .calendar-cell.today.has-event::after {
            background-color: #1d4ed8;
        }

        .calendar-cell.selected.has-event::after {
            background-color: #fff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
    </style>
@endpush

@section('content')
    {{-- Statistik --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6 sm:mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Kandidat</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $stats['total_candidates'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Dalam Proses</p>
                    <p class="text-3xl font-bold text-yellow-500">{{ $stats['candidates_in_process'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-500 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Lulus</p>
                    <p class="text-3xl font-bold text-green-500">{{ $stats['candidates_passed'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Ditolak</p>
                    <p class="text-3xl font-bold text-red-500">{{ $stats['candidates_failed'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Cancel</p>
                    <p class="text-3xl font-bold text-purple-500">{{ $stats['candidates_cancelled'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-ban text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Calendar Section - Only for team_hc --}}
    @if(Auth::user()->hasRole(['team_hc', 'team_hc_2']))
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Kalender Recruitment</h3>
                        <div class="flex items-center gap-2">
                            <button id="prevMonth"
                                class="p-2 w-10 h-10 flex items-center justify-center hover:bg-gray-100 rounded-full transition-colors">
                                <i class="fas fa-chevron-left text-gray-600"></i>
                            </button>
                            <span id="currentMonth"
                                class="text-base font-bold text-gray-800 px-3 min-w-[150px] text-center"></span>
                            <button id="nextMonth"
                                class="p-2 w-10 h-10 flex items-center justify-center hover:bg-gray-100 rounded-full transition-colors">
                                <i class="fas fa-chevron-right text-gray-600"></i>
                            </button>
                        </div>
                    </div>

                    <table class="w-full calendar-table">
                        <thead>
                            <tr>
                                <th
                                    class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-l-0 border-gray-200">
                                    Sen</th>
                                <th class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Sel</th>
                                <th class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Rab</th>
                                <th class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Kam</th>
                                <th class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Jum</th>
                                <th class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Sab</th>
                                <th
                                    class="py-3 text-xs font-semibold text-gray-500 uppercase border-b border-r-0 border-gray-200">
                                    Min</th>
                            </tr>
                        </thead>
                        <tbody id="calendarTableBody"></tbody>
                    </table>
                </div>

                <div class="lg:col-span-1">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-lg font-semibold text-gray-800" id="selectedDateText">Pilih Tanggal</p>
                            <p class="text-sm text-gray-500" id="selectedDateInfo">Klik tanggal untuk melihat jadwal</p>
                        </div>
                        <button id="addEventBtn"
                            class="bg-blue-600 text-white w-10 h-10 rounded-full hover:bg-blue-700 transition-all shadow-md flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <div id="meetingsList" class="space-y-3 max-h-[20rem] overflow-y-auto hidden pr-2"></div>
                    <div id="noMeetings" class="text-center pt-10 text-gray-400">
                        <i class="fas fa-calendar-check text-4xl mb-3"></i>
                        <p class="text-sm font-medium">Tidak ada jadwal</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal untuk Tambah Kegiatan --}}
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Tambah Jadwal Baru</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addEventForm" method="POST" action="{{ route('events.store') }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label for="eventTitle" class="block text-sm font-medium text-gray-700 mb-1">Judul Kegiatan</label>
                    <input type="text" id="eventTitle" name="title" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="eventDate" class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" id="eventDate" name="date" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="eventTime" class="block text-sm font-medium text-gray-700 mb-1">Waktu (Opsional)</label>
                    <input type="time" id="eventTime" name="time"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="eventLocation" class="block text-sm font-medium text-gray-700 mb-1">Lokasi
                        (Opsional)</label>
                    <input type="text" id="eventLocation" name="location"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="eventDescription" class="block text-sm font-medium text-gray-700 mb-1">Deskripsi
                        (Opsional)</label>
                    <textarea id="eventDescription" name="description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" id="cancelBtn"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Batal
                    </button>
                    <button type="submit" id="saveEventBtn"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
        {{-- Recent Candidates --}}
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Kandidat Terbaru</h3>
            <div class="space-y-3">
                @forelse($recent_candidates ?? [] as $candidate)
                    <a href="{{ route('candidates.show', $candidate->id ?? '#') }}"
                        class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $candidate->nama ?? 'Unknown Name' }}</p>
                            <p class="text-sm text-gray-600 truncate">
                                {{ $candidate->applications->first()?->vacancy?->name ?? 'No Position' }}</p>
                            @if($candidate->department)
                                <p class="text-xs text-gray-500">{{ $candidate->department->name ?? 'No Department' }}</p>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-xs text-gray-500">
                                {{ $candidate->created_at ? $candidate->created_at->diffForHumans() : 'Unknown Date' }}
                            </span>
                            @if($candidate->current_stage)
                                <p class="text-xs text-blue-600 font-medium">{{ $candidate->current_stage_display }}</p>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Belum ada kandidat terbaru.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Oldest Candidates --}}
        <div id="oldestCandidatesSection" class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between mb-4 gap-2">
                <h3 class="text-lg font-semibold text-gray-900">Kandidat Terlama</h3>

                {{-- Department Filter specifically for Oldest Candidates --}}
                @if(!empty($departments) && count($departments) > 0)
                    <select id="oldestDeptFilter"
                        class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 max-w-[150px] truncate">
                        <option value="">Semua Dept</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('oldest_dept') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($oldest_candidates ?? [] as $candidate)
                    <a href="{{ route('candidates.show', $candidate->id ?? '#') }}"
                        class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $candidate->nama ?? 'Unknown Name' }}</p>
                            <p class="text-sm text-gray-600 truncate">
                                {{ $candidate->applications->first()?->vacancy?->name ?? 'No Position' }}</p>
                            @if($candidate->department)
                                <p class="text-xs text-gray-500">{{ $candidate->department->name ?? 'No Department' }}</p>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-xs text-gray-500">
                                @if($candidate->last_stage_updated_at)
                                    {{ \Carbon\Carbon::parse($candidate->last_stage_updated_at)->diffForHumans() }}
                                @else
                                    Belum ada stage
                                @endif
                            </span>
                            @if($candidate->current_stage)
                                <p class="text-xs text-blue-600 font-medium">{{ $candidate->current_stage_display }}</p>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Belum ada kandidat terlama.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Process Distribution --}}
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Tahapan</h3>
            <div class="space-y-3">
                @forelse($process_distribution ?? [] as $dist)
                    <a href="{{ route('candidates.index', ['stage' => $dist['stage'] ?? '']) }}"
                        class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    {{ $dist['display_name'] ?? 'Unknown Stage' }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ ($dist['count'] ?? 0) == 1 ? '1 kandidat' : ($dist['count'] ?? 0) . ' kandidat' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-blue-600 text-lg">{{ $dist['count'] ?? 0 }}</span>
                                <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-chart-pie text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Data distribusi tidak tersedia.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Gender Distribution --}}
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Gender</h3>
            <div class="space-y-3">
                @forelse($gender_distribution ?? [] as $gender)
                    <div class="block p-3 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    {{ $gender->jk == 'L' ? 'Laki-laki' : ($gender->jk == 'P' ? 'Perempuan' : 'Tidak Diketahui') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-blue-600 text-lg">{{ $gender->count ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-venus-mars text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Data distribusi gender tidak tersedia.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- University Distribution --}}
        <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Universitas (Top 10)</h3>
            <div class="space-y-3">
                @forelse($university_distribution ?? [] as $university)
                    <div class="block p-3 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    {{ $university->perguruan_tinggi ?? 'Tidak Diketahui' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-blue-600 text-lg">{{ $university->count ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Data distribusi universitas tidak tersedia.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Monthly Summary --}}
    <div id="monthlySummarySection" class="bg-white rounded-xl p-5 mt-6 sm:mt-8 shadow-sm border border-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-4 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Ringkasan Per Bulan</h3>
            <form id="monthlySummaryForm" method="GET" action="{{ route('dashboard') }}#monthlySummarySection"
                class="flex flex-wrap items-center gap-2">
                <label for="summary_month" class="sr-only">Bulan Ringkasan</label>
                <select name="summary_month" id="summary_month" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}" {{ $summaryMonth == $month ? 'selected' : '' }}>
                            {{ Carbon\Carbon::create()->month($month)->isoFormat('MMMM') }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="summary_year" id="summary_year" value="{{ $year }}" />
                <input type="hidden" name="year" value="{{ $year }}" />
                {{-- Tombol submit tidak lagi diperlukan --}}
            </form>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h4 class="text-base font-semibold text-gray-700">Menampilkan Ringkasan untuk:
                {{ $monthlySummary['month_name'] }}</h4>
            <a href="{{ $monthlySummary['filter_url'] }}"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm flex items-center gap-2">
                <i class="fas fa-search"></i>
                Lihat Kandidat
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 text-center">
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-3xl font-bold text-gray-800">{{ number_format($monthlySummary['total_applications']) }}</p>
                <p class="text-sm text-gray-500">Total Aplikasi Masuk</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-3xl font-bold text-green-600">{{ number_format($monthlySummary['total_hired']) }}</p>
                <p class="text-sm text-gray-500">Total Diterima</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-3xl font-bold text-cyan-600">{{ $monthlySummary['conversion_rate'] }}%</p>
                <p class="text-sm text-gray-500">Tingkat Konversi</p>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const oldestDeptFilter = document.getElementById('oldestDeptFilter');
            if (oldestDeptFilter) {
                oldestDeptFilter.addEventListener('change', function () {
                    const currentUrl = new URL(window.location);
                    if (this.value) {
                        currentUrl.searchParams.set('oldest_dept', this.value);
                    } else {
                        currentUrl.searchParams.delete('oldest_dept');
                    }
                    currentUrl.hash = 'oldestCandidatesSection';
                    window.location.href = currentUrl.toString();
                });
            }

            const yearFilter = document.getElementById('yearFilter');
            if (yearFilter) {
                yearFilter.addEventListener('change', function () {
                    const currentUrl = new URL(window.location);
                    currentUrl.searchParams.set('year', this.value);
                    window.location.href = currentUrl.toString();
                });
            }

            const summaryForm = document.getElementById('monthlySummaryForm');
            if (summaryForm) {
                const summaryInputs = summaryForm.querySelectorAll('select');
                summaryInputs.forEach(input => {
                    input.addEventListener('change', () => {
                        summaryForm.submit();
                    });
                });
            }

            const calendarTableBody = document.getElementById('calendarTableBody');
            if (!calendarTableBody) return;

            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            let currentDate = new Date();
            const yearFromUrl = new URLSearchParams(window.location.search).get('year');
            if (yearFromUrl) currentDate.setFullYear(parseInt(yearFromUrl, 10));
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            let selectedDateKey = null;
            const eventsByDate = {};

            function updateCalendar() {
                if (!calendarTableBody) return;
                document.getElementById('currentMonth').textContent = `${months[currentMonth]} ${currentYear}`;
                calendarTableBody.innerHTML = '';
                const firstDay = new Date(currentYear, currentMonth, 1);
                const startDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
                let dayCounter = 1;
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                for (let i = 0; i < 6; i++) {
                    let row = document.createElement('tr');
                    for (let j = 0; j < 7; j++) {
                        let cellWrapper = document.createElement('td');
                        cellWrapper.className = 'calendar-cell-wrapper';
                        let cellContent = document.createElement('div');
                        cellContent.className = 'calendar-cell';
                        if (i === 0 && j < startDayIndex) {
                            cellContent.classList.add('other-month');
                            cellContent.textContent = prevMonthLastDay - startDayIndex + j + 1;
                        } else if (dayCounter > daysInMonth) {
                            cellContent.classList.add('other-month');
                            cellContent.textContent = dayCounter - daysInMonth;
                            dayCounter++;
                        } else {
                            const dateKey = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayCounter).padStart(2, '0')}`;
                            cellContent.textContent = dayCounter;
                            cellContent.dataset.dateKey = dateKey;
                            const cellDate = new Date(currentYear, currentMonth, dayCounter);
                            if (cellDate.getTime() === today.getTime()) cellContent.classList.add('today');
                            if (eventsByDate[dateKey]) cellContent.classList.add('has-event');
                            cellContent.addEventListener('click', () => selectDate(dateKey, cellContent));
                            dayCounter++;
                        }
                        cellWrapper.appendChild(cellContent);
                        row.appendChild(cellWrapper);
                    }
                    calendarTableBody.appendChild(row);
                }
            }

            async function fetchAndMarkEvents() {
                try {
                    const response = await fetch('{{ route("events.calendar") }}');
                    if (!response.ok) throw new Error('Failed to fetch events');
                    const allEventsData = await response.json();
                    processEvents(allEventsData);
                    markEventDays();
                    selectTodayIfVisible();
                } catch (error) {
                    console.error('Error fetching calendar data:', error);
                }
            }

            function processEvents(events) {
                for (const key in eventsByDate) { delete eventsByDate[key]; }
                if (!Array.isArray(events)) return;
                events.forEach(item => {
                    if (!item || !item.date) return;
                    // Directly use item.date as dateKey since it's already in YYYY-MM-DD format from backend
                    const dateKey = item.date;
                    if (!eventsByDate[dateKey]) eventsByDate[dateKey] = [];
                    eventsByDate[dateKey].push(item);
                    console.log(`Processing event for dateKey: ${dateKey}`, item); // Debugging line
                });
            }

            function markEventDays() {
                document.querySelectorAll('.calendar-cell.has-event').forEach(c => c.classList.remove('has-event'));
                for (const dateKey in eventsByDate) {
                    const cell = document.querySelector(`[data-date-key="${dateKey}"]`);
                    if (cell) cell.classList.add('has-event');
                }
            }

            function initialize() {
                updateCalendar();
                fetchAndMarkEvents();
                document.getElementById('prevMonth').addEventListener('click', () => navigateMonth(-1));
                document.getElementById('nextMonth').addEventListener('click', () => navigateMonth(1));
            }

            initialize();

            // Other functions (selectDate, filterMeetingsByDate, etc.) remain the same
            function selectDate(dateKey, cellElement) {
                document.querySelectorAll('.calendar-cell.selected').forEach(c => c.classList.remove('selected'));
                if (cellElement) cellElement.classList.add('selected');
                selectedDateKey = dateKey;
                const [year, month, day] = dateKey.split('-').map(Number);
                document.getElementById('selectedDateText').textContent = `${new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(new Date(year, month - 1, day))}, ${day} ${months[month - 1]}`;
                filterMeetingsByDate(dateKey);
            }

            function filterMeetingsByDate(dateKey) {
                const meetingsList = document.getElementById('meetingsList');
                const noMeetings = document.getElementById('noMeetings');
                meetingsList.innerHTML = '';
                const eventsForDate = eventsByDate[dateKey] || [];
                if (eventsForDate.length > 0) {
                    noMeetings.classList.add('hidden');
                    meetingsList.classList.remove('hidden');
                    eventsForDate.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    eventsForDate.forEach(item => {
                        const isCustom = item.is_custom;
                        const title = isCustom ? item.title : item.title;
                        const subtitle = isCustom ? item.description : item.description;
                        const link = isCustom ? 'javascript:void(0);' : item.url;
                        const itemHtml = `
                        <div class="flex items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg group hover:bg-slate-100 transition-colors">
                            <a href="${link}" class="flex-1 min-w-0 ${isCustom ? 'cursor-default' : ''}">
                                <p class="font-semibold text-slate-800 truncate text-sm">${title}</p>
                                <p class="text-xs text-slate-500 truncate">${subtitle}</p>
                            </a>
                            ${isCustom ? `<button type="button" data-event-id="${item.id}" class="delete-event-btn text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100"><i class="fas fa-trash-alt"></i></button>` : `<i class="fas fa-chevron-right text-gray-400"></i>`}
                        </div>`;
                        meetingsList.insertAdjacentHTML('beforeend', itemHtml);
                    });

                    // Add event listeners for delete buttons
                    document.querySelectorAll('.delete-event-btn').forEach(button => {
                        button.addEventListener('click', async function () {
                            const eventId = this.dataset.eventId.replace('custom_', ''); // Remove 'custom_' prefix
                            if (!confirm('Apakah Anda yakin ingin menghapus jadwal ini?')) {
                                return;
                            }

                            try {
                                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                                const response = await fetch(`/events/${eventId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': csrfToken
                                    }
                                });

                                const result = await response.json();

                                if (response.ok && result.success) {
                                    alert('Jadwal berhasil dihapus!');
                                    fetchAndMarkEvents(); // Re-fetch and update calendar
                                } else {
                                    let errorMessage = 'Gagal menghapus jadwal.';
                                    if (result.message) {
                                        errorMessage = result.message;
                                    }
                                    alert(errorMessage);
                                }
                            } catch (error) {
                                console.error('Error deleting event:', error);
                                alert('Terjadi kesalahan jaringan atau server saat menghapus jadwal.');
                            }
                        });
                    });

                } else {
                    meetingsList.classList.add('hidden');
                    noMeetings.classList.remove('hidden');
                }
            }

            function navigateMonth(direction) {
                currentMonth += direction;
                if (currentMonth < 0) { currentMonth = 11; currentYear--; }
                if (currentMonth > 11) { currentMonth = 0; currentYear++; }
                updateCalendar();
                markEventDays(); // Re-mark events on month change
                resetMeetingList();
            }

            function resetMeetingList() {
                document.getElementById('meetingsList').classList.add('hidden');
                document.getElementById('noMeetings').classList.remove('hidden');
                document.getElementById('selectedDateText').textContent = 'Pilih Tanggal';
                document.getElementById('selectedDateInfo').textContent = 'Klik tanggal untuk melihat jadwal';
                selectedDateKey = null;
            }

            function selectTodayIfVisible() {
                const todayCell = document.querySelector('.calendar-cell.today');
                if (todayCell) {
                    const today = new Date();
                    if (currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
                        todayCell.click();
                    } else {
                        resetMeetingList();
                    }
                } else {
                    resetMeetingList();
                }
            }

            // Modal Logic
            const addEventModal = document.getElementById('addEventModal');
            const addEventBtn = document.getElementById('addEventBtn');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const addEventForm = document.getElementById('addEventForm');
            const eventDateInput = document.getElementById('eventDate');

            if (addEventBtn) {
                addEventBtn.addEventListener('click', function () {
                    addEventForm.reset(); // Reset form fields
                    if (selectedDateKey) {
                        eventDateInput.value = selectedDateKey; // Set date to selected calendar date
                    } else {
                        const today = new Date();
                        eventDateInput.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                    }
                    addEventModal.classList.add('show');
                });
            }

            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function () {
                    addEventModal.classList.remove('show');
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    addEventModal.classList.remove('show');
                });
            }

            // Close modal when clicking outside
            if (addEventModal) {
                addEventModal.addEventListener('click', function (e) {
                    if (e.target === addEventModal) {
                        addEventModal.classList.remove('show');
                    }
                });
            }

            // Form Submission Logic
            if (addEventForm) {
                addEventForm.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    try {
                        const response = await fetch(this.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            alert('Jadwal berhasil disimpan!');
                            addEventModal.classList.remove('show');
                            fetchAndMarkEvents(); // Re-fetch and update calendar
                        } else {
                            let errorMessage = 'Gagal menyimpan jadwal.';
                            if (result.errors) {
                                errorMessage += '\n' + Object.values(result.errors).flat().join('\n');
                            } else if (result.message) {
                                errorMessage = result.message;
                            }
                            alert(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error submitting form:', error);
                        alert('Terjadi kesalahan jaringan atau server.');
                    }
                });
            }

        });
    </script>
@endpush