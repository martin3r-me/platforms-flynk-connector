<?php

use Illuminate\Support\Facades\Route;
use Platform\FlynkConnector\Livewire\Container\Index as ContainerIndex;
use Platform\FlynkConnector\Livewire\Container\Show as ContainerShow;
use Platform\FlynkConnector\Livewire\Questions\Index as QuestionsIndex;

// Module root → redirect to container list
Route::get('/', fn () => redirect()->route('flynk-connector.containers.index'))->name('flynk-connector.dashboard');

// Container
Route::get('/containers', ContainerIndex::class)->name('flynk-connector.containers.index');
Route::get('/containers/{container}', ContainerShow::class)->name('flynk-connector.containers.show');

// Rückfragen (inbound, Kanal 2)
Route::get('/questions', QuestionsIndex::class)->name('flynk-connector.questions.index');
