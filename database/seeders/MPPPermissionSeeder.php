<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MPPPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create MPP-related permissions
        $mppPermissions = [
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

        // Create permissions
        foreach ($mppPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Get roles
        $teamHCRole = Role::firstOrCreate(['name' => 'team_hc']);
        $kepalaDepartemenRole = Role::firstOrCreate(['name' => 'kepala departemen']);
        $divisionHeadRole = Role::firstOrCreate(['name' => 'division_head']);

        // Grant Team HC permissions for MPP management
        $teamHCRole->givePermissionTo([
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'approve-mpp-submission',
            'reject-mpp-submission',
            'delete-mpp-submission',
            'approve-vacancy-document',
            'reject-vacancy-document',
            'download-vacancy-document',
        ]);

        // Grant Department Head permissions for document upload
        $kepalaDepartemenRole->givePermissionTo([
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'upload-vacancy-document',
            'download-vacancy-document',
            'delete-vacancy-document',
        ]);

        $divisionHeadRole->givePermissionTo([
            'view-mpp-submissions',
            'create-mpp-submission',
            'submit-mpp-submission',
            'view-mpp-submission-details',
            'upload-vacancy-document',
            'download-vacancy-document',
            'delete-vacancy-document',
        ]);

        $this->command->info('MPP permissions seeded successfully');
    }
}
