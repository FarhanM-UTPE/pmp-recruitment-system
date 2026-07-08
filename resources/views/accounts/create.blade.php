@extends('layouts.app')

@section('title', 'Tambah Akun')
@section('page-title', 'Tambah Akun')
@section('page-subtitle', 'Buat akun pengguna baru')

@push('header-filters')
    <button onclick="history.back()"
        class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
        <i class="fas fa-arrow-left text-sm"></i>
        <span>Kembali</span>
    </button>
@endpush

@section('content')
    @can('manage-users')
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Form Akun</h3>
            </div>

            <div class="p-6">
                {{-- Error Messages --}}
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

                <form method="POST" action="{{ route('accounts.store') }}" class="space-y-6">
                    @csrf

                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Informasi Akun</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Nama --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Nama *</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>

                            {{-- Nama Penandatangan Approval --}}
                            <div>
                                <label for="approval_display_name" class="block text-sm font-medium text-gray-700">Nama Penandatangan (Approval)</label>
                                <input type="text" name="approval_display_name" id="approval_display_name" value="{{ old('approval_display_name') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="Contoh: Budi Santoso">
                                <p class="text-xs text-gray-500 mt-1">Jika diisi, nama ini akan dipakai di matrix approval MPP.</p>
                            </div>

                            {{-- Nama Divisi --}}
                            <div id="division-name-field" style="display: none;">
                                <label for="division_name" class="block text-sm font-medium text-gray-700">Nama Divisi *</label>
                                <input type="text" name="division_name" id="division_name" value="{{ old('division_name') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="Contoh: Marketing & Sales">
                                <p class="text-xs text-gray-500 mt-1">Wajib diisi untuk role Division Head.</p>
                            </div>

                            {{-- NRP --}}
                            <div>
                                <label for="nrp" class="block text-sm font-medium text-gray-700">NRP</label>
                                <input type="text" name="nrp" id="nrp" value="{{ old('nrp') }}"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    placeholder="Contoh: 1234">
                                <p class="text-xs text-gray-500 mt-1">Wajib diisi jika role Kepala Departemen atau Division Head.</p>
                            </div>

                            {{-- Password --}}
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                                <input type="password" name="password" id="password"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>

                            {{-- Konfirmasi Password --}}
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi
                                    Password *</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                            </div>

                            {{-- Role --}}
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role *</label>
                                <select name="role" id="role"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                                    <option value="">Pilih Role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                            @if($role->name === 'admin')
                                                Administrator
                                            @elseif($role->name === 'team_hc')
                                                Team HC
                                            @elseif($role->name === 'team_hc_2')
                                                Team HC 2
                                            @elseif($role->name === 'kepala departemen')
                                                Kepala Departemen
                                            @elseif($role->name === 'division_head')
                                                Division Head
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Department Field (Hidden by default) --}}
                            <div id="department-field" class="col-span-1" style="display: none;">
                                <label for="department_id" class="block text-sm font-medium text-gray-700">Departemen *</label>
                                <select name="department_id" id="department_id"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="">Pilih Departemen</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Multi Department Access --}}
                            <div id="division-departments-field" class="col-span-1 md:col-span-2">
                                <label for="accessible_department_ids" class="block text-sm font-medium text-gray-700">Akses Departemen</label>
                                @php
                                    $selectedAccessibleDepartments = old('accessible_department_ids', []);
                                @endphp
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 border border-gray-300 rounded-lg p-3 bg-white">
                                    @foreach($departments as $dept)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                name="accessible_department_ids[]"
                                                value="{{ $dept->id }}"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                {{ in_array($dept->id, $selectedAccessibleDepartments) ? 'checked' : '' }}
                                            >
                                            <span>{{ $dept->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Pilih satu atau lebih departemen. Untuk Kepala Departemen, sistem akan mengikuti departemen utama.</p>
                            </div>

                            {{-- Status --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status *</label>
                                <select name="status" id="status"
                                    class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required>
                                    <option value="">Pilih Status</option>
                                    <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Tidak Aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Simpan Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- Success Message --}}
    @if(session('success'))
        <div id="success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button onclick="document.getElementById('success-alert').remove()" class="ml-2 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    @endif

    {{-- Error Message --}}
    @if(session('error'))
        <div id="error-alert" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ session('error') }}</span>
                <button onclick="document.getElementById('error-alert').remove()" class="ml-2 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('role');
            const departmentField = document.getElementById('department-field');
            const departmentSelect = document.getElementById('department_id');
            const divisionNameField = document.getElementById('division-name-field');
            const divisionNameInput = document.getElementById('division_name');
            const divisionDepartmentsField = document.getElementById('division-departments-field');
            const nrpInput = document.getElementById('nrp');

            function toggleDepartmentField() {
                const selectedRole = roleSelect.value;

                if (selectedRole === 'kepala departemen') {
                    departmentField.style.display = 'block';
                    departmentSelect.setAttribute('required', 'required');

                    divisionNameField.style.display = 'none';
                    divisionNameInput.removeAttribute('required');
                    divisionNameInput.value = '';
                    divisionDepartmentsField.style.display = 'block';

                    nrpInput.setAttribute('required', 'required');
                } else if (selectedRole === 'division_head') {
                    departmentField.style.display = 'none';
                    departmentSelect.removeAttribute('required');
                    departmentSelect.value = '';

                    divisionNameField.style.display = 'block';
                    divisionNameInput.setAttribute('required', 'required');
                    divisionDepartmentsField.style.display = 'block';

                    nrpInput.setAttribute('required', 'required');
                } else {
                    departmentField.style.display = 'none';
                    departmentSelect.removeAttribute('required');
                    departmentSelect.value = ''; // Clear selection

                    divisionNameField.style.display = 'none';
                    divisionNameInput.removeAttribute('required');
                    divisionNameInput.value = '';
                    divisionDepartmentsField.style.display = 'block';

                    nrpInput.removeAttribute('required');
                }
            }

            // Event listener untuk perubahan role
            roleSelect.addEventListener('change', toggleDepartmentField);

            // Initial check saat halaman dimuat
            toggleDepartmentField();
        });

        // Auto hide success/error alerts
        setTimeout(() => {
            const successAlert = document.getElementById('success-alert');
            const errorAlert = document.getElementById('error-alert');

            if (successAlert) successAlert.remove();
            if (errorAlert) errorAlert.remove();
        }, 5000);
    </script>
@endpush