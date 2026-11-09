@props(['course'])
@php
$deadlineTeachers=\App\Models\User::where('role','teacher')->whereHas('assignments',fn($q)=>$q->where('course_id',$course->course_id)->where('section_id',$course->section_id))->orderBy('name')->get(['id','name']);
$defaultDeadline=$course->marks_due_at?->timezone('Asia/Karachi')->format('Y-m-d\TH:i')??'';
$teacherDeadlines=$course->submissionDeadlines->mapWithKeys(fn($override)=>[(string)$override->teacher_id=>$override->marks_due_at->timezone('Asia/Karachi')->format('Y-m-d\TH:i')]);
@endphp
<x-card title="{{ ui_text('common.copy.set_a_submission_deadline') }}" description="The course default applies to all assigned teachers. A teacher override takes precedence. Times are in Asia/Karachi."><form method="POST" action="{{ route('marks.deadline.update',$course) }}" class="form-grid" x-data="{teacherId:@js((string)old('teacher_id','')),deadline:@js(old('marks_due_at',$defaultDeadline)),defaultDeadline:@js($defaultDeadline),overrides:@js($teacherDeadlines)}">
@csrf<x-form-field label="Applies to"><select name="teacher_id" x-model="teacherId" @change="deadline=teacherId?(overrides[teacherId]??defaultDeadline):defaultDeadline"><option value="">{{ ui_text('common.copy.course_default_all_assigned_teachers') }}</option>
@foreach($deadlineTeachers as $deadlineTeacher)<option value="{{ $deadlineTeacher->id }}">{{ $deadlineTeacher->name }} · teacher override</option>
@endforeach</select></x-form-field><x-form-field label="Deadline (PKT)"><input type="datetime-local" name="marks_due_at" x-model="deadline" value="{{ old('marks_due_at',$course->marks_due_at?->timezone('Asia/Karachi')->format('Y-m-d\TH:i')) }}"><small>{{ ui_text('common.copy.leave_blank_to_clear_the_course_default_or_remove_the_se') }}</small></x-form-field><x-button type="submit" icon="calendar">{{ ui_text('common.copy.save_deadline') }}</x-button></form><div class="card-body"><p class="muted text-xs">Course default: {{ $course->marks_due_at?$course->marks_due_at->timezone('Asia/Karachi')->format('d M Y H:i').' PKT':'No deadline set' }}</p>
@foreach($course->submissionDeadlines as $override)<p class="muted text-xs">{{ $deadlineTeachers->firstWhere('id',$override->teacher_id)?->name??'Assigned teacher' }}: {{ $override->marks_due_at->timezone('Asia/Karachi')->format('d M Y H:i') }} PKT</p>
@endforeach</div></x-card>
