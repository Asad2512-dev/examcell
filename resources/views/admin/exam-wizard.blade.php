<x-admin-page :title="$exam ? 'Add papers' : 'New examination'" slug="exam-wizard" module="Exams" :ws="['key' => 'exams', 'tab' => 'overview', 'primary' => 'exam-wizard']">
    <x-page-header :title="$exam ? 'Add papers to ' . $exam->name : 'Create a new examination'"
        description="Choose the classes and papers. ExamCell creates every paper and its marks breakdown for you in one go." />
    @if ($classes->isEmpty())
        <x-card title="No classes have papers yet">
            <div class="card-body">
                <p>An examination is made from your classes and the courses they study. Add classes, courses and
                    students first.</p>
                <x-button href="/admin/academic-setup">Open Academic Setup</x-button>
            </div>
        </x-card>
    @else
        <form method="POST" action="/admin/exams/new" class="wizard-form" data-wizard>@csrf
            @if ($exam)
                <input type="hidden" name="exam_id" value="{{ $exam->id }}">
            @else
                <x-card title="1. Name the examination" :description="'For the ' . $session->name . ' session.'">
                    <div class="form-grid card-body">
                        <x-setup-input name="name" label="Examination name" :value="$session->name . ' Final Exams'" required />
                        <x-form-field label="Type">
                            <select name="type" required data-duration-source>
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" data-duration="{{ $durations[$key] }}"
                                        @selected(old('type', 'final') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                        <x-form-field label="Grading scheme"
                            hint="How percentages turn into grades. Must be approved before the exam can be published.">
                            <select name="grading_scheme_id" required>
                                @foreach ($schemes as $scheme)
                                    <option value="{{ $scheme->id }}" @selected((string) old('grading_scheme_id') === (string) $scheme->id)>
                                        {{ $scheme->name }}{{ $scheme->status === 'approved' ? '' : ' (' . $scheme->status . ')' }}
                                    </option>
                                @endforeach
                            </select>
                        </x-form-field>
                    </div>
                </x-card>
            @endif
            <x-card :title="($exam ? '1' : '2') . '. Tick the papers'"
                description="Each class lists the courses its students study. Untick anything that should not sit this exam.">
                <div class="card-body" style="display:grid;gap:18px">
                    <label class="muted"><input type="checkbox" data-check-all> Select all papers</label>
                    @foreach ($classes as $class)
                        <fieldset class="wizard-group">
                            <legend><strong>{{ $class['section']->name }}</strong> <small
                                    class="muted">{{ $class['section']->department->name }} ·
                                    {{ $class['section']->semester->name }}</small></legend>
                            <div class="wizard-classes">
                                @foreach ($class['papers'] as $paper)
                                    @php $value = $class['section']->id.':'.$paper['course']->id; @endphp
                                    <label class="wizard-class">
                                        <input type="checkbox" name="papers[]" value="{{ $value }}"
                                            @checked(!$paper['exists'] && (old('papers') === null || in_array($value, (array) old('papers'), true))) @disabled($paper['exists'])>
                                        <span><strong>{{ $paper['course']->code }}</strong>
                                            {{ $paper['course']->name }}
                                            <small>{{ $paper['exists'] ? 'Already in this examination' : $paper['students'] . ' students' }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    @error('papers')
                        <small class="field-error" role="alert">{{ $message }}</small>
                    @enderror
                </div>
            </x-card>
            <x-card :title="($exam ? '2' : '3') . '. Paper settings'"
                description="These apply to every paper you ticked. You can change a single paper later.">
                <div class="form-grid card-body">
                    <x-setup-input name="duration_minutes" label="Duration (minutes)" type="number" min="15"
                        max="480" :value="$durations['final']" required data-duration-target />
                    <x-setup-input name="maximum" label="Total marks" type="number" min="1" step="1"
                        value="100" required />
                    <x-setup-input name="pass_percent" label="Pass mark (%)" type="number" min="0"
                        max="100" value="40" required />
                </div>
            </x-card>
            <x-card :title="($exam ? '3' : '4') . '. How are marks split?'"
                description="Teachers will enter marks for each part. The parts must add up to 100%.">
                <div class="wizard-presets card-body">
                    @foreach ($presets as $key => [$label, $hint, $parts])
                        <label class="wizard-preset">
                            <span><input type="radio" name="preset" value="{{ $key }}"
                                    @checked(old('preset', 'single') === $key) required> <strong>{{ $label }}</strong></span>
                            <small>{{ $hint }}</small>
                        </label>
                    @endforeach
                </div>
            </x-card>
            <div class="wizard-actions">
                <x-button type="submit" icon="check">{{ $exam ? 'Add papers' : 'Create examination' }}</x-button>
                <x-button variant="secondary" href="/admin/admin-dashboard">Cancel</x-button>
                <small class="muted">Next you will make the date sheet. Nothing is shown to students until you
                    publish.</small>
            </div>
        </form>
        <script>
            (function() {
                var f = document.querySelector('[data-wizard]');
                if (!f) return;
                var all = f.querySelector('[data-check-all]');
                if (all) all.addEventListener('change', function() {
                    f.querySelectorAll('input[name="papers[]"]:not(:disabled)').forEach(function(c) {
                        c.checked = all.checked;
                    });
                });
                var type = f.querySelector('[data-duration-source]'),
                    dur = f.querySelector('[data-duration-target]');
                if (type && dur) type.addEventListener('change', function() {
                    var o = type.options[type.selectedIndex];
                    if (o && o.dataset.duration) dur.value = o.dataset.duration;
                });
            })();
        </script>
    @endif
</x-admin-page>
