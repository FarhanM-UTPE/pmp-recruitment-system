@extends('layouts.app')

@section('title', 'Edit Kandidat')
@section('page-title', 'Edit Kandidat')
@section('page-subtitle', 'Perbarui informasi kandidat')

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
                <h3 class="text-lg font-semibold text-gray-900">Form Edit Kandidat</h3>
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

                <form method="POST" action="{{ route('candidates.update', $candidate) }}" enctype="multipart/form-data"
                    class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Basic Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama" class="block text-sm font-medium text-gray-700">Nama *</label>
                                <input type="text" name="nama" id="nama" value="{{ old('nama', $candidate->nama) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>
                            <div>
                                <label for="alamat_email" class="block text-sm font-medium text-gray-700">Email *</label>
                                <input type="email" name="alamat_email" id="alamat_email"
                                    value="{{ old('alamat_email', $candidate->alamat_email) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>
                            <div>
                                <label for="applicant_id" class="block text-sm font-medium text-gray-700">Applicant ID *</label>
                                <input type="text" name="applicant_id" id="applicant_id"
                                    value="{{ old('applicant_id', $candidate->applicant_id) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 text-sm" required
                                    readonly>
                            </div>
                            <div>
                                <label for="jk" class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
                                <select name="jk" id="jk"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" {{ old('jk', $candidate->jk) == 'Laki-laki' ? 'selected' : '' }}>
                                        Laki-laki</option>
                                    <option value="Perempuan" {{ old('jk', $candidate->jk) == 'Perempuan' ? 'selected' : '' }}>
                                        Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label for="tanggal_lahir" class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" id="tanggal_lahir"
                                    value="{{ old('tanggal_lahir', $candidate->tanggal_lahir ? $candidate->tanggal_lahir->format('Y-m-d') : '') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
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
                                    value="{{ old('jenjang_pendidikan', $candidate->jenjang_pendidikan) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="perguruan_tinggi" class="block text-sm font-medium text-gray-700">Perguruan
                                    Tinggi</label>
                                <input type="text" name="perguruan_tinggi" id="perguruan_tinggi"
                                    value="{{ old('perguruan_tinggi', $candidate->perguruan_tinggi) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="jurusan" class="block text-sm font-medium text-gray-700">Jurusan</label>
                                <input type="text" name="jurusan" id="jurusan" value="{{ old('jurusan', $candidate->jurusan) }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label for="ipk" class="block text-sm font-medium text-gray-700">IPK</label>
                                <input type="number" name="ipk" id="ipk" value="{{ old('ipk', $candidate->ipk) }}" step="0.01"
                                    min="0" max="4"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Files -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Berkas</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="cv" class="block text-sm font-medium text-gray-700">Upload CV Baru
                                    (Opsional)</label>
                                <input type="file" name="cv" id="cv"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @if($candidate->cv)
                                    <p class="text-sm text-gray-500 mt-2">File saat ini: <a
                                            href="{{ Storage::url($candidate->cv) }}" target="_blank"
                                            class="text-blue-600 hover:underline">{{ basename($candidate->cv) }}</a>
                                    </p>
                                @endif
                            </div>
                            <div>
                                <label for="flk" class="block text-sm font-medium text-gray-700">Upload FLK Baru
                                    (Opsional)</label>
                                <input type="file" name="flk" id="flk"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @if($candidate->flk)
                                    <p class="text-sm text-gray-500 mt-2">File saat ini: <a
                                            href="{{ Storage::url($candidate->flk) }}" target="_blank"
                                            class="text-blue-600 hover:underline">{{ basename($candidate->flk) }}</a>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Edit History -->
                    @if($editHistories->isNotEmpty())
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Riwayat Perubahan</h4>
                            <div class="space-y-4">
                                @foreach($editHistories as $history)
                                    <div class="p-3 bg-white rounded-lg border border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <p class="text-sm text-gray-600">
                                                Diubah oleh <strong>{{ $history->user->name }}</strong> pada
                                                {{ $history->created_at->format('d M Y, H:i') }}
                                            </p>
                                        </div>
                                        <div class="mt-2">
                                            <ul class="list-disc pl-5 text-sm text-gray-800">
                                                @foreach($history->changes as $field => $value)
                                                    <li>
                                                        <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                        <span class="text-gray-600">{{ $value['old'] }}</span> &rarr; <span
                                                            class="text-green-600 font-semibold">{{ $value['new'] }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                        <button type="button" onclick="history.back()"
                            class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-times text-sm"></i>
                            <span>Batal</span>
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <i class="fas fa-save text-sm"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection