@php
$acUsers=\App\Models\User::orderBy('role')->orderBy('name')->limit(300)->get();
$acMatrix=['admin'=>'Everything: academics, examinations, scheduling, seating, duties, marks approval, results, settings and AI automation','teacher'=>'Own courses and marks, assigned schedules and seating, own duties and availability, own analytics and AI assistant','student'=>'Own schedule, admit card, seat, published results, rechecking requests and AI assistant'];
@endphp
<x-card title="{{ ui_text('admin.copy.permissions_by_role') }}" description="What each role can reach. Role-based access is enforced on the server for every request.">
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('admin.copy.role') }}</th><th>{{ ui_text('admin.copy.access') }}</th></tr></thead><tbody>
@foreach($acMatrix as $role=>$text)<tr><td>{{ ucfirst($role) }}</td><td>{{ $text }}</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
<x-card title="{{ ui_text('admin.copy.accounts') }}" description="Change a person's role or activate/deactivate their account. You cannot change your own account or remove the last administrator.">
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('admin.copy.name') }}</th><th>{{ ui_text('admin.copy.email') }}</th><th>{{ ui_text('admin.copy.role') }}</th><th>{{ ui_text('admin.copy.active') }}</th><th></th></tr></thead><tbody>
@foreach($acUsers as $u)<tr><td><form id="acc{{ $u->id }}" method="POST" action="/admin/settings/accounts/{{ $u->id }}">@csrf</form>{{ $u->name }}</td><td>{{ $u->email }}</td>
<td><select form="acc{{ $u->id }}" name="role" aria-label="Role for {{ $u->name }}" @disabled($u->id===auth()->id())>@foreach(['admin','teacher','student'] as $r)<option value="{{ $r }}" @selected($u->role===$r)>{{ ucfirst($r) }}</option>
@endforeach</select></td>
<td><input form="acc{{ $u->id }}" type="checkbox" name="active" value="1" aria-label="{{ ui_text('admin.copy.active') }}" @checked($u->active) @disabled($u->id===auth()->id())></td>
<td>@if($u->id!==auth()->id())<x-button form="acc{{ $u->id }}" type="submit" variant="secondary" class="btn-small">{{ ui_text('admin.copy.save') }}</x-button>@else<span class="muted text-xs">{{ ui_text('admin.copy.you') }}</span>@endif</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
