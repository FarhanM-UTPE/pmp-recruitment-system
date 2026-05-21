{{-- /resources/views/candidates/index.blade.php --}}

@extends('layouts.app')

@section('title', 'Kandidat')
@section('page-title', 'Manajemen Kandidat')
@section('page-subtitle', 'Kelola dan pantau kandidat recruitment')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <p class="text-gray-600">@yield('page-subtitle')</p>
    <div class="flex items-center gap-4">
        
        @can('create-candidates')
            <a href="{{ route('candidates.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors">
                <i class="fas fa-plus text-sm"></i>
                <span>Tambah Kandidat</span>
            </a>
        @endcan
        @can('view-candidates')
            <a href="{{ route('candidates.export') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 transition-colors">
                <i class="fas fa-file-excel text-sm"></i>
                <span>Export Excel</span>
            </a>
        @endcan
    </div>
</div>

@canany(['view-candidates', 'view-own-department-candidates'])
    {{-- This scope initializes and contains all Alpine.js logic for this page --}}
    <div x-data="candidatesPage()" x-init="init(); $store.candidates.selectedIds = [];" id="candidates-scope" x-cloak>

        

        <div x-show="selectedCount > 0" id="bulk-operations" class="bg-blue-50 border border-blue-200 px-4 sm:px-6 py-4 mb-4 rounded-lg" x-transition>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-blue-900">
                        <span x-text="selectedCount"></span> kandidat dipilih
                    </span>
                    <button @click="clearSelection" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>
                
                <div class="flex items-center gap-2">
                    
                    
                    <button @click="showBulkExportModal = true" class="bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 text-sm flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                    </button>
                    
                    @can('delete-candidates')
                        <button @click="confirmBulkDelete" class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm flex items-center gap-2">
                            <i class="fas fa-trash"></i>
                            <span>Hapus</span>
                        </button>
                    @endcan
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 mb-6">
            <form method="GET" x-ref="filterForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(request()->query() as $key => $value)
                    @if(!in_array($key, ['search', 'year', 'status', 'department_id', 'source', 'stage', 'type']) && !is_array($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari Kandidat</label>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari nama, email, atau posisi..."
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           @keydown.enter.prevent="$refs.filterForm.submit()">
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                    <select name="year" id="year" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('year') ? 'selected' : '' }}>Semua Tahun</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('status') ? 'selected' : '' }}>Semua Status</option>
                        @foreach($statuses as $value => $display)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $display }}</option>
                        @endforeach
                    </select>
                </div>
                @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Departemen</label>
                    <select name="department_id" id="department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('department_id') ? 'selected' : '' }}>Semua Department</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select name="source" id="source" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('source') ? 'selected' : '' }}>Semua Source</option>
                        @foreach($sources as $source)
                            <option value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="stage" class="block text-sm font-medium text-gray-700 mb-1">Tahapan</label>
                    <select name="stage" id="stage" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('stage') ? 'selected' : '' }}>Semua Tahapan</option>
                        @foreach($stages as $stage)
                            <option value="{{ $stage->value }}" {{ request('stage') == $stage->value ? 'selected' : '' }}>{{ Str::title(str_replace('_', ' ', $stage->name)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                    <select name="type" id="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="" {{ !request('type') ? 'selected' : '' }}>Semua Tipe</option>
                        <option value="duplicate" {{ request('type') == 'duplicate' ? 'selected' : '' }}>Duplicate</option>
                        <option value="non-duplicate" {{ request('type') == 'non-duplicate' ? 'selected' : '' }}>Non-Duplicate</option>
                        <option value="organic" {{ request('type') == 'organic' ? 'selected' : '' }}>Organik</option>
                        <option value="non-organic" {{ request('type') == 'non-organic' ? 'selected' : '' }}>Non-Organik</option>
                    </select>
                </div>
                <div class="flex items-end justify-end mt-4 md:mt-0">
                    <a href="{{ route('candidates.index') }}" class="w-full md:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm text-center">Reset Filter</a>
                </div>
            </form>
        </div>

        <div class="flex-1 p-4 sm:p-6">
            @if(request('vacancy_id'))
                <div class="mb-4">
                    @php
                        $selectedVacancy = $activeVacancies->firstWhere('id', request('vacancy_id'));
                    @endphp
                    @if($selectedVacancy)
                        <p class="text-lg font-semibold text-gray-800">Menampilkan Statistik untuk Posisi: <span class="text-blue-600">{{ $selectedVacancy->name }}</span></p>
                    @endif
                </div>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6 sm:mb-8">
                <!-- <a href="{{ route('candidates.index', request()->except(['status', 'type', 'page'])) }}" class="block"> -->
                <!-- <span> -->
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
                <!-- </span> -->
                <!-- <a href="{{ route('candidates.index', array_merge(request()->except('page'), ['status' => 'ON_PROCESS'])) }}" class="block"> -->
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
                <!-- </a> -->
                <!-- <a href="{{ route('candidates.index', array_merge(request()->except('page'), ['status' => 'HIRED'])) }}" class="block"> -->
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
                <!-- </a> -->
                <!-- <a href="{{ route('candidates.index', array_merge(request()->except('page'), ['status' => 'FAILED'])) }}" class="block"> -->
                    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Tidak Lulus</p>
                                <p class="text-3xl font-bold text-red-500">{{ $stats['candidates_failed'] ?? 0 }}</p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-500 text-xl"></i>
                            </div>
                        </div>
                    </div>
                <!-- </a> -->
                <!-- <a href="{{ route('candidates.index', array_merge(request()->except('page'), ['status' => 'CANCEL'])) }}" class="block"> -->
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
                <!-- </a> -->
            </div>

            <div class="mb-6 bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-md font-semibold text-gray-800">Active Openings</h3>
                    @if(request('vacancy_id'))
                    <a href="{{ route('candidates.index', request()->except(['vacancy_id', 'page'])) }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-times mr-1"></i>Clear Filter
                    </a>
                    @endif
                </div>
                
                <div class="w-full">
                    <select 
                        onchange="if(this.value) window.location.href=this.value" 
                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors"
                    >
                        <option value="{{ route('candidates.index', request()->except(['vacancy_id', 'page'])) }}">
                            Semua Lowongan
                        </option>
                        
                        @forelse($activeVacancies->sortBy('name') as $vacancy)
                            @php
                                $mppSubmission = $vacancy->mppSubmissions->first();
                                $neededCount = $mppSubmission ? $mppSubmission->pivot->needed_count : 0;
                                $isActive = request('vacancy_id') == $vacancy->id;
                                $url = route('candidates.index', array_merge(request()->except('page'), ['vacancy_id' => $vacancy->id]));
                            @endphp
                            <option value="{{ $url }}" {{ $isActive ? 'selected' : '' }}>
                                {{ $vacancy->name }}
                            </option>
                        @empty
                            <option value="" disabled>Tidak ada lowongan yang aktif saat ini.</option>
                        @endforelse
                    </select>
                </div>
            </div>

            <div class="mb-6">
                
            </div>



            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        @if($type === 'duplicate')
                            Daftar Kandidat Duplicate
                            <span class="text-sm font-normal text-orange-600">(Mendaftar 2 kali dalam 1 tahun)</span>
                        @else
                            Daftar Kandidat
                            @if(request('vacancy_id'))
                                @php
                                    $selectedVacancy = $activeVacancies->firstWhere('id', request('vacancy_id'));
                                @endphp
                                @if($selectedVacancy)
                                    <span class="text-sm font-normal text-blue-600">- Posisi: {{ $selectedVacancy->name }}</span>
                                @endif
                            @endif
                            @if(request('status'))
                                <span class="text-sm font-normal text-blue-600">- Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}</span>
                            @endif
                            @if(request('search'))
                                <span class="text-sm font-normal text-gray-600">- Pencarian: "{{ request('search') }}"</span>
                            @endif
                        @endif
                    </h3>
                </div>
                
                @if($applications->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <label for="select-all-checkbox" class="sr-only">Select all candidates</label>
                                        <input type="checkbox"
                                               id="select-all-checkbox"
                                               name="select_all"
                                               @click="toggleAll($event.target.checked)"
                                               :checked="allVisibleSelected"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kandidat</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posisi</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun MPP</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahapan</th>
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    @if($type === 'duplicate')
                                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant ID</th>
                                    @endif
                                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky right-0 bg-gray-50">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($applications as $application)
                                    @php
                                        $candidate = $application->candidate;
                                        $latestStage = $application->stages->sortByDesc('id')->first();
                                        $isDuplicate = $duplicateCandidateIds->contains($candidate->id);
                                    @endphp
                                    @if(Auth::user()->hasRole('department') && $candidate->department_id !== Auth::user()->department_id)
                                        @continue
                                    @endif
                                    {{-- Highlight for duplicate candidates --}}
                                    <tr x-data="{ open: false }" @click.away="open = false" :class="{ 'relative z-10': open }" class="hover:bg-gray-50 transition-colors {{ $isDuplicate ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                            <label for="candidate_checkbox_{{ $candidate->id }}" class="sr-only">Select {{ $candidate->nama }}</label>
                                            <input type="checkbox"
                                                   id="candidate_checkbox_{{ $candidate->id }}"
                                                   name="candidate_ids[]"
                                                   :value="{{ $candidate->id }}"
                                                   x-model="$store.candidates.selectedIds"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-9 sm:w-10 h-9 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 {{ $isDuplicate ? 'bg-yellow-200' : '' }} {{ $candidate->is_inactive ? 'ring-2 ring-red-500' : '' }}">
                                                    <span class="text-sm font-medium {{ $isDuplicate ? 'text-yellow-700' : 'text-blue-600' }}">{{ substr($candidate->nama, 0, 2) }}</span>
                                                </div>
                                                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                                                    <div class="text-sm font-medium text-gray-900 truncate">
                                                        {{ $candidate->nama }}
                                                        @if($isDuplicate)
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-copy mr-1"></i>
                                                                Duplicate
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm text-gray-500 truncate">{{ $candidate->alamat_email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $application->vacancy ? $application->vacancy->name : 'N/A' }}</div>
                                            @if($application->internal_position)
                                                <div class="text-sm text-gray-500">{{ $application->internal_position }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                @if ($candidate->department)
                                                    {{ $candidate->department->name }}
                                                @else
                                                    N/A
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $application->mpp_year ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $candidate->source }}
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                             @if($application->overall_status == 'LULUS')
                                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Lulus</span>
                                            @elseif($application->overall_status == 'DITOLAK')
                                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Tidak Lulus</span>
                                            @elseif($application->overall_status == 'CANCEL')
                                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Cancel</span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Proses</span>
                                            @endif
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($latestStage)
                                                {{ \App\Models\Candidate::formatStageName($latestStage->stage_name) }}
                                            @else
                                                Psikotest
                                            @endif
                                        </td>
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $application->created_at->format('d M Y') }}
                                        </td>
                                        @if($type === 'duplicate')
                                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-700">{{ $candidate->applicant_id }}</div>
                                            </td>
                                        @endif
                                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-medium sticky right-0 bg-white hover:bg-gray-50 relative">
                                            <div class="flex items-center gap-1 sm:gap-2">
                                                @can('show-candidates')
                                                    <a href="{{ route('candidates.show', [$candidate, 'application_id' => $application->id]) }}" class="text-blue-600 hover:text-blue-900 p-1" title="Lihat Detail">
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </a>
                                                @endcan
                                                @can('import-excel')
                                                       <a href="{{ route('candidates.edit', $candidate) }}" 
       class="text-indigo-600 hover:text-indigo-900 p-1 
              hover:bg-indigo-50 rounded transition-colors" 
       title="Edit Kandidat" 
       aria-label="Edit Kandidat">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" 
             stroke="currentColor" stroke-width="1.5" 
             xmlns="http://www.w3.org/2000/svg">
            <path d="M2 21.154C2 16.689 6.07 15 10 15c1.283 0 2.581.18 3.747.578" 
                  stroke-linecap="round"/>
            <path d="M14.308 7.237c0 2.51-.287 5.302-4.308 5.302s-4.308-2.792-4.308-5.302C5.692 4.727 7.621 2.692 10 2.692s4.308 2.035 4.308 4.545z"/>
            <path d="M14.421 18.32l4.974-5.898.65-.173 1.77 1.492-.061.671-4.974 5.898-2.601.69.242-2.68z"/>
            <path d="M18.464 13.383l2.421 2.152"/>
        </svg>
    </a>
                                                @endcan

                                                @canany(['import-excel', 'delete-candidates'])
                                                <!-- Dropdown for other actions -->
                                                <div class="relative">
                                                    <button @click="open = !open" class="text-gray-500 hover:text-gray-700 p-1 rounded-full focus:outline-none">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div x-show="open"
                                                         x-transition:enter="transition ease-out duration-100"
                                                         x-transition:enter-start="transform opacity-0 scale-95"
                                                         x-transition:enter-end="transform opacity-100 scale-100"
                                                         x-transition:leave="transition ease-in duration-75"
                                                         x-transition:leave-start="transform opacity-100 scale-100"
                                                         x-transition:leave-end="transform opacity-0 scale-95"
                                                         class="absolute right-0 top-full mt-2 w-48 bg-white rounded-md shadow-lg z-50 border"
                                                         style="display: none;">
                                                        <div class="py-1">
                                                            @can('import-excel')
                                                                <form method="POST" action="{{ route('candidates.switchType', $candidate) }}" class="w-full text-left" onsubmit="return confirm('Yakin ingin memindahkan tipe kandidat ini?')">
                                                                    @csrf
                                                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                                        <i class="fas fa-exchange-alt fa-fw"></i>
                                                                        <span>Pindahkan Tipe</span>
                                                                    </button>
                                                                </form>
                                                            @endcan
                                                            @can('delete-candidates')
                                                                <form method="POST" action="{{ route('candidates.destroy', $candidate) }}" class="w-full text-left" onsubmit="return confirm('Yakin ingin menghapus kandidat ini?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                                        <i class="fas fa-trash fa-fw"></i>
                                                                        <span>Hapus</span>
                                                                    </button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                </div>
                                                @endcanany
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                        {{ $applications->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        @if($type === 'duplicate')
                            <i class="fas fa-copy text-5xl sm:text-6xl text-orange-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada kandidat duplikat</h3>
                            <p class="text-gray-500 mb-6">Saat ini tidak ada kandidat yang mendaftar lebih dari satu kali.</p>
                        @else
                            <i class="fas fa-users text-5xl sm:text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                @if(request('search') || request('status'))
                                    Tidak ada hasil yang ditemukan
                                @else
                                    Belum ada aplikasi kandidat
                                @endif
                            </h3>
                            <p class="text-gray-500 mb-6">
                                @if(request('search') || request('status'))
                                    Coba ubah kriteria pencarian atau filter untuk melihat hasil lainnya.
                                @else
                                    Mulai dengan menambahkan kandidat baru atau import dari Excel.
                                @endif
                            </p>
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                                @if(request('search') || request('status'))
                                    <a href="{{ route('candidates.index', ['type' => $type]) }}" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                        Reset Filter
                                    </a>
                                @endif
                                @can('create-candidates')
                                    <a href="{{ route('candidates.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                        Tambah Kandidat
                                    </a>
                                @endcan
                                
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @can('edit-candidates')
            <div x-show="showBulkUpdateModal" @keydown.escape.window="showBulkUpdateModal = false" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" x-cloak>
                <div @click.away="showBulkUpdateModal = false" class="bg-white rounded-xl max-w-md w-full p-6" x-show="showBulkUpdateModal" x-transition>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Update Status Kandidat</h3>
                        <button @click="showBulkUpdateModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <form @submit.prevent="submitBulkUpdate">
                        <div class="space-y-4">
                            <div>
                                <label for="bulk_stage" class="block text-sm font-medium text-gray-700 mb-2">Stage</label>
                                <select id="bulk_stage" name="bulk_stage" x-model="updateForm.stage" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Pilih Stage</option>
                                    <option value="psikotes">Psikotes</option>
                                    <option value="interview_hc">Interview HC</option>
                                    <option value="interview_user">Interview User</option>
                                    <option value="interview_bod">Interview BOD</option>
                                    <option value="offering_letter">Offering Letter</option>
                                    <option value="mcu">Medical Check Up</option>
                                    <option value="hiring">Hiring</option>
                                </select>
                            </div>
                            <div>
                                <label for="bulk_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select id="bulk_status" name="bulk_status" x-model="updateForm.status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Pilih Status</option>
                                    <option value="LULUS">LULUS</option>
                                    <option value="TIDAK LULUS">TIDAK LULUS</option>
                                    <option value="DIPERTIMBANGKAN">DIPERTIMBANGKAN</option>
                                </select>
                            </div>
                            <div>
                                <label for="bulk_notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                                <textarea id="bulk_notes" name="bulk_notes" x-model="updateForm.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 mt-6">
                            <button type="button" @click="showBulkUpdateModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="showBulkMoveModal" @keydown.escape.window="showBulkMoveModal = false" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" x-cloak>
                <div @click.away="showBulkMoveModal = false" class="bg-white rounded-xl max-w-md w-full p-6" x-show="showBulkMoveModal" x-transition>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Pindah Stage Kandidat</h3>
                        <button @click="showBulkMoveModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <form @submit.prevent="submitBulkMove">
                        <div class="space-y-4">
                            <div>
                                <label for="bulk_move_target_stage" class="block text-sm font-medium text-gray-700 mb-2">Target Stage</label>
                                <select id="bulk_move_target_stage" name="bulk_move_target_stage" x-model="moveForm.targetStage" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Pilih Stage</option>
                                    <option value="psikotes">Psikotes</option>
                                    <option value="interview_hc">Interview HC</option>
                                    <option value="interview_user">Interview User</option>
                                    <option value="interview_bod">Interview BOD</option>
                                    <option value="offering_letter">Offering Letter</option>
                                    <option value="mcu">Medical Check Up</option>
                                    <option value="hiring">Hiring</option>
                                </select>
                            </div>
                            <div>
                                <label for="bulk_move_notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                                <textarea id="bulk_move_notes" name="bulk_move_notes" x-model="moveForm.notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 mt-6">
                            <button type="button" @click="showBulkMoveModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">Pindah Stage</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div x-show="showBulkExportModal" @keydown.escape.window="showBulkExportModal = false" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="showBulkExportModal = false" class="bg-white rounded-xl max-w-lg w-full p-6" x-show="showBulkExportModal" x-transition>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Export Kandidat</h3>
                    <button @click="showBulkExportModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <form @submit.prevent="submitBulkExport">
                    <div class="space-y-4">
                        <div>
                            <label for="export_format" class="block text-sm font-medium text-gray-700 mb-2">Format Export</label>
                            <select id="export_format" name="export_format" x-model="exportForm.format" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                                <option value="pdf">PDF (.pdf)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kolom yang Diexport</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <input type="checkbox" id="export_col_nama" name="export_columns[]" value="nama" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_nama" class="ml-2 text-sm">Nama</label>
                                </div>
                                <div>
                                    <input type="checkbox" id="export_col_vacancy" name="export_columns[]" value="vacancy" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_vacancy" class="ml-2 text-sm">Posisi</label>
                                </div>
                                <div>
                                    <input type="checkbox" id="export_col_department" name="export_columns[]" value="department" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_department" class="ml-2 text-sm">Departemen</label>
                                </div>
                                <div>
                                    <input type="checkbox" id="export_col_current_stage" name="export_columns[]" value="current_stage" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_current_stage" class="ml-2 text-sm">Stage</label>
                                </div>
                                <div>
                                    <input type="checkbox" id="export_col_overall_status" name="export_columns[]" value="overall_status" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_overall_status" class="ml-2 text-sm">Status</label>
                                </div>
                                <div>
                                    <input type="checkbox" id="export_col_created_at" name="export_columns[]" value="created_at" x-model="exportForm.columns" class="rounded">
                                    <label for="export_col_created_at" class="ml-2 text-sm">Tanggal Daftar</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button type="button" @click="showBulkExportModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('candidates', {
            selectedIds: [],
        });
    });

    function candidatesPage() {
        return {
            selectedCount: 0,
            allVisibleSelected: false,
            showBulkUpdateModal: false,
            showBulkMoveModal: false,
            showBulkExportModal: false,
            updateForm: {
                stage: '',
                status: '',
                notes: '',
            },
            moveForm: {
                targetStage: '',
                notes: '',
            },
            exportForm: {
                format: 'excel',
                columns: ['nama', 'vacancy', 'department', 'current_stage', 'overall_status', 'created_at'],
            },
            init() {
                this.$watch('$store.candidates.selectedIds', (newValue) => {
                    this.selectedCount = newValue.length;
                    this.updateSelectAllCheckbox();
                });
            },
            updateSelectAllCheckbox() {
                const visibleIds = this.getVisibleCandidateIds();
                const selectedVisible = visibleIds.filter(id => this.$store.candidates.selectedIds.includes(id));
                this.allVisibleSelected = visibleIds.length > 0 && selectedVisible.length === visibleIds.length;
            },
            toggleAll(checked) {
                const visibleIds = this.getVisibleCandidateIds();
                if (checked) {
                    this.$store.candidates.selectedIds = [...new Set([...this.$store.candidates.selectedIds, ...visibleIds])];
                } else {
                    this.$store.candidates.selectedIds = this.$store.candidates.selectedIds.filter(id => !visibleIds.includes(id));
                }
            },
            getVisibleCandidateIds() {
                return Array.from(document.querySelectorAll('input[name="candidate_ids[]"]')).map(el => parseInt(el.value));
            },
            clearSelection() {
                this.$store.candidates.selectedIds = [];
            },
            confirmBulkSwitchType() {
                // Implement confirmation logic
                console.log('Switching type for:', this.$store.candidates.selectedIds);
            },
            confirmBulkDelete() {
                // Implement confirmation logic
                console.log('Deleting:', this.$store.candidates.selectedIds);
            },
            submitBulkUpdate() {
                // Implement bulk update logic
                console.log('Updating:', this.$store.candidates.selectedIds, this.updateForm);
            },
            submitBulkMove() {
                // Implement bulk move logic
                console.log('Moving:', this.$store.candidates.selectedIds, this.moveForm);
            },
            submitBulkExport() {
                // Implement bulk export logic
                console.log('Exporting:', this.$store.candidates.selectedIds, this.exportForm);
            },
        };
    }
</script>
@endpush