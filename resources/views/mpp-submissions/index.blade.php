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
        @can('create-mpp-submission')
          <a href="{{ route('mpp-submissions.create') }}"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            + Buat Pengajuan Baru
          </a>
        @endcan
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

      <!-- Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis
                MPP</th>
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
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->department->name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ $mpp->createdByUser->name }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  @if ($mpp->vacancies->count() > 0)
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
                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
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