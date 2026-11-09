@props(['prefix'=>'account'])
<div class="account-password" data-account-password>
<x-form-field label="Password" hint="Use at least 12 characters, or generate a strong password." :error="$errors->first('password')"><div class="password-input"><input id="{{ $prefix }}-password" name="password" type="password" minlength="12" maxlength="255" autocomplete="new-password" required><button type="button" data-account-reveal aria-controls="{{ $prefix }}-password" aria-pressed="false">{{ ui_text('common.copy.show') }}</button></div></x-form-field>
<x-form-field label="Confirm password"><input name="password_confirmation" type="password" minlength="12" maxlength="255" autocomplete="new-password" required></x-form-field>
<div class="page-actions"><x-button variant="secondary" type="button" data-generate-account-password icon="sparkles">{{ ui_text('common.copy.generate_password') }}</x-button><span class="muted" data-password-feedback role="status"></span></div>
</div>
