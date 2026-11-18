<?php

use App\Http\Controllers\AcademicContextController;
use App\Http\Controllers\AcademicSetupController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdmitVerificationController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecoveryMailController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RoleAccountController;
use App\Http\Controllers\RoomLayoutController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SubmissionDeadlineController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sign-in')->name('home');
Route::view('/about', 'landing')->name('landing');
Route::get('/ui-palette.css', [AppearanceController::class, 'paletteCss'])->name('ui.palette');
Route::get('/verify/admit/{reference}', [AdmitVerificationController::class, 'show'])
    ->whereUuid('reference')->middleware(['signed', 'throttle:60,1'])->name('admit.verify');
Route::get('/sign-in', [AuthController::class, 'form'])->name('login');
Route::get('/login', fn () => redirect('/sign-in'))->name('sign-in');
Route::middleware('guest')->group(function () {
    Route::post('/sign-in', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/forgot-password', fn () => app(AuthController::class)->form('forgot'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:reset')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});
Route::view('/styleguide', 'styleguide')->middleware(['auth', 'active', 'role:admin'])->name('styleguide');

// Every route in this group requires an active, signed-in account.
Route::middleware(['auth', 'active', 'recovery.session', 'auth.session'])->group(function () {
    Route::get('/workspace', fn () => redirect('/'.auth()->user()->role.'/'.auth()->user()->role.'-dashboard'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    // Administrator tools: account setup, examinations, approvals and settings.
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/appearance', [AppearanceController::class, 'index'])->name('admin.appearance');
        Route::post('/admin/appearance', [AppearanceController::class, 'save'])->name('admin.appearance.save');
        Route::post('/admin/settings/email', [RecoveryMailController::class, 'save']);
        Route::post('/admin/settings/email/test', [RecoveryMailController::class, 'test'])->middleware('throttle:5,1');
        Route::post('/admin/settings/email/send-test', [RecoveryMailController::class, 'sendTest'])->middleware('throttle:3,1');
        Route::get('/admin/{directory}', [RoleAccountController::class, 'index'])->where('directory', 'teachers|students')->name('role-accounts.index');
        Route::get('/admin/{directory}/create', [RoleAccountController::class, 'create'])->where('directory', 'teachers|students')->name('role-accounts.create');
        Route::get('/admin/{directory}/{id}/edit', [RoleAccountController::class, 'edit'])->where('directory', 'teachers|students')->whereNumber('id')->name('role-accounts.edit');
        Route::post('/admin/{directory}', [RoleAccountController::class, 'save'])->where('directory', 'teachers|students');
        Route::put('/admin/{directory}/{id}', [RoleAccountController::class, 'save'])->where('directory', 'teachers|students')->whereNumber('id');
        Route::post('/admin/{directory}/{id}/password', [RoleAccountController::class, 'password'])->where('directory', 'teachers|students')->whereNumber('id')->middleware('throttle:20,1');
        Route::get('/admin/people', [RoleAccountController::class, 'legacy']);
        Route::get('/admin/academic-setup', [AcademicSetupController::class, 'show'])->name('academic-setup');
        Route::post('/admin/academic-setup/{action}', [AcademicSetupController::class, 'save'])->name('academic-setup.save');
        Route::post('/academic-context', [AcademicContextController::class, 'update'])->name('academic-context.update');
        Route::post('/workflow/marks/{course}/deadline', [SubmissionDeadlineController::class, 'update'])->name('marks.deadline.update');
        Route::post('/manage/{resource}/{id?}', [ResourceController::class, 'save'])->whereNumber('id')->name('resource.save');
        Route::post('/workflow/schedule/{course}', [WorkflowController::class, 'schedule']);
        Route::post('/workflow/examination/{exam}/generate', [WorkflowController::class, 'generateSchedule']);
        Route::post('/workflow/examination/{exam}/state', [WorkflowController::class, 'examinationState']);
        Route::post('/workflow/examination/{exam}/revision', [WorkflowController::class, 'revision']);
        Route::post('/workflow/seating/{entry}', [WorkflowController::class, 'seating']);
        Route::post('/workflow/duty/{entry}', [WorkflowController::class, 'duty']);
        Route::post('/workflow/replacement/{replacement}/nominate', [WorkflowController::class, 'nominate']);
        Route::post('/workflow/duty-request/{request}/reject', [WorkflowController::class, 'rejectDuty']);
        Route::post('/workflow/backup/restore', [WorkflowController::class, 'restoreBackup']);
        Route::post('/workflow/backup', [WorkflowController::class, 'backup']);
        Route::get('/download/backup/{backup}', [WorkflowController::class, 'downloadBackup']);
        Route::post('/workflow/replacement/{replacement}/approve', [WorkflowController::class, 'replacement']);
        Route::post('/workflow/marks/{course}/return', [WorkflowController::class, 'returnMarks']);
        Route::post('/workflow/results/{exam}/calculate', [WorkflowController::class, 'calculate']);
        Route::post('/workflow/batch/{batch}/state', [WorkflowController::class, 'resultState']);
        Route::post('/workflow/correction/{mark}', [WorkflowController::class, 'correction']);
        Route::post('/workflow/correction/{correction}/approve', [WorkflowController::class, 'approveCorrection']);
        Route::post('/workflow/recheck/{recheck}/decide', [WorkflowController::class, 'decideRecheck']);
        Route::post('/workflow/analysis/{exam}', [WorkflowController::class, 'analysis']);
        Route::post('/workflow/flag/{flag}/review', [WorkflowController::class, 'reviewFlag']);
        Route::post('/workflow/admit/{exam}', [WorkflowController::class, 'admit']);
        Route::post('/admin/rooms/{room}/layout', [RoomLayoutController::class, 'update']);
        Route::post('/admin/settings/notifications', [AdminSettingsController::class, 'notifications']);
        Route::post('/admin/settings/grading-rules', [AdminSettingsController::class, 'gradingRules']);
        Route::post('/admin/settings/accounts/{user}', [AdminSettingsController::class, 'account']);
        Route::post('/admin/ai-settings/model', [AiController::class, 'saveModel']);
        Route::post('/admin/ai-settings/test', [AiController::class, 'test'])->middleware('throttle:10,1');
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('/admin/ai/runs/{run}/revise', [AiController::class, 'revise']);
            Route::post('/admin/ai/command', [AiController::class, 'command']);
            Route::post('/admin/ai/draft', [AiController::class, 'draft']);
            Route::post('/admin/ai/report', [AiController::class, 'report']);
            Route::post('/admin/ai/triage', [AiController::class, 'triage']);
            Route::post('/admin/ai/replacement/{replacement}', [AiController::class, 'replacement']);
        });
        Route::post('/admin/ai/send', [AiController::class, 'send']);
        Route::get('/admin/ai/quick/{key}', [AiController::class, 'quick']);
        Route::post('/workflow/seat-map/{entry}/swap', [AiController::class, 'seatSwap']);
    });
    Route::post('/workflow/duty/{duty}/request', [WorkflowController::class, 'dutyRequest'])->middleware('role:teacher');
    Route::post('/workflow/forecast', [WorkflowController::class, 'forecast']);
    Route::post('/workflow/availability', [WorkflowController::class, 'availability'])->middleware('role:admin,teacher');
    Route::post('/workflow/marks/{course}', [WorkflowController::class, 'marks'])->middleware('role:admin,teacher');
    Route::post('/workflow/import', [WorkflowController::class, 'import'])->middleware('role:admin,teacher');
    Route::middleware('role:admin,teacher')->group(function () {
        Route::post('/ai/start', [AiController::class, 'start'])->middleware('throttle:20,1');
        Route::get('/ai/runs/{run}/status', [AiController::class, 'status']);
        Route::post('/ai/runs/{run}/approve', [AiController::class, 'approve']);
    });
    Route::post('/workflow/recheck/{result}', [WorkflowController::class, 'recheck'])->middleware('role:student');
    Route::post('/workflow/assistant', [WorkflowController::class, 'assistant'])->middleware('throttle:assistant');
    Route::post('/workflow/notifications/read-all', [WorkflowController::class, 'readAllNotifications']);
    Route::post('/workflow/notifications/{id}/read', [WorkflowController::class, 'readNotification']);
    Route::get('/seat-map/{entry}/room/{room}/print', [AiController::class, 'seatPrint']);
    Route::get('/download/admit/{card}', [WorkflowController::class, 'downloadAdmit']);
    Route::get('/reports/{type}', [WorkflowController::class, 'report']);
    // Keep this general screen route last so it does not catch specific URLs above.
    Route::get('/{role}/{screen?}', [ScreenController::class, 'show'])->where('role', 'admin|teacher|student')->name('screen');
});
