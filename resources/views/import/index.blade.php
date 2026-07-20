@extends('layouts.app')

@section('title', 'Import Excel')
@section('page-title', 'Import Data Excel')
@section('page-subtitle', 'Upload file Excel untuk import data kandidat')

@push('header-filters')
@endpush

@section('content')
    <div class="space-y-6" x-data="importManager()">
        <!-- Upload Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-excel text-blue-600 text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Upload File Excel Kandidat</h2>
                    <p class="text-gray-600">Lakukan validasi data sebelum mengimpor untuk memastikan integritas data.</p>
                </div>

                <div id="uploadBox" class="max-w-2xl mx-auto">
                    <!-- Drop Zone -->
                    <div x-show="!file" class="drop-zone rounded-lg p-8 text-center mb-6 cursor-pointer"
                        x-on:dragover.prevent="$el.classList.add('dragover')"
                        x-on:dragleave.prevent="$el.classList.remove('dragover')" x-on:drop.prevent="handleFileDrop($event)"
                        @click="$refs.fileInput.click()">
                        <div class="space-y-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-lg font-medium text-gray-700">Drag & drop file di sini</p>
                                <p class="text-sm text-gray-500">atau klik untuk browse file</p>
                            </div>
                            <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" class="hidden"
                                accept=".xlsx,.xls,.csv">
                            <button type="button"
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Pilih File
                            </button>
                        </div>
                    </div>

                    <!-- File Info & Progress -->
                    <div x-show="file" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-excel text-blue-600 text-xl"></i>
                                <div>
                                    <p class="font-medium text-blue-900" x-text="fileName"></p>
                                    <p class="text-sm text-blue-600" x-text="fileSize"></p>
                                </div>
                            </div>
                            <button type="button" @click="clearFile()"
                                class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div x-show="isUploading" class="mt-3">
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button @click="uploadAndValidate" :disabled="!file || isUploading"
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors flex items-center justify-center gap-2">
                        <span x-show="!isUploading"><i class="fas fa-upload"></i> Validasi & Preview</span>
                        <span x-show="isUploading">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span>Memvalidasi...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div x-show="showModal" x-cloak style="display: none;"
            class="fixed inset-0 bg-gray-900 bg-opacity-60 z-50 flex items-center justify-center"
            @keydown.escape.window="cancelImport">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl mx-auto transform transition-all"
                @click.away="cancelImport">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900">Hasil Validasi & Preview Import</h3>
                </div>

                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <!-- Error Section -->
                    <div x-show="errors.length > 0" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg mb-6">
                        <h4 class="font-bold text-red-900 mb-2">Ditemukan Kesalahan Validasi</h4>
                        <ul class="list-disc pl-5 text-red-800 text-sm space-y-1 max-h-48 overflow-y-auto">
                            <template x-for="error in errors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                        <p class="text-sm mt-3 text-red-900">Mohon perbaiki file Excel Anda dan coba lagi. Import tidak
                            dapat dilanjutkan.</p>
                    </div>

                    <!-- Success/Preview Section -->
                    <div x-show="errors.length === 0">
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-6">
                            <h4 class="font-bold text-green-900">Validasi Berhasil!</h4>
                            <p class="text-sm text-green-800">
                                <strong x-text="totalRows"></strong> baris data lolos validasi. Berikut adalah preview dari
                                5 baris pertama.
                            </p>
                        </div>

                        <h5 class="font-semibold text-gray-800 mb-2">Preview Data:</h5>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <template x-for="(header, headerIndex) in previewHeaders"
                                            :key="`h-${headerIndex}-${header}`">
                                            <th class="px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider"
                                                x-text="header.replace(/_/g, ' ')"></th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="(row, rowIndex) in previewData" :key="`r-${rowIndex}`">
                                        <tr>
                                            <template x-for="(header, headerIndex) in previewHeaders"
                                                :key="`c-${rowIndex}-${headerIndex}-${header}`">
                                                <td class="px-4 py-2 whitespace-nowrap text-gray-700"
                                                    x-text="row[header] ?? '-'"></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p x-show="previewData.length === 0" class="mt-3 text-sm text-gray-500">
                            Tidak ada baris preview yang dapat ditampilkan.
                        </p>
                    </div>
                </div>

                <div
                    class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 rounded-b-2xl">
                    <div class="flex items-center gap-3" x-show="errors.length === 0">
                        <label for="import-source" class="text-sm font-medium text-gray-700">Source</label>
                        <select id="import-source" x-model="selectedSource"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="Airsys">Airsys</option>
                            <option value="Campus Hiring">Campus Hiring</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button @click="cancelImport"
                            class="text-gray-600 bg-white hover:bg-gray-100 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            Batal
                        </button>
                        <button @click="confirmImport" :disabled="isConfirming"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <span x-show="!isConfirming">
                                <i class="fas fa-check"></i> Konfirmasi & Import
                            </span>
                            <span x-show="isConfirming">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <use href="#spinner-path" />
                                </svg>
                                <span>Memproses...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="toast.show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 max-w-md text-white"
            :class="{ 'bg-green-500': toast.type === 'success', 'bg-red-500': toast.type === 'error' }">
            <div class="flex items-center gap-2">
                <i class="fas"
                    :class="{ 'fa-check-circle': toast.type === 'success', 'fa-exclamation-circle': toast.type === 'error' }"></i>
                <span x-text="toast.message"></span>
                <button @click="toast.show = false" class="ml-2 hover:bg-white/20 p-1 rounded-full">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Import History -->
        @if(isset($import_history) && $import_history->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-history text-gray-600"></i>
                        Riwayat Import
                    </h3>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($import_history as $history)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-10 h-10 
                                                    @if($history->status == 'success' || $history->status == 'completed') bg-green-50 @elseif($history->status == 'failed') bg-red-50 @else bg-blue-50 @endif 
                                                    rounded-lg flex items-center justify-center">
                                        @if($history->status == 'success' || $history->status == 'completed')
                                            <i class="fas fa-check-circle text-green-600"></i>
                                        @elseif($history->status == 'failed')
                                            <i class="fas fa-times-circle text-red-600"></i>
                                        @else
                                            <i class="fas fa-clock text-blue-600"></i>
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">{{ $history->filename }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ number_format($history->total_rows) }} baris •
                                            <span class="text-green-600">{{ number_format($history->success_rows) }} berhasil</span>
                                            •
                                            <span class="text-red-600">{{ number_format($history->failed_rows) }} gagal</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 mb-1">{{ $history->created_at->diffForHumans() }}</p>
                                    @if($history->status == 'success' || $history->status == 'completed')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Berhasil
                                        </span>
                                    @elseif($history->status == 'failed')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Gagal
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Proses
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Error Details Expandable Section -->
                            @if($history->failed_rows > 0 && $history->error_details && count($history->error_details) > 0)
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <details class="group">
                                        <summary
                                            class="flex items-center gap-2 text-sm text-red-600 hover:text-red-700 font-medium cursor-pointer list-none group-open:text-red-700">
                                            <i class="fas fa-chevron-right text-xs transition-transform group-open:rotate-90"></i>
                                            <span>Lihat {{ count($history->error_details) }} error yang terjadi</span>
                                        </summary>

                                        <div class="mt-3 space-y-2 bg-red-50 rounded-lg p-4 border border-red-100">
                                            @foreach($history->error_details as $error)
                                                <div class="bg-white rounded p-3 border border-red-200">
                                                    <div class="flex items-start gap-3">
                                                        <div class="flex-shrink-0 mt-0.5">
                                                            <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <p class="text-xs text-gray-500 font-medium">Baris {{ $error['row'] }}</p>
                                                            <p class="text-sm text-gray-900 mt-1">
                                                                <span class="font-medium">{{ $error['nama'] }}</span>
                                                                <span class="text-gray-600">(ID: {{ $error['applicant_id'] }})</span>
                                                            </p>
                                                            <p class="text-sm text-red-700 mt-2">
                                                                <i class="fas fa-info-circle text-xs"></i>
                                                                {{ $error['error'] }}
                                                            </p>
                                                            @if(isset($error['vacancy_name_provided']))
                                                                <p class="text-xs text-gray-600 mt-1">Vacancy yang dicari: <span
                                                                        class="font-mono bg-gray-100 px-1 rounded">{{ $error['vacancy_name_provided'] }}</span>
                                                                </p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <svg class="hidden">
        <defs>
            <path id="spinner-path" class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </defs>
    </svg>
@endsection

@push('scripts')
    <script>
        function importManager() {
            return {
                file: null,
                fileName: '',
                fileSize: '',
                selectedSource: 'Airsys',
                isUploading: false,
                progress: 0,
                showModal: false,
                errors: [],
                previewData: [],
                previewHeaders: [],
                totalRows: 0,
                fileId: null,
                isConfirming: false,
                toast: { show: false, message: '', type: 'success' },

                handleFileDrop(event) {
                    this.handleFile(event.dataTransfer.files[0]);
                },
                handleFileSelect(event) {
                    this.handleFile(event.target.files[0]);
                },
                handleFile(file) {
                    if (!file) return;

                    const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
                    const isValid = validTypes.includes(file.type) || file.name.match(/\.(xlsx|xls|csv)$/i);

                    if (!isValid) {
                        this.showToast('File harus berformat Excel (.xlsx, .xls, .csv)', 'error');
                        return;
                    }
                    if (file.size > 10 * 1024 * 1024) { // 10MB
                        this.showToast('Ukuran file maksimal 10MB', 'error');
                        return;
                    }

                    this.file = file;
                    this.fileName = file.name;
                    this.fileSize = this.formatFileSize(file.size);
                },
                clearFile() {
                    this.file = null;
                    this.fileName = '';
                    this.fileSize = '';
                    this.isUploading = false;
                    this.progress = 0;
                    this.$refs.fileInput.value = '';
                },
                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },
                async uploadAndValidate() {
                    if (!this.file) return;

                    this.isUploading = true;
                    this.progress = 0;
                    this.errors = [];

                    const interval = setInterval(() => {
                        if (this.progress < 90) this.progress += 10;
                    }, 100);

                    const formData = new FormData();
                    formData.append('file', this.file);


                    try {
                        const response = await fetch('{{ route("import.preview") }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        clearInterval(interval);
                        this.progress = 100;

                        const data = await response.json();

                        this.errors = data.errors || []; // Always assign errors, even if empty
                        if (data.success) {
                            this.fileId = data.file_id;
                            this.previewData = data.preview;
                            this.previewHeaders = data.headers;
                            this.totalRows = data.total_rows;
                        } else {
                            // This else block should ideally not be hit if backend always returns success: true
                            // But good for defensive programming
                            this.errors = data.errors || [data.message];
                        }
                        this.showModal = true;

                    } catch (error) {
                        console.error('Validation error:', error);
                        this.errors = [`Tidak dapat terhubung ke server: ${error.message}. Silakan coba lagi.`];
                        this.showModal = true;
                    } finally {
                        this.isUploading = false;
                    }
                },
                async confirmImport() {
                    if (!this.fileId) return;

                    this.isConfirming = true;

                    try {
                        const response = await fetch('{{ route("import.confirm") }}', {
                            method: 'POST',
                            body: JSON.stringify({
                                file_id: this.fileId,
                                source: this.selectedSource,
                            }),
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.showToast('Import berhasil dimulai! Halaman akan dimuat ulang.');
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            this.showToast(data.message || 'Gagal memulai proses import.', 'error');
                            this.isConfirming = false;
                        }

                    } catch (error) {
                        console.error('Confirm error:', error);
                        this.showToast(`Tidak dapat terhubung ke server: ${error.message}`, 'error');
                        this.isConfirming = false;
                    }
                },
                async cancelImport() {
                    if (this.isConfirming) return;

                    if (this.fileId) {
                        try {
                            await fetch('{{ route("import.cancel") }}', {
                                method: 'POST',
                                body: JSON.stringify({ file_id: this.fileId }),
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });
                        } catch (error) {
                            console.error('Cancel error:', error);
                        }
                    }
                    this.resetAll();
                },
                resetAll() {
                    this.clearFile();
                    this.showModal = false;
                    this.errors = [];
                    this.previewData = [];
                    this.previewHeaders = [];
                    this.totalRows = 0;
                    this.fileId = null;
                    this.isConfirming = false;
                },
                showToast(message, type = 'success') {
                    this.toast.message = message;
                    this.toast.type = type;
                    this.toast.show = true;
                    setTimeout(() => this.toast.show = false, 5000);
                }
            }
        }
    </script>
@endpush