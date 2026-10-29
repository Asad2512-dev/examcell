<div class="profile-wrap">
    <button type="button" class="profile-button" aria-expanded="false" aria-controls="account-panel" aria-label="Account menu for {{ $userName }}" data-toggle-profile>
        <span class="avatar">{{ $initials }}</span>
        <span class="profile-copy"><strong>{{ $userName }}</strong><small>{{ ucfirst($role) }}</small></span>
        <x-icon name="down" :size="14"/>
    </button>
    <div class="profile-menu account-panel" id="account-panel" role="region" aria-labelledby="account-panel-title" hidden>
        <div class="account-identity">
            <span class="account-avatar" aria-hidden="true">{{ $initials }}</span>
            <div class="account-copy"><h2 id="account-panel-title">{{ $userName }}</h2><p title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</p><span class="account-role">{{ ucfirst($role) }} account</span></div>
        </div>
        <div class="account-session"><span class="account-status-dot" aria-hidden="true"></span>Signed in<span class="account-session-label">Current session</span></div>
        <div class="account-actions">
            @if($role==='student' || $role==='teacher')
                <a class="account-action" href="{{ route('screen',[$role,$role==='student'?'my-profile':'profile-and-availability']) }}"><span class="account-action-icon"><x-icon name="user" :size="18"/></span><span>{{ $role==='teacher'?'Profile & availability':'My profile' }}</span><x-icon name="chevron" :size="14"/></a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
@csrf<button class="account-action account-signout" type="submit"><span class="account-action-icon"><x-icon name="logout" :size="18"/></span><span>Sign out</span><x-icon name="arrow" :size="15"/></button></form>
        </div>
    </div>
</div>
