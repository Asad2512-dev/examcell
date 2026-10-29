@php
$academicContext=app(\App\Services\AcademicContext::class)->current();
$academicSessions=\App\Models\AcademicSession::orderByDesc('starts_on')->orderByDesc('id')->get(['id','name']);
$academicSemesters=\App\Models\Semester::orderBy('number')->get(['id','academic_session_id','name']);
$contextName=$academicSessions->firstWhere('id',$academicContext['session_id'])?->name??'Select session';
$semesterName=$academicSemesters->firstWhere('id',$academicContext['semester_id'])?->name;
@endphp
<details class="context-picker"><summary aria-label="Switch academic session and semester" title="{{ $contextName }}{{ $semesterName?' · '.$semesterName:'' }}"><x-icon name="calendar" :size="16"/><span>{{ $contextName }}</span><x-icon name="down" :size="12"/></summary><div class="context-menu"><p class="eyebrow">ACADEMIC CONTEXT</p><form method="POST" action="{{ route('academic-context.update') }}" class="context-switcher" x-data="{sessionId:@js((string)($academicContext['session_id']??'')),semesterId:@js((string)($academicContext['semester_id']??'')),semesters:@js($academicSemesters)}">
@csrf
<x-form-field label="Academic session"><select name="session_id" required x-model="sessionId" @change="semesterId=''" aria-label="Academic session">
@if($academicSessions->isEmpty())<option value="">No academic sessions configured</option>
@endif @foreach($academicSessions as $session)<option value="{{ $session->id }}" @selected($academicContext['session_id']===$session->id)>{{ $session->name }}</option>
@endforeach</select></x-form-field>
<x-form-field label="Semester"><select name="semester_id" x-model="semesterId" aria-label="Semester"><option value="">All semesters</option><template x-for="semester in semesters.filter(s=>String(s.academic_session_id)===String(sessionId))" :key="semester.id"><option :value="String(semester.id)" x-text="semester.name"></option></template></select></x-form-field><x-button type="submit" :disabled="$academicSessions->isEmpty()">Apply selection</x-button><p class="muted text-xs">Academic views and exports use this selection. Shared directories stay institution-wide.</p></form></div></details>
