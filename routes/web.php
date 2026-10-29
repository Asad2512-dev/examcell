<?php

use App\Http\Controllers\AiController as Ai;
use App\Http\Controllers\AuthController as A;
use App\Http\Controllers\ResourceController as R;
use App\Http\Controllers\ScreenController as S;
use App\Http\Controllers\WorkflowController as W;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::get('/verify/admit/{reference}', [\App\Http\Controllers\AdmitVerificationController::class, 'show'])
    ->whereUuid('reference')->middleware(['signed', 'throttle:60,1'])->name('admit.verify');
Route::get('/sign-in', [A::class, 'form'])->name('login');
Route::get('/login', fn () => redirect('/sign-in'))->name('sign-in');
Route::middleware('guest')->group(function () {
    Route::post('/sign-in', [A::class, 'login'])->middleware('throttle:login');
    Route::get('/forgot-password', fn () => app(A::class)->form('forgot'))->name('password.request');
    Route::post('/forgot-password', [A::class, 'forgot'])->middleware('throttle:reset')->name('password.email');
    Route::get('/reset-password/{token}', [A::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [A::class, 'reset'])->middleware('throttle:reset')->name('password.update');
});
Route::view('/styleguide', 'styleguide')->middleware(['auth', 'active', 'role:admin'])->name('styleguide');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/workspace', fn () => redirect('/'.auth()->user()->role.'/'.auth()->user()->role.'-dashboard'));
    Route::post('/logout', [A::class, 'logout'])->name('logout');
    Route::middleware('role:admin')->group(function () {
        Route::post('/academic-context', [\App\Http\Controllers\AcademicContextController::class, 'update'])->name('academic-context.update');
        Route::post('/workflow/marks/{course}/deadline', [\App\Http\Controllers\SubmissionDeadlineController::class, 'update'])->name('marks.deadline.update');
        Route::post('/manage/{resource}/{id?}', [R::class, 'save'])->whereNumber('id')->name('resource.save');
        Route::post('/workflow/schedule/{course}', [W::class, 'schedule']);
        Route::post('/workflow/examination/{exam}/generate', [W::class, 'generateSchedule']);
        Route::post('/workflow/examination/{exam}/state', [W::class, 'examinationState']);
        Route::post('/workflow/examination/{exam}/revision', [W::class, 'revision']);
        Route::post('/workflow/seating/{entry}', [W::class, 'seating']);
        Route::post('/workflow/duty/{entry}', [W::class, 'duty']);
        Route::post('/workflow/replacement/{replacement}/nominate', [W::class, 'nominate']);
        Route::post('/workflow/duty-request/{request}/reject', [W::class, 'rejectDuty']);
        Route::post('/workflow/backup/restore', [W::class, 'restoreBackup']);
        Route::post('/workflow/backup', [W::class, 'backup']);
        Route::get('/download/backup/{backup}', [W::class, 'downloadBackup']);
        Route::post('/workflow/replacement/{replacement}/approve', [W::class, 'replacement']);
        Route::post('/workflow/marks/{course}/return', [W::class, 'returnMarks']);
        Route::post('/workflow/results/{exam}/calculate', [W::class, 'calculate']);
        Route::post('/workflow/batch/{batch}/state', [W::class, 'resultState']);
        Route::post('/workflow/correction/{mark}', [W::class, 'correction']);
        Route::post('/workflow/correction/{correction}/approve', [W::class, 'approveCorrection']);
        Route::post('/workflow/recheck/{recheck}/decide', [W::class, 'decideRecheck']);
        Route::post('/workflow/analysis/{exam}', [W::class, 'analysis']);
        Route::post('/workflow/flag/{flag}/review', [W::class, 'reviewFlag']);
        Route::post('/workflow/admit/{exam}', [W::class, 'admit']);
        Route::post('/admin/rooms/{room}/layout', [\App\Http\Controllers\RoomLayoutController::class, 'update']);
        Route::post('/admin/settings/notifications', [\App\Http\Controllers\AdminSettingsController::class, 'notifications']);
        Route::post('/admin/settings/grading-rules', [\App\Http\Controllers\AdminSettingsController::class, 'gradingRules']);
        Route::post('/admin/settings/accounts/{user}', [\App\Http\Controllers\AdminSettingsController::class, 'account']);
        Route::post('/admin/ai-settings/model', [Ai::class, 'saveModel']);
        Route::post('/admin/ai-settings/test', [Ai::class, 'test'])->middleware('throttle:10,1');
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('/admin/ai/runs/{run}/revise', [Ai::class, 'revise']);
            Route::post('/admin/ai/command', [Ai::class, 'command']);
            Route::post('/admin/ai/draft', [Ai::class, 'draft']);
            Route::post('/admin/ai/report', [Ai::class, 'report']);
            Route::post('/admin/ai/triage', [Ai::class, 'triage']);
            Route::post('/admin/ai/replacement/{replacement}', [Ai::class, 'replacement']);
        });
        Route::post('/admin/ai/send', [Ai::class, 'send']);
        Route::get('/admin/ai/quick/{key}', [Ai::class, 'quick']);
        Route::post('/workflow/seat-map/{entry}/swap', [Ai::class, 'seatSwap']);
    });
    Route::post('/workflow/duty/{duty}/request', [W::class, 'dutyRequest'])->middleware('role:teacher');
    Route::post('/workflow/forecast', [W::class, 'forecast']);
    Route::post('/workflow/availability', [W::class, 'availability'])->middleware('role:admin,teacher');
    Route::post('/workflow/marks/{course}', [W::class, 'marks'])->middleware('role:admin,teacher');
    Route::post('/workflow/import', [W::class, 'import'])->middleware('role:admin,teacher');
    Route::middleware('role:admin,teacher')->group(function () {
        Route::post('/ai/start', [Ai::class, 'start'])->middleware('throttle:20,1');
        Route::get('/ai/runs/{run}/status', [Ai::class, 'status']);
        Route::post('/ai/runs/{run}/approve', [Ai::class, 'approve']);
    });
    Route::post('/workflow/recheck/{result}', [W::class, 'recheck'])->middleware('role:student');
    Route::post('/workflow/assistant', [W::class, 'assistant'])->middleware('throttle:assistant');
    Route::post('/workflow/notifications/{id}/read', [W::class, 'readNotification']);
    Route::get('/seat-map/{entry}/room/{room}/print', [Ai::class, 'seatPrint']);
    Route::get('/download/admit/{card}', [W::class, 'downloadAdmit']);
    Route::get('/reports/{type}', [W::class, 'report']);
    Route::get('/{role}/{screen?}', [S::class, 'show'])->where('role', 'admin|teacher|student')->name('screen');
});
