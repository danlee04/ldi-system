<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('trainings/mine', 'pages::trainings.mine')->name('trainings.mine');
    Route::livewire('trainings/{record}', 'pages::trainings.show')->name('trainings.show');

    Route::livewire('approvals', 'pages::approvals')->name('approvals');

    Route::livewire('employees', 'pages::employees.index')->name('employees.index');
    Route::livewire('employees/{employee}', 'pages::employees.show')->name('employees.show');
});

require __DIR__.'/settings.php';
