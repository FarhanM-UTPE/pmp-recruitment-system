@extends('layouts.app')

@section('title', 'Edit Akun')
@section('page-title', 'Edit Akun')
@section('page-subtitle', 'Perbarui informasi akun pengguna')

@push('header-filters')
    <button onclick="history.back()"
        class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
        <i class="fas fa-arrow-left text-sm"></i>
        <span>Kembali</span>
    </button>
@endpush

@section('content')
    @can('manage-users')
        <div class="max-w-2xl mx-auto">

            @if ($errors->any())
                <div class="bg-red-50 text-red-800 p-4 rounded-lg mb-4">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="bg-green-50 text-green-800 p-4 rounded-lg mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('accounts.update', $account->id) }}" method="POST" class="bg-white shadow-md rounded-lg p-6">
                @csrf
                @method('PUT')
                <div class="grid gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $account->name) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $account->email) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="nrp" class="block text-sm font-medium text-gray-700">NRP</label>
                        <input type="text" name="nrp" id="nrp" value="{{ old('nrp', $account->nrp) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Contoh: 1234">
                        <p class="text-xs text-gray-500 mt-1">Wajib diisi jika role Kepala Departemen.</p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Kata Sandi Baru (opsional)</label>
                        <input type="password" name="password" id="password"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            onchange="toggleDepartment(this.value)">
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ $account->hasRole($role->name) ? 'selected' : '' }}>
                                    @if($role->name === 'admin')
                                        Administrator
                                    @elseif($role->name === 'team_hc')
                                        Team HC
                                    @elseif($role->name === 'kepala departemen')
                                        Kepala Departemen
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="department-field"
                        style="display: {{ $account->hasRole('kepala departemen') ? 'block' : 'none' }};">
                        <label for="department_id" class="block text-sm font-medium text-gray-700">Department</label>
                        <select name="department_id" id="department_id"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Departemen</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id', $account->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:focus:border-blue-500">
                            <option value="1" {{ $account->status ? 'selected' : '' }}>Aktif</option>
                            <option value="0" {{ !$account->status ? 'selected' : '' }}>Non-Aktif</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit"
                            class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        function toggleDepartment(role) {
            const departmentField = document.getElementById('department-field');
            const departmentSelect = document.getElementById('department_id');
            const nrpInput = document.getElementById('nrp');

            if (role === 'kepala departemen') {
                departmentField.style.display = 'block';
                departmentSelect.setAttribute('required', 'required');
                nrpInput.setAttribute('required', 'required');
            } else {
                departmentField.style.display = 'none';
                departmentSelect.removeAttribute('required');
                nrpInput.removeAttribute('required');
            }
        }
        // Initial check on page load
        document.addEventListener('DOMContentLoaded', function () {
            toggleDepartment(document.getElementById('role').value);
        });
    </script>
@endpush