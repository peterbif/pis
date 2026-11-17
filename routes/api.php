<?php

use App\Http\Controllers\{AdminController, AuthController, ChatController, PersonnelController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/lookups', [AdminController::class, 'lookups']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::prefix('signup')->group(function () {
    Route::post('/check-email', [AuthController::class, 'checkEmail'])->middleware('throttle:10,1');
    Route::post('/', [AuthController::class, 'signup'])->middleware('throttle:5,1');
    Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user()->only('id', 'name', 'email', 'role', 'permissions'));
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/chat', [ChatController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/chat/messages', [ChatController::class, 'send'])->middleware('throttle:20,1');
    Route::get('/personnel/profile', [PersonnelController::class, 'show']);
    Route::put('/personnel/profile', [PersonnelController::class, 'update']);
    Route::delete('/personnel/profile/sections/{section}', [PersonnelController::class, 'clearSection']);
    Route::post('/lookups', [PersonnelController::class, 'addLookupOption'])->middleware('throttle:30,60');
    Route::middleware('role:admin,superadmin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view_dashboard');
        Route::get('/personnel/export', [AdminController::class, 'export'])->middleware('permission:export_personnel');
        Route::get('/personnel', [AdminController::class, 'directory'])->middleware('permission:view_personnel');
        Route::get('/personnel/{profile}', [AdminController::class, 'showPersonnel'])->middleware('permission:view_personnel');
        Route::patch('/personnel/{profile}/status', [AdminController::class, 'updatePersonnelStatus'])->middleware('permission:review_personnel');
        Route::post('/invitations', [AdminController::class, 'invite'])->middleware('permission:manage_invitations');
        Route::post('/import', [AdminController::class, 'import'])->middleware('permission:manage_invitations');
        Route::get('/lookups', [AdminController::class, 'allLookups'])->middleware('permission:manage_lookups');
        Route::post('/lookups', [AdminController::class, 'addOption'])->middleware('permission:manage_lookups');
        Route::post('/lookups/import', [AdminController::class, 'importLookups'])->middleware('permission:manage_lookups');
        Route::put('/lookups/{lookup}', [AdminController::class, 'updateOption'])->middleware('permission:manage_lookups');
        Route::delete('/lookups/{lookup}', [AdminController::class, 'deleteOption'])->middleware('permission:manage_lookups');
        Route::middleware('role:superadmin')->group(function () {
            Route::get('/users', [AdminController::class, 'administrators']);
            Route::post('/users', [AdminController::class, 'createAdmin']);
            Route::patch('/users/{user}/permissions', [AdminController::class, 'updateAdminPermissions']);
        });
    });
});
