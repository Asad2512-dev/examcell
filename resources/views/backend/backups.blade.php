<x-card class=""><h2>Verified database and document backups</h2><p>Backups include the MySQL schema/records and private admit-card files. Secrets and application source are not copied. Downloaded backups contain private academic data; keep them secure.</p><form method="POST" action="/workflow/backup">
@csrf<x-button variant="primary" class="" type="submit">Create and verify backup now</x-button></form><p>Automatic backup: daily at 02:00 Asia/Karachi while <code>php artisan schedule:work</code> is running. Restore is available below or from the CLI into a separate empty database.</p></x-card>
<x-card class=""><h2>Backup history</h2>
@forelse($records as $run)<article class="notification-item"><div><strong>Backup #{{ $run->id }} · {{ $run->status }}</strong><p>{{ $run->created_at }} · {{ number_format($run->bytes/1024,1) }} KiB · Verified {{ $run->verified_at??'—' }}</p>
@if($run->checksum)<small>SHA-256 {{ $run->checksum }}</small>
@endif<p>{{ $run->failure }}</p></div>
@if($run->status==='completed')<a class="btn btn-secondary" href="/download/backup/{{ $run->id }}">Download verified backup</a>
@endif</article>
@empty<p>No backups yet. Create the first verified backup above.</p>
@endforelse @include('backend.pagination')</x-card>

<x-card class=""><h2>Restore into a separate database</h2><p>The current academic database is retained. After verification, review the restored data and staged private documents before switching your local configuration. Use the same APP_KEY that signed the backup.</p><form method="POST" action="/workflow/backup/restore" enctype="multipart/form-data" class="form-grid">
@csrf<x-form-field ><span>Signed backup ZIP</span><input type="file" name="archive" accept=".zip" required></x-form-field><x-form-field ><span>New empty database name</span><input name="target" pattern="[a-zA-Z0-9_]+_restore" placeholder="examcell_recovery_restore" required></x-form-field><label><input type="checkbox" name="confirm" value="1" required> Restore into the separate empty database and stage its documents</label><x-button variant="secondary" class="" type="submit">Verify and restore separate copy</x-button></form></x-card>
