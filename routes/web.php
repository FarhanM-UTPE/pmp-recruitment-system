<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\AssessmentImportController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PositionApplicantController;
use App\Http\Controllers\VacancyManagementController;
use App\Http\Controllers\VacancyProposalController;
use App\Http\Controllers\MPPSubmissionController;
use App\Http\Controllers\VacancyDocumentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\RolePermissionController;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
require __DIR__ . '/auth.php';

Route::get('/calendar', function () {
    return view('calendar.index');
})->middleware(['auth', 'verified'])->name('calendar');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard - NO PERMISSION MIDDLEWARE (all roles can access)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dashboard API Routes
    Route::get('/dashboard/stats/monthly', [DashboardController::class, 'getCandidateStatsByMonth'])->name('dashboard.stats.monthly');
    Route::get('/dashboard/years', [DashboardController::class, 'getAvailableYears'])->name('dashboard.years');

    // Calendar Events Routes - Integrated with Dashboard
    Route::prefix('events')->name('events.')->group(function () {
        // Main calendar events endpoint
        Route::get('/calendar', [DashboardController::class, 'getCalendarEvents'])->name('calendar');

        // Additional calendar endpoints
        Route::get('/today', [DashboardController::class, 'getTodayEvents'])->name('today');
        Route::get('/upcoming', [DashboardController::class, 'getUpcomingEvents'])->name('upcoming');
        Route::get('/by-date/range', [DashboardController::class, 'getEventsByDateRange'])->name('by-date');

        // Debug endpoint
        Route::get('/debug', [DashboardController::class, 'debugCalendarEvents'])->name('debug');

        // Event CRUD operations
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('/{event}', [EventController::class, 'show'])->name('show');
        Route::put('/{event}', [EventController::class, 'update'])->name('update');
        Route::delete('/{event}', [EventController::class, 'destroy'])->name('destroy');
    });

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Candidates Group - Let controller handle authorization
    Route::prefix('candidates')->name('candidates.')->group(function () {
        // Basic candidate viewing (let controller handle department restrictions)
        Route::get('/', [CandidateController::class, 'index'])->name('index');
        Route::get('/{candidate}', [CandidateController::class, 'show'])->name('show');

        // Export functionality for Team HC
        Route::middleware('can:export-candidates')->group(function () {
            Route::get('/export', [CandidateController::class, 'export'])->name('export');
            Route::post('/bulk-export', [CandidateController::class, 'bulkExport'])->name('bulkExport');
        });

        // Candidate creation for Team HC
        Route::middleware('can:create-candidates')->group(function () {
            Route::get('/create', [CandidateController::class, 'create'])->name('create');
            Route::post('/', [CandidateController::class, 'store'])->name('store');
        });

        // Candidate management actions for Team HC
        Route::middleware('can:edit-candidates')->group(function () {
            Route::get('/{candidate}/edit', [CandidateController::class, 'edit'])->name('edit');
            Route::put('/{candidate}', [CandidateController::class, 'update'])->name('update');
            Route::post('/{candidate}/switch-type', [CandidateController::class, 'switchType'])->name('switchType');
            Route::post('/bulk-switch-type', [CandidateController::class, 'bulkSwitchType'])->name('bulkSwitchType');

            Route::post('/bulk-update-status', [CandidateController::class, 'bulkUpdateStatus'])->name('bulkUpdateStatus');
            Route::post('/bulk-move-stage', [CandidateController::class, 'bulkMoveStage'])->name('bulkMoveStage');

            Route::post('/{candidate}/next-test-date', [CandidateController::class, 'setNextTestDate'])->name('setNextTestDate');
            Route::post('/check-duplicate', [CandidateController::class, 'checkDuplicate'])->name('checkDuplicate');
        });

        // Delete actions for Team HC
        Route::middleware('can:delete-candidates')->group(function () {
            Route::delete('/{candidate}', [CandidateController::class, 'destroy'])->name('destroy');
        });

        // Ensure show route is at the end with numeric constraint
        Route::get('/{candidate}', [CandidateController::class, 'show'])
            ->whereNumber('candidate')
            ->name('show');
    });

    Route::post('/applications/{application}/stage', [CandidateController::class, 'updateStage'])->name('applications.updateStage');
    Route::post('/applications/{application}/reset-stage', [CandidateController::class, 'resetStage'])->name('applications.resetStage');
    Route::post('/applications/{application}/cancel-move', [CandidateController::class, 'cancelMove'])->name('applications.cancelMove');
    Route::post('/applications/{application}/move-position', [CandidateController::class, 'movePosition'])->name('applications.move-position');

    // Bulk Delete route outside the prefix group
    Route::delete('/candidates/bulk-actions/delete', [CandidateController::class, 'bulkDelete'])->name('candidates.bulkDelete');
    Route::post('/candidates/bulk-actions/mark-as-duplicate', [CandidateController::class, 'bulkMarkAsDuplicate'])->name('candidates.bulkMarkAsDuplicate');

    // Import Routes for Team HC
    Route::prefix('import')->name('import.')->middleware('can:import-excel')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::post('/preview', [ImportController::class, 'preview'])->name('preview');
        Route::post('/confirm', [ImportController::class, 'confirmImport'])->name('confirm');
        Route::post('/cancel', [ImportController::class, 'cancelImport'])->name('cancel');

        Route::get('/assessment', [AssessmentImportController::class, 'index'])->name('assessment.index');
        Route::post('/assessment/preview', [AssessmentImportController::class, 'preview'])->name('assessment.preview');
        Route::post('/assessment/confirm', [AssessmentImportController::class, 'confirmImport'])->name('assessment.confirm');
        Route::post('/assessment/cancel', [AssessmentImportController::class, 'cancelImport'])->name('assessment.cancel');

        Route::get('/template/{type?}', [ImportController::class, 'downloadTemplate'])->name('template');
        Route::get('/errors', function () {
            return view('import.errors', ['errors' => []]);
        })->name('errors');
    });

    // Statistics for Team HC and Department
    Route::get('/statistics', [StatisticsController::class, 'index'])
        ->middleware('can:view-statistics')
        ->name('statistics.index');

    Route::get('/statistics/vacancies', [\App\Http\Controllers\VacancyStatisticsController::class, 'index'])
        ->middleware('can:view-statistics')
        ->name('statistics.vacancies');

    // Reports for Team HC
    Route::get('/reports/export', [ReportController::class, 'export'])
        ->middleware('can:view-reports')
        ->name('reports.export');

    // Account management for Admin only
    Route::prefix('accounts')->name('accounts.')->middleware('can:manage-users')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/create', [AccountController::class, 'create'])->name('create');
        Route::post('/', [AccountController::class, 'store'])->name('store');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
        Route::get('/export', [AccountController::class, 'export'])->name('export');
    });

    // Role-Permission attachment management for Admin
    Route::prefix('role-permissions')->name('role-permissions.')->middleware('can:manage-users')->group(function () {
        Route::get('/', [RolePermissionController::class, 'index'])->name('index');
        Route::get('/create', [RolePermissionController::class, 'create'])->name('create');
        Route::post('/', [RolePermissionController::class, 'store'])->name('store');
        Route::get('/{rolePermission}/edit', [RolePermissionController::class, 'edit'])->name('edit');
        Route::put('/{rolePermission}', [RolePermissionController::class, 'update'])->name('update');
        Route::delete('/{rolePermission}', [RolePermissionController::class, 'destroy'])->name('destroy');
    });

    // Departemen management for Admin only
    Route::resource('departments', DepartmentController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:manage-departments');

    // Route for Posisi & Pelamar
    Route::get('/posisi-pelamar', [PositionApplicantController::class, 'index'])->name('posisi-pelamar.index')->middleware('can:view-posisi-pelamar');
    Route::put('/posisi-pelamar/{vacancy}/update-details', [PositionApplicantController::class, 'updateVacancyDetails'])->name('posisi-pelamar.update-details')->middleware('can:view-posisi-pelamar');

    // Vacancy Management for Admin only
    Route::resource('vacancies', VacancyManagementController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:manage-vacancies');

    Route::get('/test-route', function () {
        return 'This is a test route';
    });

    // MPP Submission Routes
    Route::prefix('mpp-submissions')->name('mpp-submissions.')->middleware('auth')->group(function () {
        Route::get('/', [MPPSubmissionController::class, 'index'])->name('index');
        Route::get('/create', [MPPSubmissionController::class, 'create'])->name('create');
        Route::get('/create-new', [MPPSubmissionController::class, 'createNew'])->name('create-new');
        Route::post('/', [MPPSubmissionController::class, 'store'])->name('store');
        Route::post('/store-new', [MPPSubmissionController::class, 'storeNew'])->name('store-new');
        Route::post('/mass-approve', [MPPSubmissionController::class, 'massApprove'])->name('mass-approve');
        Route::post('/{mppSubmission}/approve-stage', [MPPSubmissionController::class, 'approveStage'])->name('approve-stage');
        Route::post('/{mppSubmission}/disapprove-stage', [MPPSubmissionController::class, 'disapproveStage'])->name('disapprove-stage');
        Route::post('/{mppSubmission}/sync-vacancy', [MPPSubmissionController::class, 'syncVacancy'])->name('sync-vacancy')->middleware('can:view-mpp-submission-details');
        Route::get('/{mppSubmission}', [MPPSubmissionController::class, 'show'])->name('show');
        Route::delete('/{mppSubmission}', [MPPSubmissionController::class, 'destroy'])->name('destroy');

        // Per-vacancy approval actions
        Route::post('/{mppSubmission}/vacancy/{vacancy}/approve', [MPPSubmissionController::class, 'approveVacancy'])->name('approve-vacancy');
        Route::post('/{mppSubmission}/vacancy/{vacancy}/reject', [MPPSubmissionController::class, 'rejectVacancy'])->name('reject-vacancy');
    });

    // Vacancy Document Routes
    Route::prefix('vacancies/{vacancy}/documents')->name('vacancy-documents.')->middleware('auth')->group(function () {
        Route::get('/', [VacancyDocumentController::class, 'showUploadForm'])->name('upload');
        Route::post('/', [VacancyDocumentController::class, 'upload'])->name('store');
        Route::get('/{document}/preview', [VacancyDocumentController::class, 'preview'])->name('preview');
        Route::get('/{document}/download', [VacancyDocumentController::class, 'download'])->name('download');
        Route::post('/{document}/approve', [VacancyDocumentController::class, 'approve'])->name('approve');
        Route::post('/{document}/reject', [VacancyDocumentController::class, 'reject'])->name('reject');
        Route::delete('/{document}', [VacancyDocumentController::class, 'destroy'])->name('destroy');
    });

    // Document Routes
    Route::middleware('can:manage-documents')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    });

    Route::get('/masterdata', [MasterDataController::class, 'index'])
        ->middleware('can:manage-master-data')
        ->name('masterdata.index');

    Route::post('/masterdata', [MasterDataController::class, 'store'])
        ->middleware('can:manage-master-data')
        ->name('masterdata.store');

    Route::put('/masterdata/{masterData}', [MasterDataController::class, 'update'])
        ->middleware('can:manage-master-data')
        ->name('masterdata.update');

    Route::delete('/masterdata/{masterData}', [MasterDataController::class, 'destroy'])
        ->middleware('can:manage-master-data')
        ->name('masterdata.destroy');

    // Debug route for testing permissions
    Route::get('/check-auth', function () {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not logged in'], 401);
        }
        return response()->json([
            'user' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'can_view_dashboard' => $user->can('view-dashboard'),
            'can_view_candidates' => $user->can('view-candidates'),
            'can_view_reports' => $user->can('view-reports'),
            'can_import_excel' => $user->can('import-excel'),
            'can_manage_users' => $user->can('manage-users'),
            'can_manage_documents' => $user->can('manage-documents'),
            'can_manage_master_data' => $user->can('manage-master-data'),
            'can_manage_departments' => $user->can('manage-departments'),
            'can_view_posisi_pelamar' => $user->can('view-posisi-pelamar'),
            'can_manage_vacancies' => $user->can('manage-vacancies'),
        ]);
    });

    // Secure File Serving
    Route::get('/private-files/{filePath}', [FileController::class, 'serveFile'])
        ->where('filePath', '.*')
        ->name('files.serve');
});