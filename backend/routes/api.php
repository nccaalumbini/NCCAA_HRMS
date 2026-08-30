<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CadetController;
use App\Http\Controllers\Api\V1\CadetImportController;
use App\Http\Controllers\Api\V1\EmailController;
use App\Http\Controllers\Api\V1\RecruitmentCandidateController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/reference/roles', [ReferenceController::class, 'roles']);
    Route::get('/reference/ranks', [ReferenceController::class, 'ranks']);
    Route::get('/reference/permissions', [ReferenceController::class, 'permissions']);
    Route::get('/reference/provinces', [ReferenceController::class, 'provinces']);
    Route::get('/reference/districts', [ReferenceController::class, 'districts']);
    Route::get('/reference/local-levels', [ReferenceController::class, 'localLevels']);
    Route::get('/reference/wards', [ReferenceController::class, 'wards']);

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('/users/{user}/disable', [UserController::class, 'disable'])->middleware('permission:users.update');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->middleware('permission:users.update');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset-password');
    Route::put('/users/{user}/roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign-role');
    Route::put('/users/{user}/geography', [UserController::class, 'assignGeography'])->middleware('permission:users.assign-role');

    Route::get('/cadets', [CadetController::class, 'index'])->middleware('permission:cadets.view');
    Route::post('/cadets', [CadetController::class, 'store'])->middleware('permission:cadets.create');
    Route::post('/cadets/import/inspect', [CadetImportController::class, 'inspect'])->middleware('permission:cadets.import');
    Route::post('/cadets/import/preview', [CadetImportController::class, 'preview'])->middleware('permission:cadets.import');
    Route::post('/cadets/import/commit', [CadetImportController::class, 'commit'])->middleware('permission:cadets.import');
    Route::get('/cadets/{cadet}', [CadetController::class, 'show'])->middleware('permission:cadets.view');
    Route::patch('/cadets/{cadet}', [CadetController::class, 'update'])->middleware('permission:cadets.update');
    Route::delete('/cadets/{cadet}', [CadetController::class, 'destroy'])->middleware('permission:cadets.delete');

    Route::get('/recruitment-candidates', [RecruitmentCandidateController::class, 'index'])->middleware('permission:recruitment.view');
    Route::post('/recruitment-candidates/import', [RecruitmentCandidateController::class, 'import'])->middleware('permission:recruitment.import');
    Route::patch('/recruitment-candidates/{candidate}', [RecruitmentCandidateController::class, 'update'])->middleware('permission:recruitment.action');
    Route::post('/recruitment-candidates/{candidate}/promote', [RecruitmentCandidateController::class, 'promote'])->middleware('permission:recruitment.promote-to-cadet');

    Route::get('/email/settings', [EmailController::class, 'getSettings'])->middleware('permission:email.settings.view');
    Route::put('/email/settings', [EmailController::class, 'saveSettings'])->middleware('permission:email.settings.manage');
    Route::post('/email/smtp/test', [EmailController::class, 'testSmtp'])->middleware('permission:email.smtp.test');
    Route::get('/email/recipients', [EmailController::class, 'getRecipients'])->middleware('permission:email.campaigns.create');
    Route::get('/email/campaigns', [EmailController::class, 'indexCampaigns'])->middleware('permission:email.campaigns.view');
    Route::post('/email/campaigns', [EmailController::class, 'createCampaign'])->middleware('permission:email.campaigns.create');
    Route::get('/email/campaigns/{campaign}', [EmailController::class, 'showCampaign'])->middleware('permission:email.campaigns.view');
    Route::get('/email/campaigns/{campaign}/recipients', [EmailController::class, 'recipients'])->middleware('permission:email.delivery.view');
    Route::post('/email/campaigns/{campaign}/send', [EmailController::class, 'sendCampaign'])->middleware('permission:email.campaigns.send');
    Route::post('/email/campaigns/{campaign}/cancel', [EmailController::class, 'cancelCampaign'])->middleware('permission:email.campaigns.cancel');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
});
