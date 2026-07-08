<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * 4 Roles:
     * 1. admin - Admin sistem recruitment
     * 2. team_hc - Tim HC utama (full access)
     * 3. team_hc_2 - Tim HC kedua (same as team_hc, approval workflow step 2)
     * 4. department_head - Kepala departemen (own department only)
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create comprehensive permissions list
        $permissions = [
            // Dashboard access
            'view-dashboard',

            // User/Account management
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Candidate management
            'view-candidates',
            'view-own-department-candidates',
            'create-candidates',
            'edit-candidates',
            'show-candidates',
            'delete-candidates',

            // Import/Export functionality
            'import-excel',
            'export-candidates',
            'download-template',

            // Bulk operations
            'bulk-update-candidates',
            'bulk-delete-candidates',
            'bulk-export-candidates',
            'bulk-move-stage',
            'bulk-switch-type',

            // Stage management
            'update-stage',
            'move-stage',
            'edit-timeline',

            // Statistics and reports
            'view-statistics',
            'view-reports',

            // Events/Calendar management
            'view-events',
            'create-events',
            'edit-events',
            'delete-events',
            'manage-calendar',

            // Duplicate management
            'manage-duplicates',
            'mark-duplicate',
            'resolve-duplicate',

            // Document management
            'manage-documents',
            'manage-master-data',
            'view-candidate-documents',
            'manage-departments',
            'view-posisi-pelamar',
            'manage-vacancies',

            // Vacancy Proposals
            'propose-vacancy',
            'review-vacancy-proposals-step-1',
            'review-vacancy-proposals-step-2',

            // MPP Submission Permissions
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'approve-mpp-submission',
            'reject-mpp-submission',
            'delete-mpp-submission',

            // Vacancy Document Permissions
            'upload-vacancy-document',
            'download-vacancy-document',
            'approve-vacancy-document',
            'reject-vacancy-document',
            'delete-vacancy-document',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create 4 roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $teamHCRole = Role::firstOrCreate(['name' => 'team_hc']);
        $teamHC2Role = Role::firstOrCreate(['name' => 'team_hc_2']);
        $kepalaDepartemenRole = Role::firstOrCreate(['name' => 'kepala departemen']);
        $divisionHeadRole = Role::firstOrCreate(['name' => 'division_head']);

        // ============================================
        // 1. ADMIN - Manajemen sistem & user saja
        // ============================================
        $adminRole->syncPermissions([]);
        $adminRole->givePermissionTo([
            'view-dashboard',
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'manage-departments',
            'manage-vacancies',
            'view-reports',
            'view-candidate-documents',
        ]);

        // ============================================
        // 2. TEAM HC - Tim HC utama (full candidate access)
        // ============================================
        $teamHCRole->syncPermissions([]);
        $teamHCRole->givePermissionTo([
            'view-dashboard',
            'view-candidates',
            'create-candidates',
            'edit-candidates',
            'show-candidates',
            'delete-candidates',
            'import-excel',
            'export-candidates',
            'download-template',
            'bulk-update-candidates',
            'bulk-delete-candidates',
            'bulk-export-candidates',
            'bulk-move-stage',
            'bulk-switch-type',
            'update-stage',
            'move-stage',
            'edit-timeline',
            'view-statistics',
            'view-reports',
            'view-events',
            'create-events',
            'edit-events',
            'delete-events',
            'manage-calendar',
            'manage-duplicates',
            'mark-duplicate',
            'resolve-duplicate',
            'manage-documents',
            'manage-master-data',
            'view-candidate-documents',
            'view-posisi-pelamar',
            'review-vacancy-proposals-step-1',
            'propose-vacancy',
            // MPP permissions
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'approve-mpp-submission',
            'reject-mpp-submission',
            'delete-mpp-submission',
            'upload-vacancy-document',
            'approve-vacancy-document',
            'reject-vacancy-document',
            'download-vacancy-document',
        ]);

        // ============================================
        // 3. TEAM HC 2 - Tim HC kedua (same as Team HC 1)
        // ============================================
        $teamHC2Role->syncPermissions([]);
        $teamHC2Role->givePermissionTo([
            'view-dashboard',
            'view-candidates',
            'create-candidates',
            'edit-candidates',
            'show-candidates',
            'delete-candidates',
            'import-excel',
            'export-candidates',
            'download-template',
            'bulk-update-candidates',
            'bulk-delete-candidates',
            'bulk-export-candidates',
            'bulk-move-stage',
            'bulk-switch-type',
            'update-stage',
            'move-stage',
            'edit-timeline',
            'view-statistics',
            'view-reports',
            'view-events',
            'create-events',
            'edit-events',
            'delete-events',
            'manage-calendar',
            'manage-duplicates',
            'mark-duplicate',
            'resolve-duplicate',
            'manage-documents',
            'manage-master-data',
            'view-candidate-documents',
            'view-posisi-pelamar',
            'review-vacancy-proposals-step-1',
            'propose-vacancy',
            // MPP permissions
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'approve-mpp-submission',
            'reject-mpp-submission',
            'delete-mpp-submission',
            'upload-vacancy-document',
            'approve-vacancy-document',
            'reject-vacancy-document',
            'download-vacancy-document',
        ]);

        // ============================================
        // 4. KEPALA DEPARTEMEN - Kepala departemen
        // ============================================
        $departmentHeadPermissions = [
            'view-dashboard',
            'view-own-department-candidates',
            'show-candidates',
            'view-candidate-documents',
            'view-statistics',
            'propose-vacancy',
            'view-events',
            // MPP permissions for document upload
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'upload-vacancy-document',
            'download-vacancy-document',
            'delete-vacancy-document',
        ];

        $kepalaDepartemenRole->syncPermissions($departmentHeadPermissions);
        $divisionHeadRole->syncPermissions($departmentHeadPermissions);

        // Output seeding information
        $this->command->info('✅ Roles and permissions seeded successfully:');
        $this->command->info('   1. admin - Admin sistem recruitment');
        $this->command->info('   2. team_hc - Tim HC utama (full access)');
        $this->command->info('   3. team_hc_2 - Tim HC kedua (same as team_hc, approval step 2)');
        $this->command->info('   4. kepala departemen - Kepala departemen (own dept only)');
        $this->command->info('   5. division_head - Division Head (multi department scope)');
        $this->command->info('   Total permissions: ' . Permission::count());
    }
}