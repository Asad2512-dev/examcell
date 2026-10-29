<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ExamCell — {{ $mode==='login'?'Sign in':($mode==='forgot'?'Account recovery':'Reset password') }}</title><x-assets /></head><body class="auth-page"><a class="skip-link" href="#main-content">Skip to form</a><x-theme-toggle class="auth-theme"/>
<div class="auth-layout"><section class="auth-story"><a href="/" aria-label="ExamCell home"><x-logo wordmark /></a><div class="auth-story-content"><p class="auth-eyebrow">A CLEARER ACADEMIC WORKSPACE</p><h1>Every examination.<br><em>In good hands.</em></h1><p>One connected space for the people, plans and records behind every examination.</p><div class="auth-preview"><div><span>01 / PLAN</span><strong>Bring order.</strong><small>Schedules & resources</small></div><div><span>02 / COORDINATE</span><strong>Stay connected.</strong><small>People & workflows</small></div><div><span>03 / PUBLISH</span><strong>Build confidence.</strong><small>Reviewed results</small></div></div></div><p class="auth-story-foot"><x-icon name="shield" :size="13"/> Secure, role-based institution access</p></section>
<main class="auth-form-side" id="main-content"><div class="auth-form-container"><p class="auth-kicker">{{ $mode==='login'?'WELCOME BACK':($mode==='forgot'?'LET’S GET YOU BACK IN':'A FRESH START') }}</p><h2>{{ $mode==='login'?'Sign in to ExamCell':($mode==='forgot'?'Forgot your password?':'Set a new password') }}</h2><p class="auth-intro">{{ $mode==='login'?'Enter your institution email and password to continue.':($mode==='forgot'?'Enter your institution email. We’ll send you a link to reset your password.':'Choose a secure password with at least 12 characters.') }}</p>
@if(session('status'))<div class="callout callout-success" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())<div class="callout callout-danger" role="alert"><strong>Check the details below.</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
<form method="POST" action="{{ $mode==='login'?'/sign-in':($mode==='forgot'?'/forgot-password':'/reset-password') }}" data-auth-form>
@csrf
<x-input name="email" label="Institution email" type="email" required :value="old('email',$email??'')" autocomplete="username" placeholder="you@institution.edu" autofocus/>
@if($mode!=='forgot')<x-form-field label="Password" :error="$errors->first('password')"><span class="password-field"><input id="auth-password" type="password" name="password" required autocomplete="{{ $mode==='reset'?'new-password':'current-password' }}" @if($mode==='reset') minlength="12" data-password-strength @endif placeholder="Enter your password"><button type="button" class="password-toggle" data-toggle-password="auth-password" aria-label="Show password">Show</button></span></x-form-field>
@endif
@if($mode==='reset')<div><div class="strength-meter"><span data-strength-bar></span></div><small class="muted" data-strength-label aria-live="polite">Use at least 12 characters</small></div><input type="hidden" name="token" value="{{ $token }}"><x-form-field label="Confirm password"><span class="password-field"><input id="auth-password-confirm" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" placeholder="Enter your password again"><button type="button" class="password-toggle" data-toggle-password="auth-password-confirm" aria-label="Show password">Show</button></span></x-form-field>
@endif
@if($mode==='login')<div class="auth-options"><x-toggle name="remember" label="Remember me"/><a href="/forgot-password">Forgot password?</a></div>
@endif
<x-button class="auth-submit" type="submit">{{ $mode==='login'?'Sign in':($mode==='forgot'?'Send reset link':'Reset password') }}<x-icon name="arrow" :size="15"/></x-button></form>
@if($mode==='login')<p class="auth-help">Need an account? Contact your examination cell administrator.</p>
@else<a class="auth-back" href="/sign-in">← Back to sign in</a>
@endif
</div></main></div></body></html>
