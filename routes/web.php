<?php

use App\Http\Controllers\DohLdiReportController;
use App\Http\Controllers\PersonalDataSheetController;
use Illuminate\Support\Facades\Route;

// Nobody wants a landing page for an office system: send them to the
// work if they are signed in, and to the door if they are not.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('calendar', 'pages::calendar')->name('calendar');

    Route::livewire('trainings/mine', 'pages::trainings.mine')->name('trainings.mine');

    Route::livewire('my-profile', 'pages::my-profile')->name('my-profile');

    Route::livewire('my-pds', 'pages::my-pds')->name('my-pds');
    Route::get('my-pds.xlsx', [PersonalDataSheetController::class, 'mine'])->name('my-pds.download');

    Route::livewire('approvals', 'pages::approvals')->name('approvals');

    Route::livewire('reports', 'pages::reports')->name('reports');
    Route::get('reports/doh-ldi', DohLdiReportController::class)->name('reports.doh-ldi');

    Route::livewire('ldi', 'pages::ldi.index')->name('ldi.index');
    Route::livewire('ldi/{plan}', 'pages::ldi.show')->name('ldi.show');

    Route::livewire('employees', 'pages::employees.index')->name('employees.index');
    Route::livewire('employees/{employee}', 'pages::employees.show')->name('employees.show');
    Route::get('employees/{employee}/pds.xlsx', [PersonalDataSheetController::class, 'show'])->name('employees.pds');

    Route::livewire('setup/divisions', 'pages::setup.divisions')->name('setup.divisions');
    Route::livewire('setup/sections', 'pages::setup.sections')->name('setup.sections');
    Route::livewire('setup/positions', 'pages::setup.positions')->name('setup.positions');
    Route::livewire('setup/budget-caps', 'pages::setup.budget-caps')->name('setup.budget-caps');
    Route::livewire('setup/users', 'pages::setup.users')->name('setup.users');
});

require __DIR__.'/settings.php';
