<?php

use Illuminate\Support\Facades\Route;

// Admin Routes
// Dashboard
use App\Http\Controllers\Admin\AdminDashboardController;
// Cells
use App\Http\Controllers\Admin\AdminCellController;
// Nodes
use App\Http\Controllers\Admin\AdminNodeController;
use App\Http\Controllers\Admin\AdminNodeAllocationController;
// Combs
use App\Http\Controllers\Admin\AdminCombController;
use App\Http\Controllers\Admin\AdminCombImportController;
// Settings
use App\Http\Controllers\Admin\AdminSettingsController;
// Users
use App\Http\Controllers\Admin\AdminUserController;

// User Routes
// Dashboard
use App\Http\Controllers\DashboardController;

// Cells
use App\Http\Controllers\Cells\CellActivityController;
use App\Http\Controllers\Cells\CellAuditLogController;
use App\Http\Controllers\Cells\CellBackupController;
use App\Http\Controllers\Cells\CellController;
use App\Http\Controllers\Cells\CellConfigController;
use App\Http\Controllers\Cells\CellConsoleController;
use App\Http\Controllers\Cells\CellFileController;
use App\Http\Controllers\Cells\CellImporterController;
use App\Http\Controllers\Cells\CellPlayerController;
use App\Http\Controllers\Cells\CellPowerController;
use App\Http\Controllers\Cells\CellScheduleController;
use App\Http\Controllers\Cells\CellSettingsController;
use App\Http\Controllers\Cells\CellSftpCredentialController;
use App\Http\Controllers\Cells\CellSubUserController;
use App\Http\Controllers\Install\WorkerInstallScriptController;
use App\Support\CellPermissions;

Route::get('/schedule-actions', [CellScheduleController::class, 'actionDefinitions'])->name('schedule-actions');
Route::post('/cron/generate', [CellScheduleController::class, 'generateCron'])->name('cron.generate');
Route::post('/workflows/generate', [CellScheduleController::class, 'generateWorkflow'])->name('workflows.generate');

