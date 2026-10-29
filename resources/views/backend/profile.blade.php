<x-card class=""><h2>Account profile</h2><dl><dt>Name</dt><dd>{{ auth()->user()->name }}</dd><dt>Email</dt><dd>{{ auth()->user()->email }}</dd><dt>Role</dt><dd>{{ ucfirst($role) }}</dd>
@if(auth()->user()->profile)<dt>Roll number</dt><dd>{{ auth()->user()->profile->roll_number }}</dd><dt>Section</dt><dd>{{ auth()->user()->profile->section->name }}</dd>
@endif</dl><p>Academic identity updates are managed by the examination cell.</p></x-card>
