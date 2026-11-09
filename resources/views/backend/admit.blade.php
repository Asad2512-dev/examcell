
@if($role==='student')<div class="role-flow-note"><strong>{{ ui_text('examinations.copy.download_your_admit_card_before_exam_day') }}</strong><p>{{ ui_text('examinations.copy.open_the_pdf_below_and_keep_a_printed_or_saved_copy_your') }}</p><div class="page-actions"><a class="card-link" href="/student/exams">{{ ui_text('examinations.copy.check_exam_dates') }}</a><a class="card-link" href="/student/exams?tab=seat">{{ ui_text('examinations.copy.find_my_seat_94c975') }}</a></div></div>@endif
@if($role==='admin')<x-card class=""><h2>{{ ui_text('examinations.copy.generate_private_admit_cards') }}</h2><form method="POST" data-route-form="/workflow/admit/" class="form-grid" data-wizard-form>
@csrf<x-form-field ><span>{{ ui_text('examinations.copy.published_examination') }}</span><select data-route-id required>
@foreach($exams->where('status','published') as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</select></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.student_for_individual_generation') }}</span><select name="student_id"><option value="">{{ ui_text('examinations.copy.select_student_or_bulk_generation') }}</option>
@foreach($admitStudents as $s)<option value="{{ $s->user_id }}">{{ $s->roll_number }} · {{ $s->user->name }}</option>
@endforeach</select></x-form-field><label><input type="checkbox" name="bulk" value="1"> {{ ui_text('examinations.copy.generate_for_all_eligible_enrolled_students') }}</label><x-button variant="primary" class="" type="submit">{{ ui_text('examinations.copy.generate_pdfs') }}</x-button></form></x-card>
@endif
@if($role==='admin')<x-card class=""><div class="card-heading"><h2>{{ ui_text('examinations.copy.bulk_generation_progress') }}</h2><x-button variant="secondary" data-refresh icon="history">{{ ui_text('examinations.copy.refresh_status') }}</x-button></div>
@forelse(app(\App\Services\AcademicContext::class)->apply(\App\Models\AdmitGenerationRun::query())->latest()->limit(20)->get() as $run)<div class="card-body"><p class="muted text-xs mb-3">Run #{{ $run->id }} · {{ $run->created_at->format('d M Y H:i') }}</p><x-job-progress :status="$run->status" :count="$run->generated" :failure="$run->failure"/></div>
@empty<p>{{ ui_text('examinations.copy.no_queued_generation_runs') }}</p>
@endforelse</x-card>
@endif<x-card class=""><h2>{{ ui_text('examinations.copy.available_admit_cards') }}</h2>
@forelse($records as $card)<div class="notification-item"><div><strong>{{ $card->examination->name }} · {{ $card->student->name }}</strong><p>Reference {{ $card->reference }} · {{ $card->created_at }}</p></div><a class="btn btn-secondary" href="/download/admit/{{ $card->id }}">{{ ui_text('examinations.copy.download_pdf') }}</a></div>
@empty<x-empty-state title="{{ ui_text('examinations.copy.your_admit_card_is_not_available_yet') }}" description="Check back after the examination cell generates it. If your exam is approaching, contact the examination cell." icon="file"/>
@endforelse @include('backend.pagination')</x-card>
