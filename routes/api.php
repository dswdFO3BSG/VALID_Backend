<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Settings\UserAccessController;
use App\Http\Controllers\ClientVerification\ClientVerificationController;
use App\Http\Controllers\ClientVerification\uploadPhotoController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Settings\QueueManagerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::controller(AuthenticationController::class)->group(function () {
    Route::post('login', 'login');
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


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('users')->controller(UserAccessController::class)->group(function () {
        Route::get('user-access', 'getUserAccess');
        Route::get('user-modules', 'getUserModules');
        Route::get('users', 'getUsers');
        Route::get('user-current-modules', 'getUserCurrentModules');
        Route::get('user-access-path', 'checkUserAccessPath');
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

});
