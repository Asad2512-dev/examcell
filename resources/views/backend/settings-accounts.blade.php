@php
$acUsers=\App\Models\User::orderBy('role')->orderBy('name')->limit(300)->get();
$acMatrix=['admin'=>'Everything: academics, examinations, scheduling, seating, duties, marks approval, results, settings and AI automation','teacher'=>'Own courses and marks, assigned schedules and seating, own duties and availability, own analytics and AI assistant','student'=>'Own schedule, admit card, seat, published results, rechecking requests and AI assistant'];
@endphp
<x-card title="Permissions by role" description="What each role can reach. Role-based access is enforced on the server for every request.">
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Role</th><th>Access</th></tr></thead><tbody>
@foreach($acMatrix as $role=>$text)<tr><td>{{ ucfirst($role) }}</td><td>{{ $text }}</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
<x-card title="Accounts" description="Change a person's role or activate/deactivate their account. You cannot change your own account or remove the last administrator.">
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr></thead><tbody>
@foreach($acUsers as $u)<tr><td><form id="acc{{ $u->id }}" method="POST" action="/admin/settings/accounts/{{ $u->id }}">@csrf</form>{{ $u->name }}</td><td>{{ $u->email }}</td>
<td><select form="acc{{ $u->id }}" name="role" aria-label="Role for {{ $u->name }}" @disabled($u->id===auth()->id())>@foreach(['admin','teacher','student'] as $r)<option value="{{ $r }}" @selected($u->role===$r)>{{ ucfirst($r) }}</option>
@endforeach</select></td>
<td><input form="acc{{ $u->id }}" type="checkbox" name="active" value="1" aria-label="Active" @checked($u->active) @disabled($u->id===auth()->id())></td>
<td>@if($u->id!==auth()->id())<x-button form="acc{{ $u->id }}" type="submit" variant="secondary" class="btn-small">Save</x-button>@else<span class="muted text-xs">You</span>@endif</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
