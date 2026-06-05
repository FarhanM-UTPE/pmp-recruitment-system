@extends('layouts.app')

@section('title', 'Tambah Posisi Baru')
@section('page-title', 'Tambah Posisi Baru')
@section('page-subtitle', 'Formulir untuk menambahkan posisi baru')

@push('header-filters')
    <button onclick="history.back()"
        class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
        <i class="fas fa-arrow-left text-sm"></i>
        <span>Kembali</span>
    </button>
@endpush

@section('content')
    <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
        <form action="{{ route('vacancies.store') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Nama Posisi</label>
                <input type="text" name="name" id="name"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required>
                @error('name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="department_id" class="block text-sm font-medium text-gray-700">Departemen</label>
                <select name="department_id" id="department_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required>
                    <option value="">Pilih Departemen</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">Aktif</label>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Simpan Posisi
                </button>
                <button type="button" onclick="history.back()"
                    class="ml-3 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </button>
            </div>
        </form>
    </div>
@endsection