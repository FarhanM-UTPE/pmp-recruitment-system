@extends('layouts.app')

@section('title', 'Manajemen Akun')
@section('page-title', 'Manajemen Akun')
@section('page-subtitle', 'Kelola akun pengguna sistem')

@section('content')

        <div class="flex-1 p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-xl p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Total Akun</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Admin</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['admin'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Team HC</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['team_hc'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Team HC 2</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['team_hc_2'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Division Head</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['division_head'] ?? 0 }}</p>
                </div>
            </div>

            @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            <!-- Users Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Akun Pengguna</h3>
                    @can('manage-users')
                    <a href="{{ route('accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>
                        Tambah Akun
                    </a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-blue-600">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                            <div class="text-xs text-gray-400">NRP: {{ $user->nrp ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $user->role_display_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->division_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <div>{{ $user->department?->name ?? '-' }}</div>
                                    @php
                                        $departmentNames = collect($user->accessible_department_ids ?? [])
                                            ->map(fn($id) => $departmentsMap[$id] ?? null)
                                            ->filter()
                                            ->values();
                                    @endphp
                                    @if($departmentNames->isNotEmpty())
                                        <div class="text-xs text-gray-500 mt-1">
                                            Akses: {{ $departmentNames->implode(', ') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->status)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Non-Aktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        @can('manage-users')
                                        <a href="{{ route('accounts.edit', $user->id) }}" class="text-blue-600 hover:text-blue-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if(Auth::user()->id !== $user->id)
                                        <form action="{{ route('accounts.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Anda yakin ingin menghapus akun ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-12">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">Belum ada data akun.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </main>
@endsection
