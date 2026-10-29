@props(['type'=>'results','courses'=>[]])
<x-card title="Export a report" description="Choose the scope and format for this download. Only records authorized for your account are included."><form method="GET" action="/reports/{{ $type }}" class="form-grid"><x-select name="course_id" label="Examination course"><option value="">All authorized courses</option>
@foreach($courses as $uiCourse)<option value="{{ $uiCourse->id }}">{{ $uiCourse->course->code }} · {{ $uiCourse->examination->name }}</option>
@endforeach</x-select><x-select name="session_id" label="Academic session"><option value="">All authorized sessions</option>
@foreach(\App\Models\AcademicSession::whereIn('id',collect($courses)->pluck('examination.academic_session_id')->filter()->unique())->get() as $uiSession)<option value="{{ $uiSession->id }}">{{ $uiSession->name }}</option>
@endforeach</x-select><x-select name="format" label="Download format"><option value="pdf">PDF document</option><option value="csv">CSV spreadsheet</option></x-select><x-button type="submit" icon="download">Export report</x-button></form></x-card>
