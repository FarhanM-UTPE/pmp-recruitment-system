@extends('layouts.app')

@section('title', 'Role Permission')
@section('page-title', 'Role Permission')
@section('page-subtitle', 'Kelola mapping role ke permission')

@section('content')
  @can('manage-users')
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Mapping Role Permission</h3>
        <a href="{{ route('role-permissions.create') }}"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
          <i class="fas fa-plus mr-2"></i>
          Tambah Mapping
        </a>
      </div>

      @if (session('success'))
        <div class="mx-6 mt-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
          {{ session('success') }}
        </div>
      @endif

      <div class="overflow-x-auto mt-6">
        <table class="w-full">
          <thead class="bg-gray-50 border-y border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            @forelse ($roles as $role)
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                  {{ $role->name }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">
                  @if ($role->permissions->isEmpty())
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">Belum ada permission</span>
                  @else
                    <div class="flex flex-wrap gap-2">
                      @foreach ($role->permissions as $permission)
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">{{ $permission->name }}</span>
                      @endforeach
                    </div>
                  @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <div class="flex items-center gap-3">
                    <a href="{{ route('role-permissions.edit', $role) }}" class="text-blue-600 hover:text-blue-800"
                      title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('role-permissions.destroy', $role) }}" method="POST"
                      onsubmit="return confirm('Lepas semua permission dari role ini?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="text-red-600 hover:text-red-800" title="Lepas semua permission">
                        <i class="fas fa-link-slash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-center py-12 text-gray-500">
                  Belum ada role tersedia.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endcan
@endsection