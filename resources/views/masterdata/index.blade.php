@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-4 lg:px-6">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Master Data</h1>
                    <p class="mt-2 text-gray-600">Pilih stage name dan status sudah disediakan.</p>
                </div>
                <button id="open-create-modal" type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Tambah Data
                </button>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Daftar Master Data</h2>
                    </div>
                    <!-- <div class="rounded-2xl bg-gray-50 border border-gray-200 px-4 py-3">
                            <p class="text-sm text-gray-500">Jumlah baris master data</p>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $total }}</p>
                        </div> -->
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Stage
                                    Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Status
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Tahun
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">
                                    Departemen</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wider">Total
                                </th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 uppercase tracking-wider">Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($masterData as $item)
                                @php
                                    [$stageName, $status] = explode('|', $item->key);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-gray-800">{{ $stageKeys[$stageName] ?? $stageName }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $status }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $item->year ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $item->department?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $item->value }}</td>
                                    <td class="px-4 py-3 text-center space-x-2">
                                        <button type="button"
                                            class="edit-masterdata-button inline-flex items-center justify-center rounded-xl border border-blue-600 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                            data-id="{{ $item->id }}" data-stage="{{ $stageName }}"
                                            data-status="{{ $status }}" data-year="{{ $item->year }}"
                                            data-department-id="{{ $item->department_id }}"
                                            data-total="{{ $item->value }}">
                                            Edit
                                        </button>
                                        <form action="{{ route('masterdata.destroy', $item) }}" method="POST"
                                            class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center justify-center rounded-xl border border-red-600 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                                onclick="return confirm('Hapus data master ini?');">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-gray-500" colspan="6">Belum ada data master.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="masterdata-modal"
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/50 backdrop-blur-sm transition-all">
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-6">

            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white p-6 shadow-2xl sm:p-8"
                style="border-radius: 1.5rem;">

                <div class="flex items-start justify-between">
                    <div>
                        <h2 id="modal-title" class="text-xl font-semibold text-gray-900">Tambah Master Data</h2>
                        <p class="mt-1 text-sm text-gray-500">Isi stage name, status, dan total kandidat.</p>
                    </div>
                    <button type="button" id="close-masterdata-modal"
                        class="rounded-full bg-gray-50 p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="masterdata-form" action="{{ route('masterdata.store') }}" method="POST" class="mt-8 space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="modal-method" value="POST">
                    <input type="hidden" name="edit_id" id="modal-edit-id" value="{{ old('edit_id', '') }}">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stage Name</label>
                        <select name="stage_name" id="modal-stage-name"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm transition-colors focus:border-gray-900 focus:ring-gray-900">
                            @foreach ($stageKeys as $key => $label)
                                <option value="{{ $key }}" {{ old('stage_name') === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="modal-status"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm transition-colors focus:border-gray-900 focus:ring-gray-900">
                            @foreach ($statusKeys as $key => $label)
                                <option value="{{ $key }}" {{ old('status') === $key ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tahun (Opsional)</label>
                        <input type="number" name="year" id="modal-year" value="{{ old('year') }}" min="2000"
                            max="2100"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm transition-colors focus:border-gray-900 focus:ring-gray-900"
                            placeholder="Contoh: 2026" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Departemen (Opsional)</label>
                        <select name="department_id" id="modal-department-id"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm transition-colors focus:border-gray-900 focus:ring-gray-900">
                            <option value="">-- Pilih Departemen --</option>
                            @foreach ($departments ?? [] as $dept)
                                <option value="{{ $dept->id }}"
                                    {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total</label>
                        <input type="number" name="total" id="modal-total" value="{{ old('total') }}"
                            min="0"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm transition-colors focus:border-gray-900 focus:ring-gray-900"
                            placeholder="Jumlah kandidat" />
                    </div>

                    <div class="mt-8 flex flex-col gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" id="cancel-masterdata-modal"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900 sm:w-auto">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-colors hover:bg-gray-800 sm:w-auto">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('masterdata-modal');
        const form = document.getElementById('masterdata-form');
        const modalTitle = document.getElementById('modal-title');
        const modalMethod = document.getElementById('modal-method');
        const modalEditId = document.getElementById('modal-edit-id');
        const stageSelect = document.getElementById('modal-stage-name');
        const statusSelect = document.getElementById('modal-status');
        const yearInput = document.getElementById('modal-year');
        const departmentSelect = document.getElementById('modal-department-id');
        const totalInput = document.getElementById('modal-total');
        const storeUrl = "{{ route('masterdata.store') }}";
        const baseUpdateUrl = "{{ url('/masterdata') }}";
        const oldValues = {!! json_encode([
            'edit_id' => old('edit_id'),
            'stage_name' => old('stage_name'),
            'status' => old('status'),
            'year' => old('year'),
            'department_id' => old('department_id'),
            'total' => old('total'),
            'hasErrors' => $errors->any(),
        ]) !!};

        function openMasterDataModal(mode, data = {}) {
            form.action = mode === 'edit' ? `${baseUpdateUrl}/${data.id}` : storeUrl;
            modalTitle.textContent = mode === 'edit' ? 'Edit Master Data' : 'Tambah Master Data';
            modalMethod.value = mode === 'edit' ? 'PUT' : 'POST';
            modalEditId.value = mode === 'edit' ? data.id ?? '' : '';

            stageSelect.value = data.stage_name ?? oldValues.stage_name ?? stageSelect.value;
            statusSelect.value = data.status ?? oldValues.status ?? statusSelect.value;
            yearInput.value = data.year ?? oldValues.year ?? '';
            departmentSelect.value = data.department_id ?? oldValues.department_id ?? '';
            totalInput.value = data.total ?? oldValues.total ?? '';

            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        document.getElementById('open-create-modal').addEventListener('click', function() {
            openMasterDataModal('create');
        });

        document.querySelectorAll('.edit-masterdata-button').forEach(function(button) {
            button.addEventListener('click', function() {
                openMasterDataModal('edit', {
                    id: button.dataset.id,
                    stage_name: button.dataset.stage,
                    status: button.dataset.status,
                    year: button.dataset.year,
                    department_id: button.dataset.departmentId,
                    total: button.dataset.total,
                });
            });
        });

        document.querySelectorAll('#cancel-masterdata-modal, #close-masterdata-modal').forEach(function(button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        window.addEventListener('DOMContentLoaded', function() {
            if (oldValues.edit_id) {
                openMasterDataModal('edit', {
                    id: oldValues.edit_id,
                    stage_name: oldValues.stage_name,
                    status: oldValues.status,
                    year: oldValues.year,
                    department_id: oldValues.department_id,
                    total: oldValues.total,
                });
            } else if (oldValues.hasErrors) {
                openMasterDataModal('create');
            }
        });
    </script>
@endsection
