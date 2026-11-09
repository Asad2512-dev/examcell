<x-card class=""><h2>{{ ui_text('admin.copy.verified_database_and_document_backups') }}</h2><p>{{ ui_text('admin.copy.backups_include_the_mysql_schema_records_and_private_adm') }}</p><form method="POST" action="/workflow/backup">
@csrf<x-button variant="primary" class="" type="submit">{{ ui_text('admin.copy.create_and_verify_backup_now') }}</x-button></form><p>{{ ui_text('admin.copy.automatic_backup_daily_at_02_00_asia_karachi_while') }} <code>php artisan schedule:work</code> is running. Restore is available below or from the CLI into a separate empty database.</p></x-card>
<x-card class=""><h2>{{ ui_text('admin.copy.backup_history') }}</h2>
@forelse($records as $run)<article class="notification-item"><div><strong>Backup #{{ $run->id }} · {{ $run->status }}</strong><p>{{ $run->created_at }} · {{ number_format($run->bytes/1024,1) }} KiB · Verified {{ $run->verified_at??'—' }}</p>
@if($run->checksum)<small>SHA-256 {{ $run->checksum }}</small>
@endif<p>{{ $run->failure }}</p></div>
@if($run->status==='completed')<a class="btn btn-secondary" href="/download/backup/{{ $run->id }}">{{ ui_text('admin.copy.download_verified_backup') }}</a>
@endif</article>
@empty<p>{{ ui_text('admin.copy.no_backups_yet_create_the_first_verified_backup_above') }}</p>
@endforelse @include('backend.pagination')</x-card>

<x-card class=""><h2>{{ ui_text('admin.copy.restore_into_a_separate_database') }}</h2><p>{{ ui_text('admin.copy.the_current_academic_database_is_retained_after_verifica') }}</p><form method="POST" action="/workflow/backup/restore" enctype="multipart/form-data" class="form-grid">
@csrf<x-form-field ><span>{{ ui_text('admin.copy.signed_backup_zip') }}</span><input type="file" name="archive" accept=".zip" required></x-form-field><x-form-field ><span>{{ ui_text('admin.copy.new_empty_database_name') }}</span><input name="target" pattern="[a-zA-Z0-9_]+_restore" placeholder="{{ ui_text('admin.copy.examcell_recovery_restore') }}" required></x-form-field><label><input type="checkbox" name="confirm" value="1" required> {{ ui_text('admin.copy.restore_into_the_separate_empty_database_and_stage_its_d') }}</label><x-button variant="secondary" class="" type="submit">{{ ui_text('admin.copy.verify_and_restore_separate_copy') }}</x-button></form></x-card>
