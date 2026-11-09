<form class="filter-bar" method="GET">
@php $courseWorkspace=request()->attributes->get('ws'); @endphp
@if($courseWorkspace)<input type="hidden" name="tab" value="{{ $courseWorkspace['tab'] }}">@endif
@if(is_string(request('view'))&&request('view'))<input type="hidden" name="view" value="{{ request('view') }}">@endif
<x-form-field><span>{{ ui_text('admin.copy.choose_a_course') }}</span><select name="course_id" aria-label="{{ ui_text('admin.copy.choose_an_examination_course') }}">
@foreach($courses as $c)<option value="{{ $c->id }}" @selected($selected?->id===$c->id)>{{ $c->course->code }} · {{ $c->examination->name }} · {{ $c->section?->name??'Section '.$c->section_id }}</option>
@endforeach</select></x-form-field><x-button variant="secondary" type="submit">{{ ui_text('admin.copy.open_course') }}</x-button></form>