Route::get('/install/worker.sh', [WorkerInstallScriptController::class, 'show'])->name('install.worker');

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin Routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        // Dashboard
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        // Nodes
        Route::prefix('nodes')->name('nodes.')->group(function () {
            Route::get('/', [AdminNodeController::class, 'index'])->name('index');
            Route::post('/', [AdminNodeController::class, 'store'])->name('store');
            Route::get('/create', [AdminNodeController::class, 'create'])->name('create');
            Route::get('{node}', [AdminNodeController::class, 'show'])->name('show');
            Route::patch('{node}', [AdminNodeController::class, 'update'])->name('update');
            Route::delete('{node}', [AdminNodeController::class, 'destroy'])->name('destroy');
            Route::get('{node}/stats-json', [AdminNodeController::class, 'statsJson'])->name('stats-json');
            Route::get('{node}/settings', [AdminNodeController::class, 'settings'])->name('settings');
            Route::patch('{node}/settings', [AdminNodeController::class, 'updateSettings'])->name('settings.update');
            Route::get('{node}/configuration', [AdminNodeController::class, 'configuration'])->name('configuration');
            Route::post('{node}/registration-token', [AdminNodeController::class, 'generateRegistrationToken'])->name('registration-token');

            // Allocations

            Route::get('{node}/allocations', [AdminNodeAllocationController::class, 'index'])->name('allocations');
            Route::post('{node}/allocations', [AdminNodeAllocationController::class, 'store'])->name('allocations.store');
            Route::delete('{node}/allocations/{allocation}', [AdminNodeAllocationController::class, 'destroy'])->name('allocations.destroy');
            Route::patch('{node}/allocations/{allocation}/reserve', [AdminNodeAllocationController::class, 'reserve'])->name('allocations.reserve');
            Route::get('{node}/available-allocations', [AdminCellController::class, 'allocations'])->name('available-allocations');
        });

        // Cells
        Route::prefix('cells')->name('cells.')->group(function () {
            Route::get('/', [AdminCellController::class, 'index'])->name('index');
            Route::get('/create', [AdminCellController::class, 'create'])->name('create');
            Route::post('/', [AdminCellController::class, 'store'])->name('store');

            Route::get('{cell}/edit', [AdminCellController::class, 'edit'])->name('edit');
            Route::patch('{cell}', [AdminCellController::class, 'update'])->name('update');
            Route::delete('{cell}', [AdminCellController::class, 'destroy'])->name('destroy');

            Route::get('{cell}', [AdminCellController::class, 'show'])->name('show');
        });

        // Combs
        Route::prefix('combs')->name('combs.')->group(function () {
            Route::get('/', [AdminCombController::class, 'index'])->name('index');
            Route::get('/create', [AdminCombController::class, 'create'])->name('create');
            Route::post('/', [AdminCombController::class, 'store'])->name('store');
            Route::post('/registry/{id}/import', [AdminCombController::class, 'importFromRegistry'])->name('registry.import');
            Route::get('/{comb}', [AdminCombController::class, 'show'])->name('show');
            Route::delete('/{comb}', [AdminCombController::class, 'destroy'])->name('destroy');
            Route::get('/{comb}/edit', [AdminCombController::class, 'edit'])->name('edit');
            Route::put('/{comb}', [AdminCombController::class, 'update'])->name('update');
        });

        // Settings
        Route::prefix('settings')->name('admin.settings.')->group(function () {
            Route::get('/', [AdminSettingsController::class, 'index'])->name('index');

            Route::patch('/general', [AdminSettingsController::class, 'updateGeneral'])->name('general.update');
            Route::patch('/security', [AdminSettingsController::class, 'updateSecurity'])->name('security.update');
            Route::patch('/mail', [AdminSettingsController::class, 'updateMail'])->name('mail.update');
            Route::post('/mail/test', [AdminSettingsController::class, 'testMail'])->name('mail.test');
            Route::patch('/captcha', [AdminSettingsController::class, 'updateCaptcha'])->name('captcha.update');
            Route::patch('/oauth', [AdminSettingsController::class, 'updateOAuth'])->name('oauth.update');
        });

        // Users
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
            Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
            Route::patch('/{user}', [AdminUserController::class, 'update'])->name('update');
        });
    });

    // User Routes
    // Dashboard
    Route::get('/', DashboardController::class)->name('dashboard');

    // Cells
    Route::prefix('cells')->name('cells.')->group(function () {
        Route::get('/', [CellController::class, 'index'])->name('index');
        Route::post('/', [CellController::class, 'store'])->name('store');
        Route::get('/{id}', [CellController::class, 'show'])->name('show')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);

        Route::post('/{id}/start', [CellPowerController::class, 'start'])->name('start')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_POWER);
        Route::post('/{id}/stop', [CellPowerController::class, 'stop'])->name('stop')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_POWER);

        Route::get('/{id}/stats-json', [CellConsoleController::class, 'statsJson'])->name('stats-json')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);
        Route::get('/{id}/console-json', [CellConsoleController::class, 'consoleJson'])->name('console-json')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);
        Route::post('/{id}/command', [CellConsoleController::class, 'command'])->name('command')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_SEND);
        Route::post('/{id}/console-session', [CellConsoleController::class, 'consoleSession'])->name('console-session')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);

        Route::get('/{id}/activity', [CellActivityController::class, 'index'])->name('activity')
            ->middleware('cell.permission:' . CellPermissions::ACTIVITY_VIEW);
        Route::get('/{id}/activity-json', [CellActivityController::class, 'json'])->name('activity-json')
            ->middleware('cell.permission:' . CellPermissions::ACTIVITY_VIEW);

        Route::get('/{id}/files', [CellFileController::class, 'index'])->name('files')
            ->middleware('cell.permission:' . CellPermissions::FILES_VIEW);
        Route::get('/{id}/files-json', [CellFileController::class, 'json'])->name('files-json')
            ->middleware('cell.permission:' . CellPermissions::FILES_VIEW);
        Route::get('/{id}/files/download', [CellFileController::class, 'download'])->name('files.download')
            ->middleware('cell.permission:' . CellPermissions::FILES_READ);
        Route::get('/{id}/files/edit', [CellFileController::class, 'edit'])->name('files.edit')
            ->middleware('cell.permission:' . CellPermissions::FILES_READ);
        Route::get('/{id}/files/read', [CellFileController::class, 'read'])->name('files.read')
            ->middleware('cell.permission:' . CellPermissions::FILES_READ);
        Route::put('/{id}/files/write', [CellFileController::class, 'write'])->name('files.write')
            ->middleware('cell.permission:' . CellPermissions::FILES_WRITE);
        Route::delete('/{id}/files/delete', [CellFileController::class, 'delete'])->name('files.delete')
            ->middleware('cell.permission:' . CellPermissions::FILES_DELETE);
        Route::post('/{id}/files/restore', [CellFileController::class, 'restore'])->name('files.restore')
            ->middleware('cell.permission:' . CellPermissions::FILES_RESTORE);
        Route::delete('/{id}/files/permanent', [CellFileController::class, 'permanent'])->name('files.permanent')
            ->middleware('cell.permission:' . CellPermissions::FILES_DELETE);
        Route::post('/{id}/files/file', [CellFileController::class, 'createFile'])->name('files.create-file')
            ->middleware('cell.permission:' . CellPermissions::FILES_WRITE);
        Route::post('/{id}/files/folder', [CellFileController::class, 'createFolder'])->name('files.create-folder')
            ->middleware('cell.permission:' . CellPermissions::FILES_WRITE);
        Route::post('/{id}/files/upload-url', [CellFileController::class, 'uploadFromUrl'])->name('files.upload-url')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);
        Route::post('/{id}/files/upload', [CellFileController::class, 'upload'])->name('files.upload')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);

        Route::get('/{id}/players', [CellPlayerController::class, 'index'])->name('players.index')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);
        Route::get('/{id}/players-json', [CellPlayerController::class, 'json'])->name('players.json')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_VIEW);
        Route::post('/{id}/players/{action}', [CellPlayerController::class, 'action'])->name('players.action')
            ->middleware('cell.permission:' . CellPermissions::CONSOLE_SEND);

        Route::get('/{id}/users', [CellSubUserController::class, 'index'])->name('users.index')
            ->middleware('cell.permission:' . CellPermissions::USERS_VIEW);
        Route::get('/{id}/users/create', [CellSubUserController::class, 'create'])->name('users.create')
            ->middleware('cell.permission:' . CellPermissions::USERS_INVITE);
        Route::post('/{id}/users', [CellSubUserController::class, 'store'])->name('users.store')
            ->middleware('cell.permission:' . CellPermissions::USERS_INVITE);
        Route::get('/{id}/users/{user}/edit', [CellSubUserController::class, 'edit'])->name('users.edit')
            ->middleware('cell.permission:' . CellPermissions::USERS_UPDATE);
        Route::put('/{id}/users/{user}', [CellSubUserController::class, 'update'])->name('users.update')
            ->middleware('cell.permission:' . CellPermissions::USERS_UPDATE);
        Route::delete('/{id}/users/{user}', [CellSubUserController::class, 'destroy'])->name('users.destroy')
            ->middleware('cell.permission:' . CellPermissions::USERS_REMOVE);

        Route::get('/{id}/settings', [CellSettingsController::class, 'index'])->name('settings.index')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_VIEW);
        Route::patch('/{id}/settings', [CellSettingsController::class, 'update'])->name('settings.update')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_UPDATE);
        Route::post('/{id}/utilities/{utility}', [CellSettingsController::class, 'utility'])->name('utilities.run')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_UPDATE);

        Route::get('/{id}/config', [CellConfigController::class, 'index'])->name('config.index')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_VIEW);
        Route::get('/{id}/config-json', [CellConfigController::class, 'json'])->name('config.json')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_VIEW);
        Route::patch('/{id}/config-json', [CellConfigController::class, 'update'])->name('config.update')
            ->middleware('cell.permission:' . CellPermissions::SETTINGS_UPDATE);

        Route::get('/{id}/backups', [CellBackupController::class, 'index'])->name('backups.index')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_VIEW);
        Route::get('/{id}/backups-json', [CellBackupController::class, 'json'])->name('backups.json')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_VIEW);
        Route::post('/{id}/backups', [CellBackupController::class, 'create'])->name('backups.create')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_CREATE);
        Route::delete('/{id}/backups/{backup}', [CellBackupController::class, 'delete'])->name('backups.delete')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_DELETE);
        Route::post('/{id}/backups/{backup}/restore', [CellBackupController::class, 'restore'])->name('backups.restore')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_RESTORE);
        Route::get('/{id}/backups/{backup}/download', [CellBackupController::class, 'download'])->name('backups.download')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_VIEW);
        Route::get('/{id}/backups/{backup}/lock', [CellBackupController::class, 'lock'])->name('backups.lock')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_VIEW);
        Route::get('/{id}/backups/{backup}/unlock', [CellBackupController::class, 'unlock'])->name('backups.unlock')
            ->middleware('cell.permission:' . CellPermissions::BACKUPS_VIEW);

        Route::get('/{id}/importer', [CellImporterController::class, 'index'])->name('importer.index')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);
        Route::post('/{id}/importer/test', [CellImporterController::class, 'test'])->name('importer.test')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);
        Route::post('/{id}/importer/start', [CellImporterController::class, 'start'])->name('importer.start')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);
        Route::get('/{id}/importer/status', [CellImporterController::class, 'status'])->name('importer.status')
            ->middleware('cell.permission:' . CellPermissions::FILES_UPLOAD);

        Route::get('/{id}/schedule-templates', [CellScheduleController::class, 'templates'])->name('schedule-templates.index')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_VIEW);
        Route::get('/{id}/schedules', [CellScheduleController::class, 'index'])->name('schedules.index')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_VIEW);
        Route::post('/{id}/schedules', [CellScheduleController::class, 'store'])->name('schedules.store')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_CREATE);
        Route::get('/{id}/schedules/{scheduleId}', [CellScheduleController::class, 'show'])->name('schedules.show')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_VIEW);
        Route::put('/{id}/schedules/{scheduleId}', [CellScheduleController::class, 'update'])->name('schedules.update')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_UPDATE);
        Route::delete('/{id}/schedules/{scheduleId}', [CellScheduleController::class, 'destroy'])->name('schedules.destroy')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_DELETE);
        Route::post('/{id}/schedules/{scheduleId}/run', [CellScheduleController::class, 'run'])->name('schedules.run')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_UPDATE);
        Route::get('/{id}/schedules-json', [CellScheduleController::class, 'json'])->name('schedules-json')
            ->middleware('cell.permission:' . CellPermissions::SCHEDULES_VIEW);

        Route::post('/{id}/sftp/reset', [CellSftpCredentialController::class, 'reset'])->name('sftp.reset')
            ->middleware('cell.permission:' . CellPermissions::SFTP_RESET);
        Route::post('/{id}/sftp/revoke', [CellSftpCredentialController::class, 'revoke'])->name('sftp.revoke')
            ->middleware('cell.permission:' . CellPermissions::SFTP_RESET);
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';