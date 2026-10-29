<x-card title="Import academic records" description="Prepare your file, review it, then run server validation."><form method="POST" action="/workflow/import" enctype="multipart/form-data" class="form-grid" data-wizard-form>
@csrf
<x-select name="kind" label="Import type">
@if($role==='admin')<option value="students">Students</option>
@endif<option value="marks">Marks</option></x-select>
<x-select name="course_id" label="Examination course (marks only)"><option value="">Choose course</option>
@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->course->code }} / {{ $c->examination->name }} (#{{ $c->id }})</option>
@endforeach</x-select>
<x-file-upload/>
<label class="toggle-field"><input type="checkbox" name="commit" value="1">Commit validated rows to the database</label>
<x-button type="submit" icon="upload">Validate / import</x-button></form>
<div class="card-body"><div class="callout"><strong>Start with the correct template.</strong><p><a class="card-link" href="/assets/sample-students.csv">Download student CSV</a> · <a class="card-link" href="/assets/sample-marks.csv">Download marks CSV</a></p><p>Maximum 2 MiB and 1,000 rows. XLSX accepts one data worksheet; formulas and macros are rejected. Preview validation does not save records. To save, upload again with Commit selected.</p></div><details><summary class="card-link">View required columns</summary><p class="muted text-xs mt-3">Students: roll_number, name, email, section_id.<br>Marks: roll_number, component_id, score, absent (0 or 1).<br>Existing student accounts are never overwritten.</p></details></div></x-card>
@if($errors->has('file'))<x-card title="Import validation errors"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Source</th><th>Server validation message</th></tr></thead><tbody>
@foreach($errors->get('file') as $uiError)<tr><td>Uploaded file</td><td>{{ $uiError }}</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
@endif
@if(session('import_summary'))
@php
$uiSummary=session('import_summary');
@endphp

<x-card :title="($uiSummary['committed']?'Imported ':'Validated ').$uiSummary['rows'].' rows'"><div class="card-body"><x-badge :status="$uiSummary['committed']?'Completed':'Preview'"/>
@if(!empty($uiSummary['note']))<p class="muted text-xs mt-3">{{ $uiSummary['note'] }}</p>
@endif</div><x-data-table><table class="data-table"><thead><tr>
@foreach(array_keys($uiSummary['preview'][0]??[]) as $uiHeader)<th>{{ ucwords(str_replace('_',' ',$uiHeader)) }}</th>
@endforeach</tr></thead><tbody>
@foreach($uiSummary['preview']??[] as $uiRow)<tr>
@foreach($uiRow as $uiValue)<td>{{ is_bool($uiValue)?($uiValue?'Yes':'No'):$uiValue }}</td>
@endforeach</tr>
@endforeach</tbody></table></x-data-table></x-card>
@endif
