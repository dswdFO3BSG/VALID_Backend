<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\MFAController;
use App\Http\Controllers\Settings\UserAccessController;
use App\Http\Controllers\ClientVerification\ClientVerificationController;
use App\Http\Controllers\ClientVerification\uploadPhotoController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Settings\QueueManagerController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\UserModulesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(AuthenticationController::class)->group(function () {
    Route::post('login', 'login');
});

// MFA Routes
Route::prefix('mfa')->controller(MFAController::class)->group(function () {
    Route::post('setup', 'setupMFA');
    Route::post('verify-setup', 'verifyAndEnableMFA');
    Route::post('verify-login', 'verifyMFAForLogin');
    Route::post('check-remember', 'checkMFARememberToken');
    Route::post('disable', 'disableMFA');
    Route::get('status', 'getMFAStatus');
});

    Route::prefix('queue')->controller(QueueManagerController::class)->group(function () {
        Route::get('sectors', 'getSectors');
        Route::get('programs', 'getPrograms');
        Route::get('queues', 'getQueues');
        Route::get('queueNumber', 'getQueueNumber');
    });

    Route::prefix('clients')->controller(ClientVerificationController::class)->group(function () {
        Route::get('clients', 'getClients');
        Route::post('clients', 'saveClients');
        Route::patch('clients', 'updateClients');
    });


Route::middleware(['auth:sanctum', 'audit.trail'])->group(function () {
    
    Route::prefix('auth')->controller(AuthenticationController::class)->group(function () {
        Route::post('logout', 'logout');
    });

    Route::prefix('users')->controller(UserAccessController::class)->group(function () {
     
        Route::post('user-access', 'saveAccess');
    });

    Route::prefix('clients')->controller(ClientVerificationController::class)->group(function () {
        Route::get('statistics', 'getTotalCount');
        Route::get('age_group', 'getCountAge');
        Route::get('municipality', 'getCountMunicipality');
    });

    Route::prefix('queue')->controller(QueueManagerController::class)->group(function () {  
        Route::post('queues', 'createQueue');
        Route::patch('queues/{id}', 'updateQueue');
    });

    Route::prefix('reports')->controller(ReportsController::class)->group(function () {
        Route::get('clients', 'generateReports');
    });


    Route::prefix('bene_id')->controller(uploadPhotoController::class)->group(function () {
        Route::post('upload-photo', 'uploadPhoto');
    });

    // Audit Trail Routes
    Route::prefix('audit-trail')->controller(AuditTrailController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/statistics', 'statistics');
        Route::get('/export', 'export');
        Route::get('/{id}', 'show');
    });

});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('users')->controller(UserAccessController::class)->group(function () {
           Route::get('user-access', 'getUserAccess');
        Route::get('user-modules', 'getUserModules');
        Route::get('users', 'getUsers');
        Route::get('user-current-modules', 'getUserCurrentModules');
        Route::get('user-access-path', 'checkUserAccessPath');
    });

    // User Modules Management Routes
    Route::apiResource('user-modules', UserModulesController::class);
    });
