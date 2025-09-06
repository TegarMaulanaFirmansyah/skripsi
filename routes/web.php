<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreprocessingController;
use App\Http\Controllers\LabellingController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\EvaluationController;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/preprocessing', [PreprocessingController::class, 'index'])->name('preprocessing.index');
Route::post('/preprocessing/upload', [PreprocessingController::class, 'upload'])->name('preprocessing.upload');
Route::post('/preprocessing/run', [PreprocessingController::class, 'run'])->name('preprocessing.run');
Route::get('/preprocessing/download', [PreprocessingController::class, 'download'])->name('preprocessing.download');

Route::get('/labelling', [LabellingController::class, 'index'])->name('labelling.index');
Route::get('/labelling/page', [LabellingController::class, 'getPage'])->name('labelling.page');
Route::post('/labelling/upload', [LabellingController::class, 'upload'])->name('labelling.upload');
Route::post('/labelling/run', [LabellingController::class, 'run'])->name('labelling.run');
Route::post('/labelling/update', [LabellingController::class, 'updateLabel'])->name('labelling.update');
Route::post('/labelling/bulk-update', [LabellingController::class, 'bulkUpdate'])->name('labelling.bulk-update');
Route::get('/labelling/download', [LabellingController::class, 'download'])->name('labelling.download');
Route::get('/labelling/cleanup', [LabellingController::class, 'cleanup'])->name('labelling.cleanup');

Route::get('/klasifikasi', [ClassificationController::class, 'index'])->name('classification.index');
Route::post('/klasifikasi/upload/training', [ClassificationController::class, 'uploadTraining'])->name('classification.upload.training');
Route::post('/klasifikasi/upload/testing', [ClassificationController::class, 'uploadTesting'])->name('classification.upload.testing');
Route::post('/klasifikasi/run', [ClassificationController::class, 'runClassification'])->name('classification.run');
Route::get('/klasifikasi/download', [ClassificationController::class, 'downloadResults'])->name('classification.download');
Route::get('/klasifikasi/cleanup', [ClassificationController::class, 'cleanup'])->name('classification.cleanup');

Route::get('/evaluasi', [EvaluationController::class, 'index'])->name('evaluation.index');
Route::post('/evaluasi/upload', [EvaluationController::class, 'uploadResults'])->name('evaluation.upload');
Route::post('/evaluasi/compare', [EvaluationController::class, 'compareMethods'])->name('evaluation.compare');
Route::post('/evaluasi/confusion-matrix', [EvaluationController::class, 'generateConfusionMatrix'])->name('evaluation.confusion-matrix');
Route::get('/evaluasi/download', [EvaluationController::class, 'downloadReport'])->name('evaluation.download');
Route::get('/evaluasi/cleanup', [EvaluationController::class, 'cleanup'])->name('evaluation.cleanup');
