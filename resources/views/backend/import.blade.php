@include('ai.run-panel')
<x-card title="Import with AI" description="Upload Excel, CSV, PDF or a photo of a list. AI maps the columns; you review and confirm once. No template needed.">
<form method="POST" action="/ai/start" enctype="multipart/form-data" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="import">
@if($role==='admin')<x-select name="target" label="Contents"><option value="">Detect automatically</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="courses">Courses</option><option value="marks">Marks</option></x-select>
@else<input type="hidden" name="target" value="marks">@endif
<x-select name="course_id" label="Examination course (marks only)"><option value="">Choose course</option>
@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->course->code }} / {{ $c->examination->name }} (#{{ $c->id }})</option>
@endforeach</x-select>
<label class="field"><span>File</span><input type="file" name="file" accept=".csv,.tsv,.txt,.xlsx,.pdf,.png,.jpg,.jpeg,.webp" required></label>
<x-button type="submit" icon="upload" :disabled="!app(\App\Services\AI\AiSettings::class)->keyConfigured()">Read with AI</x-button></form></x-card>
