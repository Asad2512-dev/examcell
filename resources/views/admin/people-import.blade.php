@php
    $label = [
        'students' => 'students',
        'teachers' => 'teachers',
        'departments' => 'departments',
        'sessions' => 'academic sessions',
    ][$kind];
    $peopleKind = in_array($kind, ['students', 'teachers']);
@endphp
<x-admin-page title="Add {{ $label }} from a spreadsheet" slug="people-import" module="Academic Setup"
    :ws="['key' => $kind, 'tab' => 'main', 'primary' => 'people-import']">
    <x-page-header title="Add {{ $label }} from a spreadsheet"
        description="Upload an Excel or CSV file. We check every row first and only add the good ones. Nothing is saved until you confirm." />
    <nav class="tabs import-tabs" aria-label="Spreadsheet record type">
        @foreach (['students' => 'Students', 'teachers' => 'Teachers', 'departments' => 'Departments', 'sessions' => 'Sessions'] as $key => $name)
            <a href="/admin/import?kind={{ $key }}"
                class="btn {{ $kind === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $name }}</a>
        @endforeach
    </nav>

    @if ($result)
        <x-card title="Done. {{ $result['created'] }} {{ $label }} added"
            description="{{ $result['skipped'] ? $result['skipped'] . ' rows with problems were skipped. Fix them in your file and upload again.' : 'Every row was added.' }}">
            <div class="card-body" style="display:grid;gap:14px">
                @if ($peopleKind && $result['withPasswords'])
                    <div class="callout callout-warning"><strong>Save these sign-in details now.</strong>
                        <p>The temporary passwords are shown only on this page. Download the sheet and hand it out.
                            People can change their password after signing in.</p>
                        <x-button type="button" icon="download" data-download-credentials>Download sign-in sheet
                            (CSV)</x-button>
                    </div>
                    <div class="table-wrap" style="max-height:360px;overflow:auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    @if ($kind === 'students')
                                        <th>Roll number</th>
                                    @endif
                                    <th>
                                        Temporary password</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['credentials'] as $c)
                                    <tr>
                                        <td>{{ $c['name'] }}</td>
                                        <td>{{ $c['email'] }}</td>
                                        @if ($kind === 'students')
                                            <td>{{ $c['roll'] }}</td>
                                        @endif
                                        <td>
                                            <code>{{ $c['password'] }}</code>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <script type="application/json" id="credentials-data">{!! json_encode($result['credentials'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                    <script>
                        document.querySelector('[data-download-credentials]').addEventListener('click', function() {
                            var rows = JSON.parse(document.getElementById('credentials-data').textContent);
                            var q = function(v) {
                                v = String(v == null ? '' : v);
                                if (/^[=+\-@]/.test(v)) v = "'" + v;
                                return '"' + v.replace(/"/g, '""') + '"';
                            };
                            var csv = '﻿Name,Email,Roll number,Temporary password\n' + rows.map(function(r) {
                                return [r.name, r.email, r.roll, r.password].map(q).join(',');
                            }).join('\n');
                            var a = document.createElement('a');
                            a.href = URL.createObjectURL(new Blob([csv], {
                                type: 'text/csv'
                            }));
                            a.download = 'sign-in-sheet.csv';
                            a.click();
                        });
                    </script>
                @elseif ($peopleKind)
                    <div class="callout"><strong>How do they sign in?</strong>
                        <p>Each person opens the sign-in page, chooses “Forgot password” and uses their email address to
                            set their own password. If you would rather hand out passwords, add the next file with
                            “temporary passwords” ticked.</p>
                    </div>
                @endif
                <div class="wizard-actions">
                    @if ($kind === 'students')
                        <x-button href="/admin/exams/new" icon="arrow">Next: create an examination</x-button>
                    @else
                        <x-button href="/admin/import?kind=students" icon="arrow">Next: add students</x-button>
                    @endif
                    @if ($peopleKind)
                        <x-button variant="secondary" href="/admin/{{ $kind }}">See all
                        {{ $label }}</x-button>@else<x-button variant="secondary"
                            href="/admin/academic-setup">Open academic setup</x-button>
                    @endif
                    <x-button variant="secondary" href="/admin/import?kind={{ $kind }}">Add more</x-button>
                </div>
            </div>
        </x-card>
    @elseif ($check)
        <x-card title="Check your {{ $label }}"
            description="Green rows will be added. Red rows have a problem and will be skipped.">
            <div class="card-body" style="display:grid;gap:14px">
                <div class="import-summary"><span class="pill ok">{{ $check['ok'] }} ready to add</span><span
                        class="pill bad">{{ $check['bad'] }} with a problem</span></div>
                <div class="table-wrap" style="max-height:480px;overflow:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Name</th>
                                @if ($peopleKind)
                                    <th>Email</th>
                                    @endif @if ($kind === 'students')
                                        <th>Roll</th>
                                        @endif @if ($peopleKind)
                                            <th>Class</th>
                                        @elseif($kind === 'departments')
                                        <th>Code</th>@else<th>Dates</th>
                                        @endif
                                        <th>What we found</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($check['rows'] as $row)
                                <tr class="{{ $row['ok'] ? 'import-row-ok' : 'import-row-bad' }}">
                                    <td>{{ $row['ok'] ? '✓' : '✕' }} {{ $row['line'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    @if ($peopleKind)
                                        <td>{{ $row['email'] }}</td>
                                    @endif
                                    @if ($kind === 'students')
                                        <td>{{ $row['roll'] }}</td>
                                        @endif @if ($peopleKind)
                                            <td>{{ $row['class'] }}</td>
                                        @elseif($kind === 'departments')
                                        <td>{{ $row['code'] }}</td>@else<td>{{ $row['starts_on'] }} to
                                                {{ $row['ends_on'] }}</td>
                                        @endif
                                        <td>
                                            @if ($row['ok'])
                                                {{ $row['notes'] ? implode(' ', $row['notes']) : 'Ready' }}@else{{ implode(' ', $row['errors']) }}
                                            @endif
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($check['ok'] > 0)
                    <form method="POST" action="/admin/import/commit" class="wizard-actions" data-confirm="true"
                        data-confirm-title="Add {{ $check['ok'] }} {{ $label }}?"
                        data-confirm-message="{{ $check['bad'] ? $check['bad'] . ' rows with problems will be skipped. ' : '' }}New accounts are created. Existing accounts are never changed."
                        data-confirm-label="Add {{ $check['ok'] }} {{ $label }}">@csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        @if ($peopleKind)
                            <label class="muted"><input type="checkbox" name="passwords" value="1"> Also create
                                temporary passwords I can hand out (a little slower for big files)</label>
                        @endif
                        <x-button type="submit" icon="check">Add {{ $check['ok'] }} {{ $label }}</x-button>
                        <x-button variant="secondary" href="/admin/import?kind={{ $kind }}">Start
                            again</x-button>
                    </form>
                @else
                    <div class="callout callout-warning">Nothing can be added yet. Fix the problems in your file and
                        upload it again.
                        <p><x-button href="/admin/import?kind={{ $kind }}" variant="secondary">Upload another
                                file</x-button></p>
                    </div>
                @endif
            </div>
        </x-card>
    @elseif ($peopleKind && !$hasSession)
        <x-card title="Choose a session first">
            <div class="card-body">
                <p>Students and teachers are added to classes inside a session.</p><x-button
                    href="/admin/academic-setup?step=session">Choose session</x-button>
            </div>
        </x-card>
    @else
        <x-card title="1. Download or export"
            description="Use the Excel template for new records, or export the current list as a reusable spreadsheet.">
            <div class="card-body" style="display:grid;gap:12px">
                <div class="room-actions">
                    <x-button href="/admin/import/template/{{ $kind }}?format=xlsx" icon="download"
                        variant="secondary">Excel template (.xlsx)</x-button>
                    <x-button href="/admin/import/template/{{ $kind }}?format=csv" icon="download"
                        variant="secondary">CSV template</x-button>
                    <x-button href="/admin/import/export/{{ $kind }}?format=xlsx" icon="download">Export
                        current {{ $label }}</x-button>
                    <x-button href="/admin/import/export/{{ $kind }}?format=csv" icon="download"
                        variant="secondary">Export CSV</x-button>
                </div>
                <p class="muted">Columns:
                    @foreach ($columns as [$name, $required])
                        {{ $name }}{{ $required ? '' : ' (optional)' }}{{ $loop->last ? '' : ' · ' }}
                    @endforeach.
                    @if ($kind === 'students')
                        <br>Leave “Courses” empty to enroll each student in every course their class studies.
                    @elseif($kind === 'teachers')
                        <br>For “Courses”, list course codes like CS101; CS102 together with the class they are taught
                        in.
                    @elseif($kind === 'sessions')
                        <br>Use YYYY-MM-DD for both dates. “Active” accepts Yes or No.
                    @else
                        <br>Department codes must be unique. “Active” accepts Yes or No.
                    @endif
                </p>
            </div>
        </x-card>
        <x-card title="2. Upload your file"
            description="CSV or Excel (.xlsx), up to 1000 records. Every row is checked before anything is saved.">
            <form method="POST" action="/admin/import/preview" enctype="multipart/form-data"
                class="form-grid card-body">@csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <label class="field"><span>File</span><input type="file" name="file"
                        accept=".csv,.tsv,.txt,.xlsx" required></label>
                <x-button type="submit" icon="upload">Check my file</x-button>
            </form>
        </x-card>
    @endif
</x-admin-page>
