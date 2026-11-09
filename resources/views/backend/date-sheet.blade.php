@php
$dsCourses=$ehExam->courses->sortBy(fn($c)=>$c->schedule?->starts_at?->timestamp??PHP_INT_MAX);
$dsHasPapers=$dsCourses->isNotEmpty();
$dsSession=\App\Models\AcademicSession::find($ehExam->academic_session_id);
$dsFirstDate=min($dsSession->ends_on->toDateString(),max(now()->toDateString(),$dsSession->starts_on->toDateString()));
$dsReady=$dsHasPapers&&$ehUnscheduled===0&&count($ehClashes)===0;
@endphp
@if(!$dsHasPapers)
<div class="callout callout-warning"><strong>{{ ui_text('examinations.copy.add_papers_before_creating_a_date_sheet') }}</strong><p>{{ ui_text('examinations.copy.choose_the_courses_and_sections_taking_this_examination') }}</p><a class="btn btn-primary" href="/admin/records?resource=exam-courses">{{ ui_text('examinations.copy.add_examination_papers') }}</a></div>
@elseif($ehExam->status==='draft'&&$ehUnscheduled>0)
<x-card title="{{ ui_text('examinations.copy.1_create_your_timetable') }}" description="Choose the first exam date. The system finds available slots and rooms for unscheduled papers.">
<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/generate" class="form-grid card-body">
@csrf
<x-form-field label="First exam date" :hint="$dsSession->name.' · '.$dsSession->starts_on->format('d M Y').' – '.$dsSession->ends_on->format('d M Y')"><input type="date" name="starts_on" required min="{{ $dsSession->starts_on->toDateString() }}" max="{{ $dsSession->ends_on->toDateString() }}" value="{{ old('starts_on',$dsFirstDate) }}"></x-form-field>
<x-form-field label="Days available for exams" hint="Search within this many days from the first exam date."><input type="number" name="days" min="1" max="90" required value="{{ old('days',14) }}"></x-form-field>
<div class="form-actions"><x-button type="submit" icon="calendar">{{ ui_text('examinations.copy.create_date_sheet') }}</x-button></div>
<p class="muted">{{ ui_text('examinations.copy.existing_scheduled_papers_stay_as_they_are_new_entries_a') }}</p>
</form></x-card>
@endif
@if($dsHasPapers)
<x-card class="datesheet-sheet" :title="'Date sheet · '.$ehExam->name" :description="$ehUnscheduled?'Some papers still need a date and time.':'Review the dates, times and rooms below.'">
<x-slot:actions><x-button variant="secondary" data-print icon="print" class="no-print">{{ ui_text('examinations.copy.print_save_pdf') }}</x-button></x-slot:actions>
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('examinations.copy.date') }}</th><th>{{ ui_text('examinations.copy.time') }}</th><th>{{ ui_text('examinations.copy.course') }}</th><th>{{ ui_text('examinations.copy.section') }}</th><th>{{ ui_text('examinations.copy.room') }}</th></tr></thead><tbody>
@foreach($dsCourses as $paper)<tr><td>{{ $paper->schedule?->starts_at?->format('D, d M Y')??'Not scheduled' }}</td><td>{{ $paper->schedule?$paper->schedule->starts_at->format('H:i').' – '.$paper->schedule->ends_at->format('H:i'):'—' }}</td><td>{{ $paper->course->code }} · {{ $paper->course->name }}</td><td>{{ $paper->section?->name??'—' }}</td><td>{{ $paper->schedule?->room_label??'—' }}</td></tr>@endforeach
</tbody></table></x-data-table>
<p class="card-body">Status: {{ ucfirst($ehExam->status) }}{{ $ehExam->status==='draft'?' · For review; not yet published':'' }}</p>
</x-card>
@if($ehEvents)<x-disclosure title="{{ ui_text('examinations.copy.calendar_view') }}" description="See the same papers on a calendar."><x-calendar :events="$ehEvents" title="{{ ui_text('examinations.copy.date_sheet_calendar') }}"/></x-disclosure>@endif
@if($ehClashes)<div class="callout callout-danger" role="alert"><strong>{{ ui_text('examinations.copy.resolve_these_clashes_before_approval') }}</strong><ul>@foreach($ehClashes as $cl)<li>{{ $cl }}</li>@endforeach</ul></div>@endif
<x-card title="{{ ui_text('examinations.copy.2_review_3_approve_and_publish') }}" description="Check every paper above before continuing.">
<div class="card-body">
@if($ehExam->status==='draft')
<p>{{ ui_text('examinations.copy.approval_checks_the_schedule_grading_scheme_and_assessme') }}</p>
<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state" class="page-actions">@csrf<input type="hidden" name="status" value="approved"><x-button type="submit" icon="check" :disabled="!$dsReady" data-confirm="true" data-confirm-title="{{ ui_text('examinations.copy.approve_this_date_sheet') }}" data-confirm-message="{{ ui_text('examinations.copy.the_server_will_check_the_complete_schedule_and_academic') }}" data-confirm-label="{{ ui_text('examinations.copy.approve_date_sheet') }}">{{ ui_text('examinations.copy.approve_date_sheet') }}</x-button></form>
@if(!$dsReady)<p class="muted">{{ ui_text('examinations.copy.schedule_every_paper_and_resolve_clashes_to_enable_appro') }}</p>@endif
@elseif($ehExam->status==='approved')
<p>{{ ui_text('examinations.copy.approved_prepare_seating_and_invigilation_before_publish') }}</p>
<div class="page-actions"><a class="btn btn-secondary" href="/admin/seating">{{ ui_text('examinations.copy.prepare_seating') }}</a><a class="btn btn-secondary" href="/admin/invigilation">{{ ui_text('examinations.copy.assign_invigilators') }}</a><form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="published"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="{{ ui_text('examinations.copy.publish_this_date_sheet') }}" data-confirm-message="{{ ui_text('examinations.copy.the_server_checks_publication_requirements_then_notifies') }}" data-confirm-label="{{ ui_text('examinations.copy.publish') }}">{{ ui_text('examinations.copy.publish_date_sheet') }}</x-button></form></div>
@elseif($ehExam->status==='published')
<p class="callout callout-success">{{ ui_text('examinations.copy.published_teachers_and_students_can_find_it_in_their_exa') }}</p>
<a class="card-link" href="/admin/exams?exam={{ $ehExam->id }}&amp;tab=overview">{{ ui_text('examinations.copy.need_a_change_open_overview_to_create_a_revision') }}</a>
@else
<p class="muted">{{ ui_text('examinations.copy.this_examination_is_archived_you_can_view_or_print_its_d') }}</p>
@endif
</div></x-card>
@if($ehExam->status==='draft')
<x-disclosure title="{{ ui_text('examinations.copy.optional_generate_with_ai') }}" description="Use this when you want to describe extra scheduling rules.">
<form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="datesheet"><input type="hidden" name="exam_id" value="{{ $ehExam->id }}">
<x-form-field label="Your scheduling rules (optional)"><textarea name="constraints" rows="3" maxlength="1500" placeholder="{{ ui_text('examinations.copy.for_example_no_friday_afternoon_papers') }}"></textarea></x-form-field>
<x-button type="submit" icon="sparkles" :disabled="!$ehConfigured">{{ ui_text('examinations.copy.generate_date_sheet_with_ai') }}</x-button>
<p class="muted">{{ ui_text('examinations.copy.ai_proposes_the_application_validates_you_review_and_app') }}</p>
@unless($ehConfigured)<a class="card-link" href="/admin/settings?tab=ai">{{ ui_text('examinations.copy.configure_ai_settings_to_enable_this_option') }}</a>@endunless
</form></x-disclosure>
<x-disclosure title="{{ ui_text('examinations.copy.adjust_one_paper') }}" description="Choose a paper, room and start time. Changes are validated before saving." :open="$errors->has('schedule')||$errors->has('starts_at')||$errors->has('room_id')">
<form method="POST" data-route-form="/workflow/schedule/" class="form-grid card-body">@csrf
<x-form-field label="Paper"><select data-route-id required>@foreach($dsCourses as $paper)<option value="{{ $paper->id }}">{{ $paper->course->code }} · {{ $paper->section?->name }}</option>@endforeach</select></x-form-field>
<x-form-field label="Room"><select name="room_id" required>@foreach(AppModelsRoom::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }} · {{ $room->capacity }} seats</option>@endforeach</select></x-form-field>
<x-form-field label="Date and start time"><input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></x-form-field>
<x-form-field label="Additional rooms (optional)"><select name="extra_rooms[]" multiple>@foreach(AppModelsRoom::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></x-form-field>
<x-button type="submit">{{ ui_text('examinations.copy.validate_and_save_paper') }}</x-button>
</form></x-disclosure>
@endif
@endif
<x-disclosure title="{{ ui_text('examinations.copy.versions') }}" description="Earlier or revised copies of this examination."><div class="card-body">@foreach($ehLineage as $version)<p><a class="card-link" href="/admin/exams?exam={{ $version->id }}&amp;tab=datesheet">{{ $version->name }}</a> · {{ ucfirst($version->status) }}</p>@endforeach</div></x-disclosure>
