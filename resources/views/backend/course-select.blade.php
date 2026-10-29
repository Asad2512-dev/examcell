<form class="filter-bar" method="GET"><x-form-field ><span>Examination course</span><select name="course_id">
@foreach($courses as $c)<option value="{{ $c->id }}" @selected($selected?->id===$c->id)>#{{ $c->id }} · {{ $c->course->code }} · {{ $c->examination->name }} · section {{ $c->section_id }}</option>
@endforeach</select></x-form-field><x-button variant="secondary" class="" type="submit">Open course</x-button></form>
