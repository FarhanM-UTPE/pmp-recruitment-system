@extends('layouts.app')

@section('title', 'Import Assessment Excel')
@section('page-title', 'Import Assessment Excel')
@section('page-subtitle', 'Upload file assessment (Astra Spark/Ignite) untuk menyimpan score ke tabel assessment')

@push('header-filters')
<div class="flex items-center gap-2">
    <a href="{{ route('import.index') }}" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-2 text-sm transition-colors">
        <i class="fas fa-arrow-left"></i>
        <span>Import Kandidat</span>
    </a>
</div>
@endpush

@section('content')
<div class="space-y-6" x-data="assessmentImportManager()">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-brain text-indigo-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Upload File Excel Assessment</h2>
                <p class="text-gray-600">Sistem akan merapikan 3-tier header, mengubah ke snake_case, lalu menyimpan score ke tabel assessment.</p>
            </div>

            <div class="max-w-2xl mx-auto">
                <div x-show="!file"
                    class="drop-zone rounded-lg p-8 text-center mb-6 cursor-pointer"
                    x-on:dragover.prevent="$el.classList.add('dragover')"
                    x-on:dragleave.prevent="$el.classList.remove('dragover')"
                    x-on:drop.prevent="handleFileDrop($event)"
                    @click="$refs.fileInput.click()">
                    <div class="space-y-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto">
                            <i class="fas fa-cloud-upload-alt text-gray-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-lg font-medium text-gray-700">Drag & drop file assessment di sini</p>
                            <p class="text-sm text-gray-500">atau klik untuk pilih file</p>
                        </div>
                        <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" class="hidden" accept=".xlsx,.xls,.csv">
                        <button type="button" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                            Pilih File
                        </button>
                    </div>
                </div>

                <div x-show="file" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-file-excel text-indigo-600 text-xl"></i>
                            <div>
                                <p class="font-medium text-indigo-900" x-text="fileName"></p>
                                <p class="text-sm text-indigo-600" x-text="fileSize"></p>
                            </div>
                        </div>
                        <button type="button" @click="clearFile()" class="text-indigo-600 hover:text-indigo-800 p-1 rounded">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <button @click="uploadAndValidate" :disabled="!file || isUploading"
                    class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors flex items-center justify-center gap-2">
                    <span x-show="!isUploading"><i class="fas fa-upload"></i> Validasi & Preview Assessment</span>
                    <span x-show="isUploading">Memvalidasi...</span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="showModal" x-cloak style="display: none;" class="fixed inset-0 bg-gray-900 bg-opacity-60 z-50 flex items-center justify-center" @keydown.escape.window="cancelImport">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl mx-auto transform transition-all" @click.away="cancelImport">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-900">Hasil Validasi & Preview Import Assessment</h3>
            </div>

            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <div x-show="errors.length > 0" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg mb-6">
                    <h4 class="font-bold text-red-900 mb-2">Ditemukan Kesalahan Validasi</h4>
                    <ul class="list-disc pl-5 text-red-800 text-sm space-y-1 max-h-48 overflow-y-auto">
                        <template x-for="error in errors" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                    <p class="text-sm mt-3 text-red-900">Baris yang bermasalah akan dilewati saat proses import final.</p>
                </div>

                <div x-show="previewData.length > 0">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-6">
                        <h4 class="font-bold text-green-900">Preview Siap Diimport</h4>
                        <p class="text-sm text-green-800"><strong x-text="totalRows"></strong> baris terdeteksi. Preview menampilkan maksimal 5 baris valid pertama.</p>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <template x-for="header in previewHeaders" :key="header">
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 uppercase tracking-wider" x-text="header.replace(/_/g, ' ')"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(row, idx) in previewData" :key="idx">
                                    <tr>
                                        <template x-for="header in previewHeaders" :key="header">
                                            <td class="px-4 py-2 whitespace-nowrap text-gray-700" x-text="row[header]"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end items-center gap-3 rounded-b-2xl">
                <button @click="cancelImport" class="text-gray-600 bg-white hover:bg-gray-100 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Batal
                </button>
                <button @click="confirmImport" :disabled="isConfirming || !fileId"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isConfirming">Konfirmasi & Import Assessment</span>
                    <span x-show="isConfirming">Memproses...</span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="toast.show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 max-w-md text-white"
        :class="{ 'bg-green-500': toast.type === 'success', 'bg-red-500': toast.type === 'error' }">
        <div class="flex items-center gap-2">
            <i class="fas" :class="{ 'fa-check-circle': toast.type === 'success', 'fa-exclamation-circle': toast.type === 'error' }"></i>
            <span x-text="toast.message"></span>
        </div>
    </div>

    @if(isset($import_history) && $import_history->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-history text-gray-600"></i>
                Riwayat Import Assessment
            </h3>
        </div>

        <div class="divide-y divide-gray-200">
            @foreach($import_history as $history)
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">{{ $history->filename }}</p>
                        <p class="text-sm text-gray-600">{{ number_format($history->total_rows) }} baris • <span class="text-green-600">{{ number_format($history->success_rows) }} berhasil</span> • <span class="text-red-600">{{ number_format($history->failed_rows) }} gagal</span></p>
                    </div>
                    <p class="text-sm text-gray-500">{{ $history->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function assessmentImportManager() {
    return {
        file: null,
        fileName: '',
        fileSize: '',
        isUploading: false,
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
            if (file.size > 10 * 1024 * 1024) {
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
            this.errors = [];

            const formData = new FormData();
            formData.append('file', this.file);

            try {
                const response = await fetch('{{ route("import.assessment.preview") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                this.errors = data.errors || [];

                if (data.success) {
                    this.fileId = data.file_id;
                    this.previewData = data.preview || [];
                    this.previewHeaders = data.headers || [];
                    this.totalRows = data.total_rows || 0;
                    this.showModal = true;
                } else {
                    this.showToast(data.message || 'Gagal memvalidasi file assessment.', 'error');
                }
            } catch (error) {
                this.showToast(`Tidak dapat terhubung ke server: ${error.message}`, 'error');
            } finally {
                this.isUploading = false;
            }
        },
        async confirmImport() {
            if (!this.fileId) return;
            this.isConfirming = true;

            try {
                const response = await fetch('{{ route("import.assessment.confirm") }}', {
                    method: 'POST',
                    body: JSON.stringify({ file_id: this.fileId }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    this.showToast('Import assessment berhasil diproses. Halaman akan dimuat ulang.');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.showToast(data.message || 'Gagal memproses import assessment.', 'error');
                    this.isConfirming = false;
                }
            } catch (error) {
                this.showToast(`Tidak dapat terhubung ke server: ${error.message}`, 'error');
                this.isConfirming = false;
            }
        },
        async cancelImport() {
            if (this.fileId) {
                try {
                    await fetch('{{ route("import.assessment.cancel") }}', {
                        method: 'POST',
                        body: JSON.stringify({ file_id: this.fileId }),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } catch (_) {}
            }

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
