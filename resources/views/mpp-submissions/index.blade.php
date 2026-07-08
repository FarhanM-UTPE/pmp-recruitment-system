@extends('layouts.app')

@section('content')
  <div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Pengajuan MPP</h1>
          <p class="mt-2 text-gray-600">Daftar pengajuan manpower planning</p>
        </div>
        @if(($canCreateOldFormMpp ?? false) || ($canCreateNewFormMpp ?? false))
          <div class="flex items-center gap-2">
            @if($canCreateOldFormMpp ?? false)
              <a href="{{ route('mpp-submissions.create') }}"
                class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">
                + Buat Pengajuan Lama
              </a>
            @endif
            @if($canCreateNewFormMpp ?? false)
              <a href="{{ route('mpp-submissions.create-new') }}"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                + Buat Pengajuan Baru
              </a>
            @endif
          </div>
        @endif
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-slate-500">
          <p class="text-xs uppercase tracking-wide text-slate-500">Total Terlihat</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ $mppSummary['total_visible'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-500">
          <p class="text-xs uppercase tracking-wide text-amber-600">Menunggu</p>
          <p class="mt-2 text-2xl font-bold text-amber-700">{{ $mppSummary['submitted'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
          <p class="text-xs uppercase tracking-wide text-emerald-600">Disetujui</p>
          <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $mppSummary['approved'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-rose-500">
          <p class="text-xs uppercase tracking-wide text-rose-600">Ditolak</p>
          <p class="mt-2 text-2xl font-bold text-rose-700">{{ $mppSummary['rejected'] ?? 0 }}</p>
        </div>
        <div class="bg-indigo-50 rounded-lg shadow p-4 border-l-4 border-indigo-600">
          <p class="text-xs uppercase tracking-wide text-indigo-700">Perlu Approval Saya</p>
          <p class="mt-2 text-2xl font-bold text-indigo-900">{{ $mppSummary['need_my_approval'] ?? 0 }}</p>
        </div>
      </div>

      <!-- Success Message -->
      @if ($message = Session::get('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
          {{ $message }}
        </div>
      @endif

      <!-- Error Message -->
      @if ($message = Session::get('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
          {{ $message }}
        </div>
      @endif

      <!-- My Approval Queue -->
      <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-indigo-50">
          <h2 class="text-lg font-semibold text-indigo-900">MPP Menunggu Approval Anda</h2>
          <p class="text-sm text-indigo-700 mt-1">Daftar prioritas item yang bisa Anda approve sekarang.</p>
        </div>
        <form method="POST" action="{{ route('mpp-submissions.mass-approve') }}">
          @csrf
          <div class="px-6 py-3 border-b border-gray-200 bg-white flex items-center justify-between gap-3">
            <p class="text-sm text-gray-600">Pilih beberapa item lalu klik <span class="font-semibold">Approve Terpilih</span>.</p>
            <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
              Approve Terpilih
            </button>
          </div>
          <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input type="checkbox" id="queue-select-all" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MPP ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahap Anda</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              @forelse (($myApprovalQueue ?? collect()) as $queueItem)
                @php
                  $mpp = $queueItem['submission'];
                @endphp
                <tr class="hover:bg-indigo-50/40">
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <input
                      type="checkbox"
                      name="selected[]"
                      value="{{ $mpp->id }}:{{ $queueItem['stage_index'] }}"
                      class="queue-item-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded"
                    >
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">#{{ $mpp->id }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $mpp->department->name ?? '-' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    @if ($mpp->submission_type === 'planned')
                      <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">Sesuai MPP</span>
                    @else
                      <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">Di Luar MPP</span>
                    @endif
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-amber-100 text-amber-800 text-xs font-medium">
                      {{ $queueItem['stage_role'] }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $mpp->created_at->format('d M Y H:i') }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <a href="{{ route('mpp-submissions.show', $mpp) }}" class="inline-flex items-center px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                      Buka & Approve
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="px-6 py-5 text-sm text-gray-500 text-center">
                    Tidak ada MPP yang menunggu approval Anda saat ini.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
          </div>
        </form>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" action="{{ route('mpp-submissions.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
              <option value="">Semua Status</option>
              <option value="draft" @selected(request('status') === 'draft')>Draft</option>
              <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
              <option value="approved" @selected(request('status') === 'approved')>Approved</option>
              <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
            <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md">
              <option value="">Semua Tahun</option>
              @foreach ($years as $year)
                <option value="{{ $year }}" @selected(request('year') == $year)>{{ $year }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Filter</button>
          </div>
        </form>
      </div>

      <!-- Full Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h2 class="text-lg font-semibold text-gray-900">Semua Pengajuan MPP</h2>
          <p class="text-sm text-gray-600 mt-1">Gunakan filter untuk mempersempit pencarian.</p>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis
                MPP</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versi
                Form</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Posisi</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tanggal Dibuat</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            @forelse ($mppSubmissions as $index => $mpp)
              <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ ($mppSubmissions->currentPage() - 1) * $mppSubmissions->perPage() + $index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->year }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  @if ($mpp->submission_type === 'planned')
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Sesuai
                      MPP</span>
                  @else
                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">Di
                      Luar MPP</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  @if (($mpp->form_version ?? 'old') === 'new')
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-medium">Baru</span>
                  @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Lama</span>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->department->name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->createdByUser->name }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  @if (($mpp->form_version ?? 'old') === 'new')
                    @if ($mpp->vacancy)
                      <span class="inline-block px-2 py-1 bg-emerald-100 text-emerald-800 rounded text-xs">
                        {{ $mpp->vacancy->name }}
                      </span>
                    @elseif (!empty($mpp->custom_jabatan_name))
                      <span class="inline-block px-2 py-1 bg-amber-100 text-amber-800 rounded text-xs">
                        {{ $mpp->custom_jabatan_name }}
                      </span>
                    @else
                      <span class="inline-block px-2 py-1 bg-emerald-100 text-emerald-800 rounded text-xs">
                        Form Digital MPP
                      </span>
                    @endif
                  @elseif ($mpp->vacancies->count() > 0)
                    <div class="flex flex-wrap gap-2">
                      @foreach ($mpp->vacancies as $vacancy)
                        <span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                          {{ $vacancy->name }}
                        </span>
                      @endforeach
                    </div>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                  @php
                    $approved = $mpp->vacancies->where('pivot.proposal_status', 'approved')->count();
                    $rejected = $mpp->vacancies->where('pivot.proposal_status', 'rejected')->count();
                    $pending = $mpp->vacancies->reject(function ($v) {
                      return in_array($v->pivot->proposal_status, ['approved', 'rejected']);
                    })->count();
                  @endphp
                  <div class="space-y-1">
                    <p><span class="text-green-600 font-bold">✓</span> Disetujui: {{ $approved }}</p>
                    <p><span class="text-yellow-600 font-bold">⏳</span> Menunggu: {{ $pending }}</p>
                    <p><span class="text-red-600 font-bold">✕</span> Ditolak: {{ $rejected }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->created_at->format('d M Y H:i') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                  <a href="{{ route('mpp-submissions.show', $mpp) }}" class="text-blue-600 hover:text-blue-900">Lihat</a>
                  @if (auth()->user()->can('delete-mpp-submission'))
                    <form action="{{ route('mpp-submissions.destroy', $mpp) }}" method="POST" style="display:inline;">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="text-red-600 hover:text-red-900"
                        onclick="return confirm('Apakah Anda yakin ingin menghapus MPP ini?')">Hapus</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada pengajuan MPP
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      @if ($mppSubmissions->lastPage() > 1)
        <div class="mt-6">
          {{ $mppSubmissions->links() }}
        </div>
      @endif
    </div>
  </div>
@endsection

@php
  function getStatusLabel($status)
  {
    return match ($status) {
      'draft' => 'Draft',
      'submitted' => 'Submitted',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      default => $status,
    };
  }

  function getStatusBadgeClass($status)
  {
    return match ($status) {
      'draft' => 'bg-gray-100 text-gray-800',
      'submitted' => 'bg-yellow-100 text-yellow-800',
      'approved' => 'bg-green-100 text-green-800',
      'rejected' => 'bg-red-100 text-red-800',
      default => 'bg-gray-100 text-gray-800',
    };
  }
@endphp

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('queue-select-all');
    if (!selectAll) {
      return;
    }

    const checkboxes = Array.from(document.querySelectorAll('.queue-item-checkbox'));

    selectAll.addEventListener('change', function () {
      checkboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
      });
    });

    checkboxes.forEach(function (checkbox) {
      checkbox.addEventListener('change', function () {
        selectAll.checked = checkboxes.length > 0 && checkboxes.every(function (item) {
          return item.checked;
        });
      });
    });
  });
</script>