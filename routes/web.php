<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('trainings/mine', 'pages::trainings.mine')->name('trainings.mine');
    Route::livewire('trainings/{record}', 'pages::trainings.show')->name('trainings.show');

    Route::livewire('approvals', 'pages::approvals')->name('approvals');
});

require __DIR__.'/settings.php';
