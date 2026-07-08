@extends('layouts.app')

@section('title', 'Detail Kandidat - ' . $candidate->nama)

@section('page-title', 'Detail Kandidat')

@section('content')

    <!-- Header Detail Kandidat -->
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <a href="{{ route('candidates.index') }}"
                    class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h2 class="text-base sm:text-lg font-bold text-gray-900 truncate">{{ $candidate->nama }}</h2>
                    <p class="text-xs text-gray-500 truncate">{{ $candidate->applicant_id }} •
                        {{ $candidate->alamat_email }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                @can('edit-candidates')
                    <a href="{{ route('candidates.edit', $candidate) }}"
                        class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors duration-200">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                            <path fill-rule="evenodd"
                                d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="hidden sm:inline">Edit</span>
                    </a>
                @endcan

                @can('delete-candidates')
                    <form method="POST" action="{{ route('candidates.destroy', $candidate) }}" class="inline"
                        onsubmit="return confirm('Yakin ingin menghapus kandidat ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors duration-200">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span class="hidden sm:inline">Hapus</span>
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @php
        // The primary application is passed from the controller, or null if no applications
        // This will be the default active application for the timeline view.
        $primaryApplicationId = isset($primaryApplication) && $primaryApplication ? $primaryApplication->id : null;
        $primaryApplicationVacancyName =
            isset($primaryApplication) && $primaryApplication && $primaryApplication->vacancy
                ? $primaryApplication->vacancy->name
                : 'N/A';
    @endphp

    @can('show-candidates')
        <div x-data="candidateDetail(
            {{ json_encode($allTimelines) }},
            {{ $primaryApplicationId ?? 'null' }},
            {{ json_encode($primaryApplicationVacancyName) }}
        )" class="space-y-6">

            <!-- Modal untuk Update Stage -->
            <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"
                        @click="closeModal()"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                        <form @submit.prevent="submitForm()" id="stageUpdateForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div
                                        class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900"
                                            x-text="'Update ' + selectedStage"></h3>
                                        <div class="mt-4 space-y-4">
                                            <template x-if="!canEditResult && nextStageScheduledDate">
                                                <div class="bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
                                                    <p class="text-xs text-amber-800">
                                                        <strong>Info:</strong> Stage selanjutnya sudah ada dengan tanggal <span
                                                            x-text="formatDate(nextStageScheduledDate)"></span>.
                                                        Anda hanya dapat mengubah tanggal stage ini, tidak dapat mengubah hasil.
                                                    </p>
                                                </div>
                                            </template>

                                            <div>
                                                <label for="stage_result" class="block text-sm font-medium text-gray-700">
                                                    Hasil <span class="text-red-500">*</span>
                                                    <template x-if="!canEditResult">
                                                        <span class="text-xs text-gray-500 ml-2">(Tidak dapat diubah - stage
                                                            selanjutnya sudah ada)</span>
                                                    </template>
                                                </label>
                                                <select name="result" id="stage_result" x-model="stageData.result"
                                                    @change="handleResultChange" :disabled="!canEditResult"
                                                    :class="!canEditResult ?
                                                        'mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100 cursor-not-allowed sm:text-sm' :
                                                        'mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm'">
                                                    <option value="">Pilih Hasil</option>
                                                    <template x-for="opt in availableResults" :key="opt">
                                                        <option :value="opt" x-text="labelMap[opt] || opt"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <div x-show="showNextStage">
                                                <label class="block text-sm font-medium text-gray-700">Stage Selanjutnya</label>
                                                <div class="mt-1 text-sm text-gray-700 bg-gray-50 p-2 rounded">
                                                    <span x-text="nextStageName"></span>
                                                </div>
                                            </div>

                                            <div>
                                                <label for="stage_date" class="block text-sm font-medium text-gray-700">Tanggal
                                                    Stage <span class="text-red-500">*</span></label>
                                                <input type="date" id="stage_date" name="stage_date"
                                                    x-model="stageData.stage_date"
                                                    :min="selectedStage === 'Psikotest' ? '' : (previousStageDate ||
                                                        getCurrentDate())"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <template x-if="previousStageDate && selectedStage !== 'Psikotest'">
                                                    <p class="mt-1 text-xs text-gray-500">Minimal tanggal: <span
                                                            x-text="formatDate(previousStageDate)"></span> (tanggal stage
                                                        sebelumnya)</p>
                                                </template>
                                            </div>

                                            <div x-show="showNextStage">
                                                <label for="next_stage_date"
                                                    class="block text-sm font-medium text-gray-700">Tanggal Stage Selanjutnya
                                                    <span class="text-red-500">*</span></label>
                                                <input type="date" id="next_stage_date" name="next_stage_date"
                                                    x-model="stageData.next_stage_date" {{-- :min="getCurrentDate()" --}}
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            </div>

                                            <div>
                                                <label for="stage_notes"
                                                    class="block text-sm font-medium text-gray-700">Catatan</label>
                                                <textarea name="notes" id="stage_notes" x-model="stageData.notes" rows="3" maxlength="1000"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    placeholder="Tambahkan catatan (opsional)..."></textarea>
                                            </div>

                                            <template x-if="shouldShowApplicationSelection()">
                                                <div class="bg-blue-50 border border-blue-200 rounded-md p-3"
                                                    :class="!canEditResult ? 'opacity-75' : ''">
                                                    <p class="text-xs font-medium text-blue-800 mb-2">Pilih lamaran yang akan diluluskan:</p>
                                                    <template x-if="!canEditResult">
                                                        <p class="text-xs text-amber-700 mb-2">
                                                            Pilihan lamaran dikunci karena stage selanjutnya sudah ada.
                                                        </p>
                                                    </template>
                                                    <div class="space-y-2">
                                                        <template x-for="app in selectableApplications" :key="app.id">
                                                            <label class="flex items-start gap-2 text-xs text-gray-700">
                                                                <input type="checkbox" :value="app.id" x-model="selectedApplicationIds"
                                                                    :disabled="!canEditResult"
                                                                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                <span>
                                                                    <span class="font-medium" x-text="app.vacancy && app.vacancy.name ? app.vacancy.name : 'N/A'"></span>
                                                                    <span class="text-gray-500" x-text="'(Aplikasi ID: ' + app.id + ')' "></span>
                                                                </span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="stageData.stage === 'hc_interview' && stageData.result === 'TIDAK LULUS'">
                                                <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                                    <p class="text-xs font-medium text-red-800">
                                                        Hasil Tidak Lulus pada HC Interview akan diterapkan ke semua lamaran kandidat ini.
                                                    </p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="submit" :disabled="isSubmitting"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed sm:ml-3 sm:w-auto sm:text-sm"
                                    x-text="isSubmitting ? 'Menyimpan...' : 'Simpan'">
                                </button>
                                <button type="button" @click="closeModal()" :disabled="isSubmitting"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal untuk Pindahkan Posisi -->
            <div x-show="showMovePositionModal" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"
                        @click="closeMovePositionModal()"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                        <form @submit.prevent="submitMovePositionForm()">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div
                                        class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Pindahkan Posisi Kandidat</h3>
                                        <div class="mt-4 space-y-4">
                                            <div>
                                                <label for="new_vacancy_id"
                                                    class="block text-sm font-medium text-gray-700">Pilih Posisi Baru</label>

                                                <div x-show="activeVacancies && activeVacancies.length > 0">
                                                    <select id="new_vacancy_id" name="new_vacancy_id"
                                                        x-model="selectedVacancyId" @change="handleVacancyChange()"
                                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                                                        <option value="">Pilih Posisi</option>
                                                        <template x-for="vacancy in activeVacancies" :key="vacancy.id">
                                                            <option :value="vacancy.id" x-text="vacancy.name"></option>
                                                        </template>
                                                    </select>
                                                </div>

                                                <div x-show="!activeVacancies || activeVacancies.length === 0"
                                                    class="mt-2 text-sm text-gray-600 bg-yellow-50 p-3 rounded">
                                                    Tidak ada posisi terbuka saat ini untuk dipindahkan. Mohon cek kembali
                                                    nanti.
                                                </div>
                                            </div>

                                            <div x-show="selectedVacancyId && availableMppYears.length > 0">
                                                <label for="new_mpp_year"
                                                    class="block text-sm font-medium text-gray-700">Pilih Tahun MPP <span
                                                        class="text-red-500">*</span></label>
                                                <select id="new_mpp_year" name="new_mpp_year" x-model="selectedMppYear"
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm"
                                                    required>
                                                    <option value="">Pilih Tahun</option>
                                                    <template x-for="year in availableMppYears" :key="year">
                                                        <option :value="year" x-text="year"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="submit"
                                    :disabled="isSubmitting || !activeVacancies || activeVacancies.length === 0"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed sm:ml-3 sm:w-auto sm:text-sm"
                                    x-text="isSubmitting ? 'Memindahkan...' : (activeVacancies && activeVacancies.length > 0 ? 'Pindahkan' : 'Tidak Ada Posisi')">
                                </button>
                                <button type="button" @click="closeMovePositionModal()" :disabled="isSubmitting"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal untuk Menampilkan Catatan -->
            <div x-show="showCommentModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.083-.98L2 17l1.02-3.11A8.841 8.841 0 012 10c0-4.418 4.03-8 9-8s9 3.134 8 7zM4.5 10a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Catatan</h3>
                                    <div class="mt-2">
                                        <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded-md max-h-40 overflow-y-auto">
                                            <p x-text="selectedComment || 'Tidak ada catatan'" class="whitespace-pre-wrap">
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" @click="showCommentModal = false"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    <div class="overflow-hidden rounded-lg bg-white shadow lg:order-1">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Pribadi</h3>
                        </div>
                        <div class="px-6 py-4">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nama Lengkap</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-medium">{{ $candidate->nama }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="mailto:{{ $candidate->alamat_email }}"
                                            class="text-blue-600 hover:text-blue-800">
                                            {{ $candidate->alamat_email }}
                                        </a>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Applicant ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $candidate->applicant_id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Jenis Kelamin</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($candidate->jk && $candidate->jk[0] === 'L')
                                            <span class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 10a2 2 0 114 0 2 2 0 01-4 0z" />
                                                </svg>
                                                Laki-laki
                                            </span>
                                        @elseif($candidate->jk && $candidate->jk[0] === 'P')
                                            <span class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-pink-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 10a2 2 0 114 0 2 2 0 01-4 0z" />
                                                </svg>
                                                Perempuan
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tanggal Lahir</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $candidate->tanggal_lahir ? $candidate->tanggal_lahir->format('d F Y') : '-' }}
                                        @if ($candidate->tanggal_lahir)
                                            <span class="text-gray-400 text-xs ml-1">
                                                ({{ $candidate->tanggal_lahir->age }} tahun)
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow lg:order-2">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Pendidikan</h3>
                        </div>
                        <div class="px-6 py-4">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Jenjang Pendidikan</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $candidate->jenjang_pendidikan ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Perguruan Tinggi</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $candidate->perguruan_tinggi ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Jurusan</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $candidate->jurusan ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">IPK</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($candidate->ipk)
                                            <span class="font-medium">{{ $candidate->ipk }}</span>
                                            @if ($candidate->ipk >= 3.5)
                                                <span class="ml-1 text-green-600 text-xs">Cumlaude</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if ($primaryApplication)
                        <div class="overflow-hidden rounded-lg bg-white shadow lg:order-6">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Informasi Posisi (Aktif)</h3>
                            </div>
                            <div class="px-6 py-4">
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Vacancy</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-medium"><span
                                                x-text="activeApplicationVacancyName"></span></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Department</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ $primaryApplication->vacancy?->department?->name ?? ($primaryApplication->department?->name ?? '-') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Source</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $candidate->source ?? '-' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    @endif

                    @can('view-candidate-documents', $candidate)
                        @if ($candidate->cv || $candidate->flk)
                            <div class="overflow-hidden rounded-lg bg-white shadow lg:order-7">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-900">Berkas</h3>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="space-y-3">
                                        @if ($candidate->cv)
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-8 w-8 text-red-600" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">Curriculum Vitae</p>
                                                        <p class="text-xs text-gray-500">PDF Document</p>
                                                    </div>
                                                </div>
                                                <a href="{{ route('files.serve', ['filePath' => $candidate->cv]) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                    Lihat
                                                </a>
                                            </div>
                                        @endif

                                        @if ($candidate->flk)
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-8 w-8 text-green-600" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">Form Lamaran Kerja</p>
                                                        <p class="text-xs text-gray-500">PDF Document</p>
                                                    </div>
                                                </div>
                                                <a href="{{ route('files.serve', ['filePath' => $candidate->flk]) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                    Lihat
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endcan

                    <!-- NEW: Application History Card -->
                    <div class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2 lg:order-3">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Riwayat Lamaran</h3>
                            <p class="mt-1 text-sm text-gray-500">Klik baris untuk melihat timeline detail lamaran tersebut.
                            </p>
                        </div>
                        <div class="flow-root">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Posisi (Vacancy)</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tanggal Melamar</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status</th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Aplikasi ID</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @forelse ($candidate->applications->sortByDesc('created_at') as $app)
                                            @php
                                                $isCancelled = strtoupper($app->overall_status) === 'CANCEL';
                                                $vacancyName = $app->vacancy->name ?? 'N/A';
                                            @endphp
                                            <tr @if (!$isCancelled) @click="setActiveApplication({{ $app->id }}, '{{ $vacancyName }}')" @endif
                                                :class="{
                                                    'bg-blue-50': {{ $isCancelled ? 'false' : 'true' }} &&
                                                        activeApplicationId === {{ $app->id }},
                                                    'hover:bg-gray-50': {{ $isCancelled ? 'false' : 'true' }}
                                                }"
                                                class="transition-colors duration-150 {{ $isCancelled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ $vacancyName }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $app->vacancy->department->name ?? 'No Department' }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $app->created_at->format('d M Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                @switch(strtoupper($app->overall_status))
                    @case('PROSES') bg-blue-100 text-blue-800 @break
                    @case('LULUS') bg-green-100 text-green-800 @break
                    @case('DITOLAK') bg-red-100 text-red-800 @break
                    @case('PINDAH') bg-yellow-100 text-yellow-800 @break
                    @case('CANCEL') bg-gray-100 text-gray-800 @break
                    @default bg-gray-100 text-gray-800
                @endswitch">
                                                        {{ strtoupper($app->overall_status) === 'DITOLAK' ? 'TIDAK LULUS' : $app->overall_status }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $app->id }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">
                                                    Belum ada data lamaran.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Rekrutmen for Active Application -->
                    <div class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2 lg:order-4">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Timeline Rekrutmen</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Progress untuk lamaran:
                                <span class="font-semibold text-gray-700" x-text="activeApplicationVacancyName"></span>
                            </p>
                        </div>
                        <div class="px-6 py-6">
                            {{-- Warning for PINDAH status (Source of move) --}}
                            <template x-if="applications.find(a => a.id === activeApplicationId)?.overall_status === 'PINDAH'">
                                <div
                                    class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center justify-between shadow-sm">
                                    <div class="flex items-center text-red-700 text-sm">
                                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <div class="flex flex-col">
                                            <span class="font-bold">Kandidat ini telah dipindahkan dari posisi ini.</span>
                                            <span class="text-xs"
                                                x-text="'Tujuan: ' + (applications.find(a => a.id === activeApplicationId)?.internal_position || 'Posisi Lain')"></span>
                                        </div>
                                    </div>
                                    @if (Auth::user()->hasRole('team_hc_2'))
                                        <button @click="cancelMove()"
                                            class="px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition-colors flex items-center gap-1 shadow-sm">
                                            <i class="fas fa-undo text-[10px]"></i>
                                            <span>Batalkan Perpindahan</span>
                                        </button>
                                    @endif
                                </div>
                            </template>

                            {{-- Info for applications that were the destination of a move --}}
                            <template
                                x-if="applications.find(a => a.id === activeApplicationId)?.overall_status !== 'PINDAH' && currentTimeline && currentTimeline.some(s => s.notes && s.notes.includes('[PINDAH POSISI]'))">
                                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-center shadow-sm">
                                    <div class="flex items-center text-blue-700 text-sm">
                                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <span class="font-medium">Kandidat ini dipindahkan ke posisi ini.</span>
                                    </div>
                                </div>
                            </template>

                            <div class="flow-root">
                                <template x-if="currentTimeline && Object.keys(currentTimeline).length > 0">
                                    <ul class="space-y-8">
                                        <template x-for="(stage, index) in currentTimeline" :key="stage.stage_key">
                                            <li class="relative">
                                                <template x-if="index < currentTimeline.length - 1">
                                                    <div class="absolute left-4 top-4 -ml-px mt-0.5 h-full w-0.5"
                                                        :class="{
                                                            'bg-green-500': stage.status === 'completed',
                                                            'bg-red-500': stage.status === 'failed',
                                                            'bg-gray-300': stage.status !== 'completed' && stage
                                                                .status !== 'failed'
                                                        }">
                                                    </div>
                                                </template>

                                                <div class="relative flex items-start space-x-4">
                                                    <div class="flex-shrink-0">
                                                        <template x-if="stage.status === 'completed'">
                                                            <div
                                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 ring-8 ring-white">
                                                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="stage.status === 'failed'">
                                                            <div
                                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500 ring-8 ring-white">
                                                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="stage.status === 'in_progress'">
                                                            <div
                                                                class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-500 bg-white ring-8 ring-white">
                                                                <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                                            </div>
                                                        </template>
                                                        <template x-if="stage.status === 'pending'">
                                                            <div
                                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-500 ring-8 ring-white">
                                                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </template>
                                                        <template x-if="stage.status === 'locked'">
                                                            <div
                                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-400 ring-8 ring-white">
                                                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="min-w-0 flex-1 pt-1.5">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center space-x-2">
                                                                <h4 class="text-sm font-medium"
                                                                    :class="{
                                                                        'text-gray-500': stage
                                                                            .status === 'locked',
                                                                        'text-gray-900': stage
                                                                            .status !== 'locked'
                                                                    }"
                                                                    x-text="stage.display_name"></h4>
                                                                <template x-if="stage.status === 'locked'">
                                                                    <span
                                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500"
                                                                        title="Tahap ini terkunci sampai tahap sebelumnya lulus.">
                                                                        <svg class="w-3 h-3 mr-1" fill="currentColor"
                                                                            viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd"
                                                                                d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                                                                clip-rule="evenodd" />
                                                                        </svg>
                                                                        Terkunci
                                                                    </span>
                                                                </template>
                                                            </div>

                                                            <div class="flex items-center space-x-2">
                                                                <template x-if="stage.date && stage.result">
                                                                    <div
                                                                        class="flex items-center space-x-1.5 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                                        <span class="text-xs font-medium text-gray-600"
                                                                            x-text="formatDate(stage.date)"></span>
                                                                        <template
                                                                            x-if="stage.is_edited && stage.original_date">
                                                                            <div class="relative flex items-center"
                                                                                x-data="{ showDateHistory: false }">
                                                                                <button @mouseenter="showDateHistory = true"
                                                                                    @mouseleave="showDateHistory = false"
                                                                                    class="text-amber-500 hover:text-amber-600 focus:outline-none transition-colors">
                                                                                    <svg class="w-3.5 h-3.5"
                                                                                        fill="currentColor"
                                                                                        viewBox="0 0 20 20">
                                                                                        <path fill-rule="evenodd"
                                                                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                                                            clip-rule="evenodd" />
                                                                                    </svg>
                                                                                </button>
                                                                                <div x-show="showDateHistory"
                                                                                    x-transition:enter="transition ease-out duration-200"
                                                                                    x-transition:enter-start="opacity-0 translate-y-1"
                                                                                    x-transition:enter-end="opacity-100 translate-y-0"
                                                                                    x-transition:leave="transition ease-in duration-150"
                                                                                    x-transition:leave-start="opacity-100 translate-y-0"
                                                                                    x-transition:leave-end="opacity-0 translate-y-1"
                                                                                    class="absolute bottom-full right-0 mb-2 w-48 bg-white border border-gray-200 rounded shadow-lg z-50 p-2 pointer-events-none"
                                                                                    style="display: none;">
                                                                                    <div class="space-y-1.5">
                                                                                        <div
                                                                                            class="flex justify-between items-center text-[10px] pb-1 border-b border-gray-100">
                                                                                            <span
                                                                                                class="text-gray-400 font-semibold uppercase">Histori
                                                                                                Tanggal</span>
                                                                                        </div>
                                                                                        <div
                                                                                            class="flex justify-between items-center">
                                                                                            <span
                                                                                                class="text-[10px] text-gray-500">Sebelum:</span>
                                                                                            <span
                                                                                                class="text-[10px] font-medium text-gray-700 line-through"
                                                                                                x-text="formatDate(stage.original_date)"></span>
                                                                                        </div>
                                                                                        <div
                                                                                            class="flex justify-between items-center">
                                                                                            <span
                                                                                                class="text-[10px] text-gray-500">Sesudah:</span>
                                                                                            <span
                                                                                                class="text-[10px] font-bold text-blue-600"
                                                                                                x-text="formatDate(stage.date)"></span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </template>

                                                                <template x-if="stage.notes">
                                                                    <button @click="showComment(stage.notes)"
                                                                        class="text-gray-400 hover:text-gray-600 transition-colors"
                                                                        title="Lihat catatan">
                                                                        <svg class="h-5 w-5" viewBox="0 0 20 20"
                                                                            fill="currentColor">
                                                                            <path fill-rule="evenodd"
                                                                                d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.02-3.11A8.841 8.841 0 012 10c0-4.418 4.03-8 9-8s8 3.134 8 7zM4.5 10a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0z"
                                                                                clip-rule="evenodd" />
                                                                        </svg>
                                                                    </button>
                                                                </template>

                                                                @canany(['edit-candidates', 'edit-timeline'])
                                                                    <template x-if="stage.status !== 'locked'">
                                                                        <div class="flex items-center space-x-1">
                                                                            <button
                                                                                @click="openStageModal(
                                                                                                    stage.display_name,
                                                                                                    stage.stage_key,
                                                                                                    stage.result || '',
                                                                                                    stage.notes || '',
                                                                                                    stage.can_edit_result !== false,
                                                                                                    stage.next_stage_scheduled_date || null,
                                                                                                    stage.previous_stage_date || null,
                                                                                                    stage.date || null
                                                                                                )"
                                                                                class="text-blue-600 hover:text-blue-800 transition-colors"
                                                                                :title="'Update status ' + stage.display_name">
                                                                                <svg class="h-5 w-5" viewBox="0 0 20 20"
                                                                                    fill="currentColor">
                                                                                    <path
                                                                                        d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                                                                    <path fill-rule="evenodd"
                                                                                        d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                                                                        clip-rule="evenodd" />
                                                                                </svg>
                                                                            </button>

                                                                            @if (Auth::user()->hasRole('team_hc_2'))
                                                                                <button
                                                                                    @click="stage.can_reset ? resetStage(stage.stage_key, stage.display_name) : null"
                                                                                    :class="stage.can_reset ?
                                                                                        'text-red-600 hover:text-red-800 cursor-pointer' :
                                                                                        'text-gray-300 cursor-not-allowed'"
                                                                                    class="transition-colors"
                                                                                    :disabled="!stage.can_reset"
                                                                                    :title="stage.can_reset ? 'Reset ' + stage
                                                                                        .display_name :
                                                                                        'Hanya stage yang baru saja di-update yang dapat di-reset'">
                                                                                    <svg class="h-5 w-5" fill="none"
                                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round"
                                                                                            stroke-linejoin="round"
                                                                                            stroke-width="2"
                                                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                                    </svg>
                                                                                </button>
                                                                            @endif
                                                                        </div>
                                                                    </template>
                                                                @endcanany
                                                            </div>
                                                        </div>

                                                        <div class="mt-2 text-sm">
                                                            <template x-if="stage.result">
                                                                <div class="flex items-center space-x-2">
                                                                    <span
                                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                                        :class="getResultClass(stage.result).bgColor + ' ' +
                                                                            getResultClass(stage.result).textColor"
                                                                        x-html="getResultClass(stage.result).icon + ' ' + (labelMap[stage.result.toUpperCase()] || stage.result)"></span>
                                                                    <template x-if="stage.evaluator">
                                                                        <p class="text-gray-500"
                                                                            x-text="'oleh ' + stage.evaluator"></p>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                            <template x-if="!stage.result && stage.status !== 'locked'">
                                                                <p class="text-gray-500">Menunggu hasil...</p>
                                                            </template>

                                                            <template
                                                                x-if="stage.stage_key === 'interview_bod' && stage.notes && stage.notes.includes('dipindahkan ke posisi baru')">
                                                                <p class="text-red-600 text-xs mt-1 font-semibold">
                                                                    * Kandidat ini dipindahkan dari posisi sebelumnya pada tahap
                                                                    ini.
                                                                </p>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <template x-if="!currentTimeline || Object.keys(currentTimeline).length === 0">
                                    <div class="text-center py-12">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2 mt-4">Belum ada aplikasi</h3>
                                        <p class="text-gray-500">Kandidat ini belum memiliki aplikasi pekerjaan.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2 lg:order-5">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Catatan Tahapan</h3>
                            <p class="mt-1 text-sm text-gray-500">Daftar catatan untuk lamaran yang sedang aktif.</p>
                        </div>
                        <div class="px-6 py-4">
                            <template x-if="currentTimeline && currentTimeline.some(s => s.notes && s.notes.trim())">
                                <div class="space-y-3">
                                    <template x-for="stage in currentTimeline.filter(s => s.notes && s.notes.trim())"
                                        :key="'note-' + stage.stage_key">
                                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-semibold text-gray-800" x-text="stage.display_name"></p>
                                                <span class="text-xs text-gray-500" x-show="stage.date"
                                                    x-text="formatDate(stage.date)"></span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-700 whitespace-pre-wrap" x-text="stage.notes"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!currentTimeline || !currentTimeline.some(s => s.notes && s.notes.trim())">
                                <p class="text-sm text-gray-500">Belum ada catatan untuk lamaran ini.</p>
                            </template>
                        </div>
                    </div>

            @if($hasAssessmentScore)
                @php
                    $assessmentFieldAliases = [
                        'presistence_score' => 'persistence_score',
                    ];

                    $assessmentValue = function (string $field) use ($candidateAssessment, $assessmentFieldAliases) {
                        $resolvedField = $assessmentFieldAliases[$field] ?? $field;
                        if (!$candidateAssessment || !isset($candidateAssessment->{$resolvedField})) {
                            return null;
                        }

                        return $candidateAssessment->{$resolvedField};
                    };

                    $assessmentMarker = function (string $field, int $target) use ($assessmentValue) {
                        $value = $assessmentValue($field);
                        if ($value === null) {
                            return '';
                        }

                        return (int) round((float) $value) === $target ? 'v' : '';
                    };

                    $assessmentBandLabel = function (string $field) use ($assessmentValue) {
                        $value = $assessmentValue($field);
                        if ($value === null) {
                            return '';
                        }

                        $score = (int) round((float) $value);
                        if ($score >= 1 && $score <= 2) {
                            return 'Need Improvement';
                        }
                        if ($score = 3) {
                            return 'Effective';
                        }
                        if ($score >= 4 && $score <= 5) {
                            return 'Highly Effective';
                        }

                        return '';
                    };

                    $assessmentBandDisplay = function (string $field, string $targetLabel) use ($assessmentBandLabel, $assessmentValue) {
                        $actualLabel = $assessmentBandLabel($field);
                        if ($actualLabel !== $targetLabel) {
                            return '';
                        }

                        $score = (int) round((float) $assessmentValue($field));
                        return (string) $score;
                    };
                @endphp

            <div class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2 lg:order-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Personality Dimension</h3>
                    <p class="mt-1 text-sm text-gray-500">Profil Psikotes Kandidat</p>
                </div>
                <div class="px-6 py-4">
                    <style>
                        #personality-dimension-table {
                            font-size: 11px;
                        }

                        #personality-dimension-table th,
                        #personality-dimension-table td {
                            padding: 4px 6px !important;
                            line-height: 1.2;
                            border-color: rgba(107, 114, 128, 1) !important;
                        }

                        #personality-dimension-table .dimension-spacer-row td {
                            height: 28px;
                            padding-top: 10px !important;
                            padding-bottom: 10px !important;
                            text-align: center !important;
                            font-weight: 700 !important;
                        }
                    </style>
                    <div class="overflow-x-auto">
                        <table id="personality-dimension-table" class="w-full table-fixed border border-gray-300 border-collapse text-sm text-gray-800">
                            <colgroup>
                                <col class="w-[35%]">
                                <col class="w-[6.5%]">
                                <col class="w-[6.5%]">
                                <col class="w-[6.5%]">
                                <col class="w-[6.5%]" style="background-color: rgba(197, 224, 179, 1);">
                                <col class="w-[6.5%]" style="background-color: rgba(197, 224, 179, 1);">
                                <col class="w-[6.5%]" style="background-color: rgba(197, 224, 179, 1);">
                                <col class="w-[6.5%]" style="background-color: rgba(197, 224, 179, 1);">
                                <col class="w-[6.5%]">
                                <col class="w-[6.5%]">
                                <col class="w-[6.5%]">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th rowspan="3"
                                        class="border border-gray-300 bg-gray-100 px-3 py-2 text-left font-semibold align-middle">
                                        Personality Dimension
                                    </th>
                                    <th colspan="10"
                                        class="border border-gray-300 bg-gray-100 px-3 py-2 text-center font-semibold">
                                        Rating
                                    </th>
                                </tr>
                                <tr>
                                    <th colspan="3"
                                        class="border border-gray-500 bg-gray-50 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">
                                        Need Improvement
                                    </th>
                                    <th colspan="4"
                                        class="border border-gray-500 bg-gray-50 px-3 py-2 text-center font-medium" style="background-color: rgba(197, 224, 179, 1);">
                                        Effective
                                    </th>
                                    <th colspan="3"
                                        class="border border-gray-500 bg-gray-50 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">
                                        Highly Effective
                                    </th>
                                </tr>
                                <tr>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">1</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">2</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">3</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(197, 224, 179, 1);">4</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(197, 224, 179, 1);">5</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(197, 224, 179, 1);">6</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(197, 224, 179, 1);">7</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">8</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">9</th>
                                    <th class="border border-gray-500 px-3 py-2 text-center font-medium" style="background-color: rgba(216, 216, 216, 1);">10</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td rowspan="2" class="border border-gray-300 px-3 py-2 text-base font-bold text-black align-top" style="background-color: rgba(142, 170, 219, 1);">Interpersonal Skill</td>
                                    <td colspan="10" class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="block text-[10px] text-gray-600 leading-tight">
                                            Kompetensi ini mengukur potensi untuk memiliki keterampilan komunikasi yang baik, sehingga dapat mempengaruhi orang lain,
                                            mampu membangun hubungan baik dengan berbagai orang dari berbagai tingkatan. Hal ini juga mendukung kemampuan untuk beradaptasi dan menyesuaikan diri dengan berbagai situasi.
                                        </span>
                                    </td>
                                </tr>
                                <tr class="dimension-spacer-row">
                                    <td colspan="3" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_is_score', 'Need Improvement') }}</td>
                                    <td colspan="4" class="border border-r px-3 py-3">{{ $assessmentBandDisplay('competency_is_score', 'Effective') }}</td>
                                    <td colspan="3" class="border border-gray-300 px-3 py-3">{{ $assessmentBandDisplay('competency_is_score', 'Highly Effective') }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">a. Extraversion</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk merasa nyaman ketika berinteraksi dengan orang banyak dan luwes saat berkomunikasi.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 10) }}</td>
                                </tr>
                                    <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">b. Assertiveness</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan seseorang untuk menampilkan dirinya secara apa adanya, serta terbuka dan berani dalam mengungkapkan pendapat.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('assertiveness_score', 10) }}</td>
                                </tr>
                                                                <tr>
                                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                                                    <span class="font-medium">c. Impact and Influence (IMP)</span><br>
                                                                    <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk dapat memengaruhi dan meyakinkan orang lain.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">d. Flexibility</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk mudah menyesuaikan diri dengan berbagai orang baru.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('flexibility_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">e. Reading Comprehension</span><br>
                                            <span class="block text-[10px] text-gray-600 leading-tight">Kemampuan untuk memahami kata-kata dan instruksi dan waktu yang dibutuhkan untuk memahami instruksi lisan maupun tertulis.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('reading_comprehension_score', 10) }}</td>
                                </tr>


                                <tr>
                                    <td rowspan="2" class="border border-gray-300 px-3 py-2 text-base font-bold text-black align-top" style="background-color: rgba(244, 176, 131, 1);">Analysis &amp; Judgement</td>
                                    <td colspan="10" class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="block text-[10px] text-gray-600 leading-tight">
