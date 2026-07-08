<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use App\Models\Department;

class AccountController extends Controller
{


    public function index()
    {
        $users = User::with('roles')->orderBy('created_at', 'desc')->paginate(10);
        $departmentsMap = Department::orderBy('name')->pluck('name', 'id');

        $stats = [
            'total' => User::count(),
            'admin' => User::role('admin')->count(),
            'team_hc' => User::role('team_hc')->count(),
            'team_hc_2' => User::role('team_hc_2')->count(),
            'division_head' => User::role('division_head')->count(),
            'kepala_departemen' => User::role('kepala departemen')->count(),
            'active' => User::where('status', true)->count(),
        ];

        return view('accounts.index', compact('users', 'stats', 'departmentsMap'));
    }

    public function create()
    {
        $roles = Role::whereNotIn('name', ['admin', 'department_head', 'department', 'user'])->get();
        $departments = Department::all();
        return view('accounts.create', [
            'departments' => $departments,
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'approval_display_name' => 'nullable|string|max:255',
            'division_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nrp' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nrp')],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'status' => 'required|boolean',
            'accessible_department_ids' => 'nullable|array',
            'accessible_department_ids.*' => 'integer|exists:departments,id',
        ];

        // Hanya tambahkan validasi department jika role adalah kepala departemen
        if ($request->role === 'kepala departemen') {
            $validationRules['department_id'] = 'required|exists:departments,id';
            $validationRules['nrp'][0] = 'required';
        }

        if ($request->role === 'division_head') {
            $validationRules['division_name'] = 'required|string|max:255';
            $validationRules['accessible_department_ids'] = 'required|array|min:1';
            $validationRules['nrp'][0] = 'required';
        }

        if ($request->role === 'team_hc' || $request->role === 'team_hc_2' || $request->role === 'admin') {
            $validationRules['division_name'] = 'nullable';
        }

        $request->validate($validationRules);

        $accessibleDepartmentIds = collect($request->input('accessible_department_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($request->role === 'kepala departemen') {
            $accessibleDepartmentIds = [(int) $request->department_id];
        }

        // Buat user baru
        $user = User::create([
            'name' => $request->name,
            'approval_display_name' => $request->filled('approval_display_name') ? trim((string) $request->approval_display_name) : null,
            'division_name' => $request->filled('division_name') ? trim((string) $request->division_name) : null,
            'email' => $request->email,
            'nrp' => $request->filled('nrp') ? trim((string) $request->nrp) : null,
            'password' => Hash::make($request->password),
            'department_id' => $request->role === 'kepala departemen' ? $request->department_id : null,
            'accessible_department_ids' => empty($accessibleDepartmentIds) ? null : $accessibleDepartmentIds,
            'status' => (bool) $request->status,
            'email_verified_at' => now(),
        ]);

        // Assign role menggunakan Spatie Permission
        $user->assignRole($request->role);

        return redirect()->route('accounts.index')
            ->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $account)
    {
        $roles = Role::where('name', '!=', 'admin')->get();
        $departments = Department::all();
        $account->load('roles');

        return view('accounts.edit', [
            'account' => $account,
            'departments' => $departments,
            'roles' => $roles
        ]);
    }

    public function update(Request $request, User $account)
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'approval_display_name' => 'nullable|string|max:255',
            'division_name' => 'nullable|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'nrp' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nrp')->ignore($account->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'status' => 'required|boolean',
            'accessible_department_ids' => 'nullable|array',
            'accessible_department_ids.*' => 'integer|exists:departments,id',
        ];

        // Hanya tambahkan validasi department jika role adalah kepala departemen
        if ($request->role === 'kepala departemen') {
            $validationRules['department_id'] = 'required|exists:departments,id';
            $validationRules['nrp'][0] = 'required';
        }

        if ($request->role === 'division_head') {
            $validationRules['division_name'] = 'required|string|max:255';
            $validationRules['accessible_department_ids'] = 'required|array|min:1';
            $validationRules['nrp'][0] = 'required';
        }

        if ($request->role === 'team_hc' || $request->role === 'team_hc_2' || $request->role === 'admin') {
            $validationRules['division_name'] = 'nullable';
        }

        $request->validate($validationRules);

        $accessibleDepartmentIds = collect($request->input('accessible_department_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($request->role === 'kepala departemen') {
            $accessibleDepartmentIds = [(int) $request->department_id];
        }

        $updateData = [
            'name' => $request->name,
            'approval_display_name' => $request->filled('approval_display_name') ? trim((string) $request->approval_display_name) : null,
            'division_name' => $request->filled('division_name') ? trim((string) $request->division_name) : null,
            'email' => $request->email,
            'nrp' => $request->filled('nrp') ? trim((string) $request->nrp) : null,
            'department_id' => $request->role === 'kepala departemen' ? $request->department_id : null,
            'accessible_department_ids' => empty($accessibleDepartmentIds) ? null : $accessibleDepartmentIds,
            'status' => (bool) $request->status,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $account->update($updateData);

        // Update role menggunakan Spatie Permission
        $account->syncRoles([$request->role]);

        return redirect()->route('accounts.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(User $account)
    {
        // Prevent deleting the last admin
        if ($account->hasRole('admin') && User::role('admin')->count() <= 1) {
            return redirect()->route('accounts.index')
                ->with('error', 'Tidak dapat menghapus admin terakhir.');
        }

        // Prevent self-deletion
        if ($account->id === Auth::user()->id) {
            return redirect()->route('accounts.index')
                ->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function export()
    {
        return Excel::download(new \App\Exports\UsersExport, 'users_' . now()->format('Ymd_His') . '.xlsx');
    }
}