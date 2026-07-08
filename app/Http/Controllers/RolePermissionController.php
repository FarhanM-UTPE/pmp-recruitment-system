<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
  /**
   * Display role-permission mappings.
   */
  public function index(): View
  {
    $roles = Role::query()
      ->with('permissions')
      ->orderBy('name')
      ->get();

    return view('role-permissions.index', [
      'roles' => $roles,
    ]);
  }

  /**
   * Show form to attach permissions to a role.
   */
  public function create(): View
  {
    $roles = Role::query()->orderBy('name')->get();
    $permissions = Permission::query()->orderBy('name')->get();

    return view('role-permissions.create', [
      'roles' => $roles,
      'permissions' => $permissions,
    ]);
  }

  /**
   * Store role-permission attachment.
   */
  public function store(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'role_id' => 'required|exists:roles,id',
      'permissions' => 'nullable|array',
      'permissions.*' => 'required|string|exists:permissions,name',
    ]);

    $role = Role::query()->findOrFail($validated['role_id']);
    $permissionNames = array_values($validated['permissions'] ?? []);

    // Sync writes into role_has_permissions via Spatie package.
    $role->syncPermissions($permissionNames);

    return redirect()
      ->route('role-permissions.index')
      ->with('success', 'Mapping role-permission berhasil disimpan.');
  }

  /**
   * Show form to edit role-permission attachment.
   */
  public function edit(Role $rolePermission): View
  {
    $rolePermission->load('permissions');

    $permissions = Permission::query()->orderBy('name')->get();
    $selectedPermissions = $rolePermission->permissions->pluck('name')->values()->all();

    return view('role-permissions.edit', [
      'role' => $rolePermission,
      'permissions' => $permissions,
      'selectedPermissions' => $selectedPermissions,
    ]);
  }

  /**
   * Update role-permission attachment.
   */
  public function update(Request $request, Role $rolePermission): RedirectResponse
  {
    $validated = $request->validate([
      'permissions' => 'nullable|array',
      'permissions.*' => 'required|string|exists:permissions,name',
    ]);

    $permissionNames = array_values($validated['permissions'] ?? []);
    $rolePermission->syncPermissions($permissionNames);

    return redirect()
      ->route('role-permissions.index')
      ->with('success', 'Mapping role-permission berhasil diperbarui.');
  }

  /**
   * Remove all permissions from a role mapping.
   */
  public function destroy(Role $rolePermission): RedirectResponse
  {
    $rolePermission->syncPermissions([]);

    return redirect()
      ->route('role-permissions.index')
      ->with('success', 'Semua permission pada role berhasil dilepas.');
  }
}