Kompetensi ini mengukur potensi untuk mengumpulkan dan menganalisa data dan informasi penting guna memahami penyebab dari suatu masalah, serta mendukungnya dalam membuat keputusan dengan cepat dan tepat.
                                        </span>
                                    </td>
                                </tr>
                                <tr class="dimension-spacer-row">
                                    <td colspan="3" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_aj_score', 'Need Improvement') }}</td>
                                    <td colspan="4" class="border border-r px-3 py-3">{{ $assessmentBandDisplay('competency_aj_score', 'Effective') }}</td>
                                    <td colspan="3" class="border border-gray-300 px-3 py-3">{{ $assessmentBandDisplay('competency_aj_score', 'Highly Effective') }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Ideas</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Ketertarikan seseorang dengan berbagai ide maupun diskusi yang bersifat teoretis dan filosofis.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('idea_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Deductive Reasoning</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kemudahan untuk membuat kesimpulan yang tepat serta waktu yang dibutuhkan dalam memecahkan masalah.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Inductive Reasoning</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kemudahan dalam memahami konsep dan teori yang bersifat abstrak serta hubungannya terhadap waktu yang diperlukan dalam memformulasikan kerangka teoretis yang baru.</span>
                                    </td>
                                      <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('inductive_reasoning_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Perceptual Speed</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kemampuan seseorang untuk dengan cepat dan akurat mengenali detail visual, membandingkan informasi, serta mendeteksi perbedaan atau kesamaan dalam pola, angka, huruf, atau objek.</span>
                                    </td>
                                  <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('perceptual_speed_score', 10) }}</td>
                                </tr>

                                <tr>
                                    <td rowspan="2" class="border border-gray-300 px-3 py-2 text-base font-bold text-black align-top" style="background-color: rgba(142, 170, 219, 1);">Planning &amp; Driving Action</td>
                                    <td colspan="10" class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="block text-[10px] text-gray-600 leading-tight">
Kompetensi ini mengukur potensi untuk secara sistematis menetapkan obyektif dan rencana yang jelas, serta memanfaatkan sumber daya guna mencapai hasil yang diinginkan
                                        </span>
                                    </td>
                                </tr>
                                <tr class="dimension-spacer-row">
                                    <td colspan="3" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_pda_score', 'Need Improvement') }}</td>
                                    <td colspan="4" class="border border-r px-3 py-3">{{ $assessmentBandDisplay('competency_pda_score', 'Effective') }}</td>
                                    <td colspan="3" class="border border-gray-300 px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_pda_score', 'Highly Effective') }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Working Autonomously</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk memiliki inisiatif dalam memulai banyak hal, sehingga memiliki kepercayaan diri untuk menyelesaikan tugas dengan benar.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_autonomously_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Conscientiousness</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan seseorang untuk membuat perencanaan kerja serta mematuhi aturan dan prosedur kerja.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('conscientiousness_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Deductive Reasoning</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Analisis deduktif dan kemudahan untuk membuat kesimpulan yang tepat serta waktu yang dibutuhkan dalam memecahkan masalah.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('deductive_reasoning_score', 10) }}</td>
                                </tr>

                                <tr>
                                    <td rowspan="2" class="border border-gray-300 px-3 py-2 text-base font-bold text-black align-top" style="background-color: rgba(244, 176, 131, 1);">Teamwork</td>
                                    <td colspan="10" class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="block text-[10px] text-gray-600 leading-tight">
Kompetensi ini mengukur potensi untuk bekerja secara bersama-sama dengan sekelompok orang untuk mencapai tujuan tertentu.
                                        </span>
                                    </td>
                                </tr>
                                <tr class="dimension-spacer-row">
                                    <td colspan="3" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_tw_score', 'Need Improvement') }}</td>
                                    <td colspan="4" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_tw_score', 'Effective') }}</td>
                                    <td colspan="3" class="border border-gray-300 px-3 py-3">{{ $assessmentBandDisplay('competency_tw_score', 'Highly Effective') }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Teamwork</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan seseorang untuk senang bekerja di dalam kelompok, bersedia mengambil tanggung jawab, dan berkomitmen menjalankan keputusan-keputusan kelompok.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('teamwork_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Extraversion</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk merasa nyaman ketika berinteraksi dengan orang banyak dan luwes saat berkomunikasi.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('extraversion_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(247, 202, 172, 1);">
                                        <span class="font-medium">Impact and Influence (IMP)</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk dapat memengaruhi dan meyakinkan orang lain.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('impact_and_influence_score', 10) }}</td>
                                </tr>

                                <tr>
                                    <td rowspan="2" class="border border-gray-300 px-3 py-2 text-base font-bold text-black align-top" style="background-color: rgba(142, 170, 219, 1);">Drive &amp; Courage</td>
                                    <td colspan="10" class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="block text-[10px] text-gray-600 leading-tight">
Kompetensi ini mengukur potensi untuk belajar dan fokus pada unjuk kerja, tidak takut berhadapan langsung dengan hal-hal yang belum jelas, bertentangan dan memiliki resiko langsung, serta bersedia mengambil tindakan berdasarkan apa yang diyakininya
                                        </span>
                                    </td>
                                </tr>
                                <tr class="dimension-spacer-row">
                                    <td colspan="3" class="border border-r px-3 py-3 text-center font-bold">{{ $assessmentBandDisplay('competency_dc_score', 'Need Improvement') }}</td>
                                    <td colspan="4" class="border border-r px-3 py-3">{{ $assessmentBandDisplay('competency_dc_score', 'Effective') }}</td>
                                    <td colspan="3" class="border border-gray-300 px-3 py-3">{{ $assessmentBandDisplay('competency_dc_score', 'Highly Effective') }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Persistence</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan seseorang untuk gigih dalam menyelesaikan pekerjaannya.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('presistence_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Achievement Orientation</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan untuk menampilkan diri sebagai orang yang berorientasi pada tujuan, sehingga memiliki hasrat yang kuat untuk menjadi yang terbaik.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('achievement_orientation_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Actions</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kecenderungan seseorang untuk memiliki ketertarikan dengan banyak hal dan terbuka dengan perubahan.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('action_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Working Memory</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Kemampuan memilah dan mengolah informasi yang ada dan hubungannya terhadap waktu yang dibutuhkan dalam belajar.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('working_memory_score', 10) }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-3 py-2 align-top" style="background-color: rgba(222, 234, 246, 1);">
                                        <span class="font-medium">Personal Motivation</span><br>
                                        <span class="block text-[10px] text-gray-600 leading-tight">Dorongan internal dalam diri seseorang yang membuatnya berkeinginan dan berusaha untuk mencapai tujuan tertentu, baik dalam kehidupan pribadi, pekerjaan, maupun pengembangan diri.</span>
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 1) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 2) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 3) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 4) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 5) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 6) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 7) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 8) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 9) }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-center font-semibold">{{ $assessmentMarker('personal_motivation_score', 10) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
        </div>
    @endcan

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('candidateDetail', (allTimelines, initialActiveApplicationId,
                    initialActiveApplicationVacancyName) => ({
                    showModal: false,
                    showCommentModal: false,
                    showMovePositionModal: false,
                    selectedStage: null,
                    selectedComment: '',
                    isSubmitting: false,
                    selectedVacancyId: '',
                    selectedMppYear: '',
                    availableMppYears: [],
                    selectedApplicationIds: [],

                    allTimelines: allTimelines,
                    activeApplicationId: initialActiveApplicationId,
                    activeApplicationVacancyName: initialActiveApplicationVacancyName,
                    currentTimeline: [], // Will be populated in init()
                    applications: @json($candidate->applications),

                    activeVacancies: @json($activeVacancies ?? []),

                    stageData: {
                        stage: '',
                        result: '',
                        notes: '',
                        stage_date: '',
                        next_stage_date: '',
                    },
                    showNextStage: false,
                    nextStageName: '',
                    canEditResult: true,
                    nextStageScheduledDate: null,
                    previousStageDate: null,

                    stageOptions: {
                        screening: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        psikotes: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        hc_interview: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        user_interview: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        interview_bod: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN', 'PINDAH_POSISI'],
                        offering_letter: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        mcu: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN'],
                        hiring: ['LULUS', 'TIDAK LULUS', 'DIPERTIMBANGKAN']
                    },

                    labelMap: {
                        'LULUS': 'Lulus',
                        'TIDAK LULUS': 'Tidak Lulus',
                        'DIPERTIMBANGKAN': 'Dipertimbangkan',
                        'PINDAH_POSISI': 'Pindahkan Posisi',
                        'MENUNGGU': 'Menunggu hasil...',
                    },

                    availableResults: [],

                    stageSequence: {
                        'psikotes': 'hc_interview',
                        'hc_interview': 'user_interview',
                        'user_interview': 'interview_bod',
                        'interview_bod': 'offering_letter',
                        'offering_letter': 'mcu',
                        'mcu': 'hiring'
                    },

                    stageDisplayNames: {
                        'psikotes': 'Psikotest',
                        'hc_interview': 'Interview HR',
                        'user_interview': 'Interview User',
                        'interview_bod': 'Interview BOD',
                        'mcu': 'MCU',
                        'offering_letter': 'Offering Letter',
                        'hiring': 'Hiring'
                    },

                    init() {
                        if (this.activeApplicationId && this.allTimelines[this.activeApplicationId]) {
                            this.currentTimeline = this.allTimelines[this.activeApplicationId];
                        } else if (Object.keys(this.allTimelines).length > 0) {
                            // Fallback to first available timeline if activeApplicationId is not set or invalid
                            const firstAppId = Object.keys(this.allTimelines)[0];
                            this.activeApplicationId = parseInt(firstAppId);
                            this.currentTimeline = this.allTimelines[firstAppId];

                            // Find vacancy name for this fallback
                            const app = this.applications.find(a => a.id == this.activeApplicationId);
                            if (app && app.vacancy) {
                                this.activeApplicationVacancyName = app.vacancy.name;
                            }
                        }
                    },

                    getCurrentDate() {
                        const today = new Date();
                        return today.toISOString().split('T')[0];
                    },

                    formatDate(dateString) {
                        if (!dateString) return '';
                        const options = {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        };
                        return new Date(dateString).toLocaleDateString('id-ID', options);
                    },

                    getResultClass(result) {
                        const resultConfig = {
                            'LULUS': {
                                bgColor: 'bg-green-100',
                                textColor: 'text-green-800',
                                icon: '&#x2713;'
                            }, // Checkmark
                            'DISARANKAN': {
                                bgColor: 'bg-green-100',
                                textColor: 'text-green-800',
                                icon: '&#x2713;'
                            },
                            'DITERIMA': {
                                bgColor: 'bg-green-100',
                                textColor: 'text-green-800',
                                icon: '&#x2713;'
                            },
                            'HIRED': {
                                bgColor: 'bg-green-100',
                                textColor: 'text-green-800',
                                icon: '&#x2713;'
                            },
                            'PENDING': {
                                bgColor: 'bg-blue-100',
                                textColor: 'text-blue-800',
                                icon: '&hellip;'
                            }, // Ellipsis
                            'SENT': {
                                bgColor: 'bg-blue-100',
                                textColor: 'text-blue-800',
                                icon: '&hellip;'
                            },
                            'DIPERTIMBANGKAN': {
                                bgColor: 'bg-yellow-100',
                                textColor: 'text-yellow-800',
                                icon: '&hellip;'
                            },
                            'MENUNGGU': {
                                bgColor: 'bg-yellow-100',
                                textColor: 'text-yellow-800',
                                icon: '&hellip;'
                            },
                            'TIDAK LULUS': {
                                bgColor: 'bg-red-100',
                                textColor: 'text-red-800',
                                icon: '&#x2715;'
                            }, // Cross
                            'default': {
                                bgColor: 'bg-red-100',
                                textColor: 'text-red-800',
                                icon: '&#x2715;'
                            } // Cross
                        };
                        return resultConfig[result.toUpperCase()] || resultConfig['default'];
                    },

                    handleResultChange() {
                        if (this.stageData.result === 'PINDAH_POSISI') {
                            this.openMovePositionModal();
                            this.stageData.result = '';
                            return;
                        }

                        this.showNextStage = false;

                        if (this.stageData.result === 'LULUS') {
                            const nextStage = this.stageSequence[this.stageData.stage];
                            if (nextStage) {
                                this.showNextStage = true;
                                this.nextStageName = this.stageDisplayNames[nextStage];
                                // Pre-fill next stage date if it's already scheduled
                                if (!this.stageData.next_stage_date && this.nextStageScheduledDate) {
                                    this.stageData.next_stage_date = this.nextStageScheduledDate;
                                }
                            }
                        } else {
                            // Clear next stage date if result is not LULUS
                            this.stageData.next_stage_date = '';
                        }
                    },

                    shouldShowApplicationSelection() {
                        return this.stageData.stage === 'hc_interview' && this.stageData.result === 'LULUS' && this
                            .selectableApplications.length > 1;
                    },

                    get selectableApplications() {
                        return this.applications.filter(app => (app.overall_status || '').toUpperCase() !== 'CANCEL');
                    },

                    setActiveApplication(appId, vacancyName) {
                        this.activeApplicationId = appId;
                        this.activeApplicationVacancyName = vacancyName;
                        this.currentTimeline = this.allTimelines[appId];
                    },

                    openStageModal(stage, stageKey, result, notes, canEditResult = true,
                        nextStageScheduledDate = null, previousStageDate = null, currentStageDate = null) {
                        const activeApp = this.applications.find(app => app.id === this
                            .activeApplicationId);
                        if (activeApp && activeApp.overall_status.toUpperCase() === 'CANCEL') {
                            alert('Lamaran yang sudah dibatalkan tidak dapat diubah.');
                            return;
                        }

                        if (!this.activeApplicationId) {
                            alert('Pilih lamaran terlebih dahulu untuk memperbarui tahapan.');
                            return;
                        }
                        this.selectedStage = stage;
                        this.canEditResult = canEditResult;
                        this.nextStageScheduledDate = nextStageScheduledDate;
                        this.previousStageDate = previousStageDate;
                        this.availableResults = this.stageOptions[stageKey] || [];

                        if (stageKey !== 'interview_bod') {
                            this.availableResults = this.availableResults.filter(r => r !==
                                'PINDAH_POSISI');
                        }

                        this.showNextStage = false;
                        this.selectedApplicationIds = [String(this.activeApplicationId)];

                        this.stageData = {
                            stage: stageKey,
                            result: result || '',
                            notes: notes || '',
                            stage_date: currentStageDate || '',
                            next_stage_date: nextStageScheduledDate || ''
                        };

                        if (this.stageData.result === 'LULUS') {
                            this.handleResultChange();
                        }

                        this.showModal = true;
                    },

                    closeModal() {
                        if (this.isSubmitting) return;
                        this.showModal = false;
                    },

                    openMovePositionModal() {
                        this.selectedVacancyId = '';
                        this.selectedMppYear = '';
                        this.availableMppYears = [];
                        this.showMovePositionModal = true;
                    },

                    handleVacancyChange() {
                        this.selectedMppYear = '';
                        if (!this.selectedVacancyId) {
                            this.availableMppYears = [];
                            return;
                        }

                        const vacancy = this.activeVacancies.find(v => v.id == this.selectedVacancyId);
                        if (vacancy && vacancy.mpp_submissions) {
                            this.availableMppYears = vacancy.mpp_submissions.map(mpp => mpp.year);

                            // Sort unique years descending
                            this.availableMppYears = [...new Set(this.availableMppYears)].sort((a, b) => b -
                                a);

                            // If only one year, auto-select it
                            if (this.availableMppYears.length === 1) {
                                this.selectedMppYear = this.availableMppYears[0];
                            }
                        } else {
                            this.availableMppYears = [];
                        }
                    },

                    closeMovePositionModal() {
                        if (this.isSubmitting) return;
                        this.showMovePositionModal = false;
                    },

                    showComment(comment) {
                        this.selectedComment = comment;
                        this.showCommentModal = true;
                    },

                    async submitForm() {
                        if (this.isSubmitting) return;

                        if (!this.activeApplicationId) {
                            alert('Tidak ada lamaran yang aktif untuk diperbarui.');
                            return;
                        }

                        if (this.shouldShowApplicationSelection() && this.selectedApplicationIds.length === 0) {
                            alert('Pilih minimal satu lamaran untuk diperbarui.');
                            return;
                        }

                        // Only validate result if it can be edited
                        if (this.canEditResult && !this.stageData.result) {
                            alert('Hasil harus diisi.');
                            return;
                        }

                        // If can't edit result, ensure result is still present (from existing data)
                        if (!this.canEditResult && !this.stageData.result) {
                            alert('Tidak dapat mengubah stage ini karena stage selanjutnya sudah ada.');
                            return;
                        }

                        // Validate stage date
                        if (!this.stageData.stage_date) {
                            alert('Tanggal stage harus diisi.');
                            return;
                        }

                        // Validate stage date is not before previous stage date
                        if (this.previousStageDate && new Date(this.stageData.stage_date) < new Date(
                                this.previousStageDate)) {
                            alert('Tanggal stage tidak boleh lebih awal dari tanggal stage sebelumnya: ' +
                                this.formatDate(this.previousStageDate));
                            return;
                        }

                        if (this.showNextStage && !this.stageData.next_stage_date) {
                            alert('Tanggal stage selanjutnya harus diisi.');
                            return;
                        }

                        this.isSubmitting = true;

                        const payload = {
                            stage: this.stageData.stage,
                            result: this.stageData.result,
                            notes: this.stageData.notes || null,
                            stage_date: this.stageData.stage_date,
                            next_stage_date: this.showNextStage ? this.stageData.next_stage_date :
                                null,
                            update_date_only: !this
                                .canEditResult, // Flag to indicate only date should be updated
                            selected_application_ids: this.shouldShowApplicationSelection() ? this
                                .selectedApplicationIds.map(id => Number(id)) : [this.activeApplicationId],
                        };

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            if (!csrfToken) {
                                throw new Error('CSRF token tidak ditemukan di halaman.');
                            }

                            const response = await fetch(
                                `/applications/${this.activeApplicationId}/stage`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(payload)
                                });

                            const data = await response.json();

                            if (response.ok) {
                                this.showModal = false;
                                if (data.message) {
                                    alert(data.message);
                                }
                                window.location.reload();
                            } else {
                                let errorMessage = 'Gagal memperbarui data.';
                                if (data.errors) {
                                    const errors = Object.values(data.errors).flat();
                                    errorMessage = errors.join('\n');
                                } else if (data.message) {
                                    errorMessage = data.message;
                                }
                                throw new Error(errorMessage);
                            }

                        } catch (error) {
                            alert('Terjadi kesalahan: ' + error.message);
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    async submitMovePositionForm() {
                        if (this.isSubmitting) return;

                        if (!this.activeApplicationId) {
                            alert('Tidak ada lamaran yang aktif untuk dipindahkan.');
                            return;
                        }

                        if (!this.selectedVacancyId) {
                            alert('Pilih posisi baru terlebih dahulu.');
                            return;
                        }

                        if (this.availableMppYears.length > 0 && !this.selectedMppYear) {
                            alert('Pilih tahun MPP terlebih dahulu.');
                            return;
                        }

                        this.isSubmitting = true;

                        const payload = {
                            new_vacancy_id: this.selectedVacancyId,
                            mpp_year: this.selectedMppYear,
                        };

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            if (!csrfToken) {
                                throw new Error('CSRF token tidak ditemukan di halaman.');
                            }

                            const response = await fetch(
                                `/applications/${this.activeApplicationId}/move-position`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(payload)
                                });

                            const data = await response.json();

                            if (response.ok) {
                                this.showMovePositionModal = false;
                                this.showModal = false;
                                if (data.message) {
                                    alert(data.message);
                                }
                                window.location.reload();
                            } else {
                                let errorMessage = 'Gagal memindahkan posisi.';
                                if (data.errors) {
                                    const errors = Object.values(data.errors).flat();
                                    errorMessage = data.message || errors.join(
                                        '\n'); // Use data.message if available, otherwise join errors
                                } else if (data.message) {
                                    errorMessage = data.message;
                                }
                                throw new Error(errorMessage);
                            }

                        } catch (error) {
                            alert('Terjadi kesalahan: ' + error.message);
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    async cancelMove() {
                        if (!confirm(
                                'Apakah Anda yakin ingin membatalkan perpindahan posisi ini? Aplikasi baru akan dihapus dan aplikasi ini akan dipulihkan.'
                            )) {
                            return;
                        }

                        this.isSubmitting = true;

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            if (!csrfToken) {
                                throw new Error('CSRF token tidak ditemukan di halaman.');
                            }

                            const response = await fetch(
                                `/applications/${this.activeApplicationId}/cancel-move`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    }
                                });

                            const data = await response.json();

                            if (response.ok) {
                                alert(data.message || 'Perpindahan berhasil dibatalkan.');
                                window.location.reload();
                            } else {
                                throw new Error(data.message || 'Gagal membatalkan perpindahan.');
                            }
                        } catch (error) {
                            alert('Terjadi kesalahan: ' + error.message);
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    async resetStage(stageKey, stageDisplayName) {
                        if (!confirm(
                                `Apakah Anda yakin ingin me-reset tahap ${stageDisplayName}? Tahap ini dan semua tahap setelahnya akan dihapus.`
                            )) {
                            return;
                        }

                        this.isSubmitting = true;

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            if (!csrfToken) {
                                throw new Error('CSRF token tidak ditemukan di halaman.');
                            }

                            const response = await fetch(
                                `/applications/${this.activeApplicationId}/reset-stage`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        stage: stageKey
                                    })
                                });

                            const data = await response.json();

                            if (response.ok) {
                                alert(data.message || 'Stage reset successfully.');
                                window.location.reload();
                            } else {
                                throw new Error(data.message || 'Gagal me-reset stage.');
                            }
                        } catch (error) {
                            alert('Terjadi kesalahan: ' + error.message);
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                }))
            });
        </script>
    @endpush

@endsection

