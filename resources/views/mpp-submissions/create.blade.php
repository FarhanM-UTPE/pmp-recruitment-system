@extends('layouts.app')

@push('header-filters')
<button onclick="history.back()" class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
    <i class="fas fa-arrow-left text-sm"></i>
    <span>Kembali</span>
</button>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Buat Pengajuan MPP</h1>
            <p class="mt-2 text-gray-600">Buat pengajuan manpower planning untuk departemen Anda</p>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow">
            <form method="POST" action="{{ route('mpp-submissions.store') }}" class="p-6">
                @csrf

                <!-- Department Selection -->
                <div class="mb-6">
                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Departemen <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="department_id"
                        name="department_id"
                        class="w-full px-3 py-2 border @error('department_id') border-red-500 @else border-gray-300 @enderror rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        onchange="updateAvailablePositions()"
                        required
                    >
                        <option value="">Pilih Departemen</option>
                        @foreach ($departments as $dept)
                        <option value="{{ $dept['id'] }}" @selected(old('department_id') === $dept['id'])>
                            {{ $dept['name'] }}
                        </option>
                        @endforeach
                    </select>
                    @error('department_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Year Selection -->
                <div class="mb-6">
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                        Tahun <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="year"
                        name="year"
                        class="w-full px-3 py-2 border @error('year') border-red-500 @else border-gray-300 @enderror rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required
                    >
                        @php
                            $currentYear = date('Y');
                            $startYear = $currentYear - 3; // 3 years before current
                            $endYear = $currentYear + 1; // 1 year after current
                            $years = range($endYear, $startYear); // From endYear down to startYear
                        @endphp
                        @foreach ($years as $yr)
                        <option value="{{ $yr }}" @selected(old('year', $currentYear) == $yr)>
                            {{ $yr }}
                        </option>
                        @endforeach
                    </select>
                    @error('year')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submission Type Selection -->
                <div class="mb-6">
                    <label for="submission_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipe Pengajuan <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="submission_type"
                        name="submission_type"
                        class="w-full px-3 py-2 border @error('submission_type') border-red-500 @else border-gray-300 @enderror rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required
                    >
                        <option value="planned" @selected(old('submission_type', 'planned') === 'planned')>Sesuai MPP (Planned)</option>
                        <option value="unplanned" @selected(old('submission_type') === 'unplanned')>Di Luar MPP (Unplanned)</option>
                    </select>
                    @error('submission_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Positions Section -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Posisi yang Diajukan <span class="text-red-500">*</span>
                        </label>
                        <button
                            type="button"
                            onclick="addPosition()"
                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
                        >
                            + Tambah Posisi
                        </button>
                    </div>

                    <div id="positions-container" class="space-y-4">
                        <!-- Positions will be added here dynamically -->
                    </div>

                    <div id="empty-message" class="text-center py-6 bg-gray-50 rounded border border-gray-200">
                        <p class="text-gray-500">Belum ada posisi yang ditambahkan</p>
                    </div>

                    @error('positions')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex gap-4">
                    <button
                        type="submit"
                        id="submit-btn"
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Simpan & Kirim
                    </button>
                    <button
                        type="button"
                        onclick="history.back()"
                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 inline-block"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Position Template (hidden) -->
<template id="position-template">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded border border-gray-200 position-row">
        <!-- Position Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Posisi <span class="text-red-500">*</span>
            </label>
            <select
                name="positions[][vacancy_id]"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 position-select position-vacancy-id"
                required
            >
                <option value="">Pilih Posisi</option>
            </select>
        </div>

        <!-- Vacancy Status -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status Vacancy <span class="text-red-500">*</span>
            </label>
            <select
                name="positions[][vacancy_status]"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 position-vacancy-status"
                required
            >
                <option value="">Pilih Status</option>
                <option value="OSPKWT">OSPKWT (Wajib upload Dokumen A1)</option>
                <option value="OS">OS (Wajib upload Dokumen B1)</option>
            </select>
        </div>

        <!-- Jumlah yang Dibutuhkan -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Jumlah <span class="text-red-500">*</span>
            </label>
            <input
                type="number"
                name="positions[][needed_count]"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 position-needed-count"
                placeholder="Jumlah"
                min="1"
                required
            >
        </div>

        <!-- Remove Button -->
        <div class="flex items-end">
            <button
                type="button"
                onclick="removePosition(this)"
                class="w-full px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 font-medium"
            >
                Hapus
            </button>
        </div>
    </div>
</template>

<script>
const positions = @json($positions);
let positionCount = 0;

function validateForm() {
    const deptId = document.getElementById('department_id').value;
    const positionRows = document.querySelectorAll('.position-row');
    
    console.log('=== FORM VALIDATION ===');
    console.log('Department ID:', deptId);
    console.log('Position rows:', positionRows.length);
    
    let errors = [];
    
    if (!deptId) {
        errors.push('Departemen harus dipilih');
    }
    
    if (positionRows.length === 0) {
        errors.push('Minimal 1 posisi harus ditambahkan');
    }
    
    // Validate each position row
    positionRows.forEach((row, index) => {
        const vacancySelect = row.querySelector('.position-vacancy-id');
        const statusSelect = row.querySelector('.position-vacancy-status');
        const neededInput = row.querySelector('.position-needed-count');
        
        console.log(`Row ${index}:`, {
            vacancy: vacancySelect?.value || 'KOSONG',
            status: statusSelect?.value || 'KOSONG',
            needed: neededInput?.value || 'KOSONG'
        });
        
        if (!vacancySelect || !vacancySelect.value) {
            errors.push(`Baris ${index + 1}: Pilih posisi`);
        }
        
        if (!statusSelect || !statusSelect.value) {
            errors.push(`Baris ${index + 1}: Pilih status vacancy`);
        }
        
        if (!neededInput || !neededInput.value || parseInt(neededInput.value) < 1) {
            errors.push(`Baris ${index + 1}: Isi jumlah yang valid`);
        }
    });
    
    if (errors.length > 0) {
        console.log('VALIDATION ERRORS:');
        errors.forEach(err => console.log('  -', err));
        alert(errors.join('\n'));
        return false;
    }
    
    console.log('✓ VALIDATION PASSED');
    return true;
}

function updateAvailablePositions() {
    const deptId = document.getElementById('department_id').value;
    const positionRows = document.querySelectorAll('.position-row');

    if (!deptId) {
        positionRows.forEach(row => {
            const select = row.querySelector('.position-select');
            select.innerHTML = '<option value="">Pilih Posisi</option>';
        });
        return;
    }

    const deptPositions = positions.find(p => p.department_id == deptId);
    const availablePos = deptPositions?.positions || [];

    positionRows.forEach(row => {
        const select = row.querySelector('.position-select');
        const currentValue = select.value;

        select.innerHTML = '<option value="">Pilih Posisi</option>';
        availablePos.forEach(pos => {
            const option = document.createElement('option');
            option.value = pos.id;
            option.text = pos.name;
            if (currentValue == pos.id) option.selected = true;
            select.appendChild(option);
        });
    });
}

function addPosition() {
    const template = document.getElementById('position-template');
    const clone = template.content.cloneNode(true);
    const container = document.getElementById('positions-container');

    // Fix names to use explicit index
    const inputs = clone.querySelectorAll('[name^="positions[]"]');
    inputs.forEach(input => {
        input.name = input.name.replace('positions[]', `positions[${positionCount}]`);
    });

    container.appendChild(clone);
    positionCount++;

    // Update available positions for the new row
    updateAvailablePositions();
    document.getElementById('empty-message').style.display = 'none';
}

function removePosition(button) {
    const row = button.closest('.position-row');
    row.remove();

    const container = document.getElementById('positions-container');
    if (container.children.length === 0) {
        document.getElementById('empty-message').style.display = 'block';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submit-btn');
    
    // Attach form submit handler
    form.addEventListener('submit', function(e) {
        console.log('Form submit event triggered');
        
        if (!validateForm()) {
            console.log('Validation failed, preventing submit');
            e.preventDefault();
            return false;
        }
        
        console.log('Validation passed, allowing submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Menyimpan...';
        
        // Log form data
        const formData = new FormData(form);
        console.log('Submitting form with data:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
    });
    
    // If there are old values, add rows for them
    const oldPositions = @json(old('positions', []));
    const oldDepartmentId = @json(old('department_id'));
    
    if (oldPositions.length > 0) {
        // Set department value
        if (oldDepartmentId) {
            document.getElementById('department_id').value = oldDepartmentId;
            updateAvailablePositions();
        }
        
        // Add rows and populate with old values
        let rowIndex = 0;
        oldPositions.forEach((oldPosition) => {
            addPosition();
            rowIndex++;
        });
        
        // Wait for all DOM updates, then populate values
        setTimeout(() => {
            const rows = document.querySelectorAll('.position-row');
            rows.forEach((row, index) => {
                if (index < oldPositions.length) {
                    const oldPosition = oldPositions[index];
                    
                    // Populate vacancy_id
                    const vacancySelect = row.querySelector('.position-vacancy-id');
                    if (vacancySelect && oldPosition.vacancy_id) {
                        vacancySelect.value = oldPosition.vacancy_id;
                    }
                    
                    // Populate vacancy_status
                    const statusSelect = row.querySelector('.position-vacancy-status');
                    if (statusSelect && oldPosition.vacancy_status) {
                        statusSelect.value = oldPosition.vacancy_status;
                    }
                    
                    // Populate needed_count
                    const neededInput = row.querySelector('.position-needed-count');
                    if (neededInput && oldPosition.needed_count) {
                        neededInput.value = oldPosition.needed_count;
                    }
                }
            });
        }, 100);
    }
});
</script>
@endsection
