<?php

use App\Http\Controllers\DohLdiReportController;
use App\Http\Controllers\PersonalDataSheetController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('trainings/mine', 'pages::trainings.mine')->name('trainings.mine');

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
