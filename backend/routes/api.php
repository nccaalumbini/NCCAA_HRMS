<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CadetController;
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
    Route::post('/users/{user}/disable', [UserController::class, 'disable'])->middleware('permission:users.update');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->middleware('permission:users.update');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.reset-password');
    Route::put('/users/{user}/roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign-role');
    Route::put('/users/{user}/geography', [UserController::class, 'assignGeography'])->middleware('permission:users.assign-role');

    Route::get('/cadets', [CadetController::class, 'index'])->middleware('permission:cadets.view');
    Route::post('/cadets', [CadetController::class, 'store'])->middleware('permission:cadets.create');
    Route::get('/cadets/{cadet}', [CadetController::class, 'show'])->middleware('permission:cadets.view');
    Route::patch('/cadets/{cadet}', [CadetController::class, 'update'])->middleware('permission:cadets.update');
    Route::delete('/cadets/{cadet}', [CadetController::class, 'destroy'])->middleware('permission:cadets.delete');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
});
