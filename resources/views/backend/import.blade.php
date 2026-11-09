@if($role==='teacher')<div class="role-flow-note"><strong>{{ ui_text('admin.copy.choose_course_upload_file_review_extracted_marks_confirm') }}</strong><p>{{ ui_text('admin.copy.check_every_value_in_the_preview_imported_marks_remain_s') }}</p></div>@endif
@include('ai.run-panel')
<x-card title="{{ ui_text('admin.copy.import_with_ai') }}" description="Upload Excel, CSV, PDF or a photo of a list. AI maps the columns; you review and confirm once. No template needed.">
<form method="POST" action="/ai/start" enctype="multipart/form-data" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="import">
@if($role==='admin')<x-select name="target" label="Contents"><option value="">{{ ui_text('admin.copy.detect_automatically') }}</option><option value="students">{{ ui_text('admin.copy.students') }}</option><option value="teachers">{{ ui_text('admin.copy.teachers') }}</option><option value="courses">{{ ui_text('admin.copy.courses') }}</option><option value="marks">{{ ui_text('admin.copy.marks') }}</option></x-select>
@else<input type="hidden" name="target" value="marks">@endif
<x-select name="course_id" label="Choose examination course" :required="$role==='teacher'"><option value="">{{ ui_text('admin.copy.choose_course') }}</option>
@foreach($courses as $c)<option value="{{ $c->id }}" @selected((string)request('course_id')===(string)$c->id)>{{ $c->course->code }} · {{ $c->examination->name }} · {{ $c->section?->name }}</option>
@endforeach</x-select>
<label class="field"><span>{{ ui_text('admin.copy.file') }}</span><input type="file" name="file" accept=".csv,.tsv,.txt,.xlsx,.pdf,.png,.jpg,.jpeg,.webp" required></label>
<x-button type="submit" icon="upload" :disabled="!app(\App\Services\AI\AiSettings::class)->keyConfigured()">{{ ui_text('admin.copy.read_with_ai') }}</x-button></form></x-card>

@if($role==='teacher')<p class="role-flow-note">{{ ui_text('admin.copy.prefer_to_type_marks') }} <a class="card-link" href="/teacher/courses?tab=marks">{{ ui_text('admin.copy.open_marks_entry') }}</a></p>@endif
@unless(app(\App\Services\AI\AiSettings::class)->keyConfigured())<div class="callout">{{ ui_text('admin.copy.ai_import_is_not_configured_yet_contact_the_examination') }}</div>@endunless
