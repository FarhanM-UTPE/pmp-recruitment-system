@extends('layouts.app')

@section('title', 'Tambah Kandidat')
@section('page-title', 'Tambah Kandidat')
@section('page-subtitle', 'Masukkan informasi kandidat baru')

@push('header-filters')
    <button onclick="history.back()"
        class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
        <i class="fas fa-arrow-left text-sm"></i>
        <span>Kembali</span>
    </button>
@endpush

@section('content')
    @can('edit-candidates')
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Form Kandidat</h3>
            </div>

            <div class="p-6">
                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-red-800 font-medium">Terdapat kesalahan dalam pengisian form:</p>
                                <ul class="list-disc pl-5 text-red-800 mt-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data" class="space-y-6"
                    x-data="{
                        vacancies: {{ json_encode($vacancies->keyBy('id')) }},
                        selectedVacancyId: '{{ old('vacancy_id') }}',
                        selectedVacancy: null,
                        selectedMppYear: '{{ old('mpp_year') }}',
                        get requiresMppYear() {
                            return this.selectedVacancy && this.selectedVacancy.mpp_submissions && this.selectedVacancy.mpp_submissions.length > 0;
                        },
                        init() {
                            if (this.selectedVacancyId && this.vacancies[this.selectedVacancyId]) {
                                this.selectedVacancy = this.vacancies[this.selectedVacancyId];
                            }
                        }
                    }" x-init="init()">
                    @csrf

                    <!-- Basic Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama" class="block text-sm font-medium text-gray-700">Nama *</label>
                                <input type="text" name="nama" id="nama" value="{{ old('nama') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>
                            <div>
                                <label for="alamat_email" class="block text-sm font-medium text-gray-700">Email *</label>
                                <input type="email" name="alamat_email" id="alamat_email" value="{{ old('alamat_email') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>
                            <div>
                                <label for="applicant_id" class="block text-sm font-medium text-gray-700">Applicant ID *</label>
                                <input type="text" name="applicant_id" id="applicant_id"
                                    value="{{ old('applicant_id', $applicantId ?? '') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required readonly>
                            </div>
                            <div>
                                <label for="jk" class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
                                <select name="jk" id="jk"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" {{ old('jk') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki
                                    </option>
                                    <option value="Perempuan" {{ old('jk') == 'Perempuan' ? 'selected' : '' }}>Perempuan
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="tanggal_lahir" class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" id="tanggal_lahir" value="{{ old('tanggal_lahir') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Position Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Informasi Posisi</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="vacancy_id" class="block text-sm font-medium text-gray-700">Nama Lowongan *</label>
                                <select name="vacancy_id" id="vacancy_id"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required x-model="selectedVacancyId"
                                    @change="selectedVacancy = vacancies[$event.target.value]; selectedMppYear = ''">
                                    <option value="">Pilih Lowongan</option>
                                    @foreach ($vacancies as $vacancy)
                                        <option value="{{ $vacancy->id }}"
                                            {{ old('vacancy_id') == $vacancy->id ? 'selected' : '' }}>{{ $vacancy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div
                                x-show="selectedVacancy && selectedVacancy.mpp_submissions && selectedVacancy.mpp_submissions.length > 0">
                                <label for="mpp_year" class="block text-sm font-medium text-gray-700">Tahun MPP *</label>
                                <select name="mpp_year" id="mpp_year"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    x-model="selectedMppYear" :required="requiresMppYear">
                                    <option value="">Pilih Tahun</option>
                                    <template x-for="submission in selectedVacancy.mpp_submissions" :key="submission.id">
                                        <option :value="submission.year"
                                            x-text="submission.year + (submission.submission_type === 'planned' ? '' : ' (Diluar MPP)')">
                                        </option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Education Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Informasi Pendidikan</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="jenjang_pendidikan" class="block text-sm font-medium text-gray-700">Jenjang
                                    Pendidikan</label>
                                <input type="text" name="jenjang_pendidikan" id="jenjang_pendidikan"
                                    value="{{ old('jenjang_pendidikan') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="perguruan_tinggi" class="block text-sm font-medium text-gray-700">Perguruan
                                    Tinggi</label>
                                <input type="text" name="perguruan_tinggi" id="perguruan_tinggi"
                                    value="{{ old('perguruan_tinggi') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="jurusan" class="block text-sm font-medium text-gray-700">Jurusan</label>
                                <input type="text" name="jurusan" id="jurusan" value="{{ old('jurusan') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="ipk" class="block text-sm font-medium text-gray-700">IPK</label>
                                <input type="number" name="ipk" id="ipk" value="{{ old('ipk') }}"
                                    step="0.01" min="0" max="4"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Files -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Berkas</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="cv" class="block text-sm font-medium text-gray-700">CV</label>
                                <input type="file" name="cv" id="cv"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="flk" class="block text-sm font-medium text-gray-700">FLK</label>
                                <input type="file" name="flk" id="flk"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                        <button type="button" onclick="history.back()"
                            class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-times text-sm"></i>
                            <span>Batal</span>
                        </button>
                        <button type="submit" :disabled="requiresMppYear && !selectedMppYear"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2"
                            :class="(requiresMppYear && !selectedMppYear) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'">
                            <i class="fas fa-save text-sm"></i>
                            <span>Simpan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <!-- Success Message -->
    @if (session('success'))
        <div id="success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button onclick="document.getElementById('success-alert').remove()"
                    class="ml-2 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('success-alert');
                if (alert) alert.remove();
            }, 5000);
        </script>
    @endif

    <!-- Error Message -->
    @if (session('error'))
        <div id="error-alert" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ session('error') }}</span>
                <button onclick="document.getElementById('error-alert').remove()"
                    class="ml-2 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('error-alert');
                if (alert) alert.remove();
            }, 5000);
        </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const namaInput = document.getElementById('nama');
            const tanggalLahirInput = document.getElementById('tanggal_lahir');

            function checkDuplicate() {
                const nama = namaInput.value;
                const tanggalLahir = tanggalLahirInput.value;

                if (nama && tanggalLahir) {
                    fetch('{{ route('candidates.checkDuplicate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content')
                            },
                            body: JSON.stringify({
                                nama: nama,
                                tanggal_lahir: tanggalLahir
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                namaInput.classList.add('border-red-500');
                                tanggalLahirInput.classList.add('border-red-500');
                            } else {
                                namaInput.classList.remove('border-red-500');
                                tanggalLahirInput.classList.remove('border-red-500');
                            }
                        });
                }
            }

            namaInput.addEventListener('blur', checkDuplicate);
            tanggalLahirInput.addEventListener('change', checkDuplicate);
        });
    </script>
@endpush
