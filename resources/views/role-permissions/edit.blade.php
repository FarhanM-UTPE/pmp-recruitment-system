@extends('layouts.app')

@section('title', 'Edit Mapping Role Permission')
@section('page-title', 'Edit Mapping Role Permission')
@section('page-subtitle', 'Update permission yang melekat pada role')

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
        <h3 class="text-lg font-semibold text-gray-900">Edit Mapping: {{ $role->name }}</h3>
      </div>

      <form method="POST" action="{{ route('role-permissions.update', $role) }}" class="p-6 space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
          <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
            <ul class="list-disc pl-5">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
          <input type="text" value="{{ $role->name }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" readonly>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-3">Permissions</p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach ($permissions as $permission)
              <label
                class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                  @checked(in_array($permission->name, old('permissions', $selectedPermissions), true))>
                <span>{{ $permission->name }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <a href="{{ route('role-permissions.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm">
            Batal
          </a>
          <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
            <i class="fas fa-save mr-2"></i>
            Update Mapping
          </button>
        </div>
      </form>
    </div>
  @endcan
@endsection