<?php
// database/seeders/DatabaseSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seeder order:
     * 1. RolesAndPermissionsSeeder - Create 4 roles (admin, team_hc, team_hc_2, department_head)
     * 2. DepartmentSeeder - Create 11 departments
     * 3. UserSeeder - Create users for each role + department heads
     * 4. DepartmentUsersSeeder - (Optional) Create additional department users if needed
     * 5. VacancySeeder - Create vacancies for each department
     */
    public function run()
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            DepartmentUsersSeeder::class,
            DivisionHeadAccountsSeeder::class,
            VacancySeeder::class,
            MPPPermissionSeeder::class,  // MPP permissions & role assignments
            MasterDataSeeder::class,
        ]);

        // Register permissions with the Gate facade
        app()[\Spatie\Permission\PermissionRegistrar::class]->registerPermissions(
            app(\Illuminate\Contracts\Auth\Access\Gate::class)
        );

        $this->command->info('✅ Database seeding completed successfully');
    }
}