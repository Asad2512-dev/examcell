<x-admin-page title="Your university" slug="university" module="Settings" :ws="['key' => 'university', 'tab' => 'main', 'primary' => 'university']">
    <x-page-header title="Your university"
        description="Tell ExamCell how your university works. It takes about a minute, and you can change it any time." />
    <form method="POST" action="/admin/university" class="uni-form">@csrf
        <x-card title="1. Name" description="Shown at the top of every page, on admit cards and on reports.">
            <div class="form-grid card-body">
                <x-setup-input name="name" label="University name" :value="$values['name']" required
                    placeholder="e.g. Riverdale University" />
                <x-setup-input name="short_name" label="Short name"
                    hint="Shown in the menu. Leave blank to use the first letters of the name." :value="$values['short_name']"
                    maxlength="24" placeholder="e.g. Riverdale" />
            </div>
        </x-card>
        <x-card title="2. Time zone" description="Used for exam times, deadlines and reminders.">
            <div class="form-grid card-body">
                <x-form-field label="Where are you?">
                    <select name="timezone" required>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', $values['timezone']) === $zone)>
                                {{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            </div>
        </x-card>
        <x-card title="3. Terms" description="The names of your teaching terms. They appear when you create a session.">
            <div class="form-grid card-body">
                <x-setup-input name="terms" label="Term names, separated by commas" :value="$values['terms']" required
                    hint="Examples: Fall, Spring  ·  Semester 1, Semester 2  ·  Trimester 1, Trimester 2, Trimester 3" />
            </div>
        </x-card>
        <x-card title="4. Exam times"
            description="When can a paper start? The automatic date sheet picks from these times each day.">
            <div class="form-grid card-body">
                @foreach ($values['slots'] as $i => $slot)
                    <x-setup-input name="slots[{{ $i }}]" :label="'Start time ' . ($i + 1) . ($i > 0 ? ' (optional)' : '')" type="time" :value="$slot"
                        :required="$i === 0" />
                @endforeach
            </div>
        </x-card>
        <x-card title="5. Grades and duties" description="Two numbers your results and invigilation rules depend on.">
            <div class="form-grid card-body">
                <x-form-field label="Highest grade point" hint="4 for a 4.0 scale, 10 for a 10-point scale.">
                    <select name="max_grade_point" required>
                        @foreach ([4, 5, 10] as $point)
                            <option value="{{ $point }}" @selected((float) old('max_grade_point', $values['max_grade_point']) === (float) $point)>{{ $point }}
                            </option>
                        @endforeach
                        @if (!in_array((float) $values['max_grade_point'], [4.0, 5.0, 10.0], true))
                            <option value="{{ $values['max_grade_point'] }}" selected>{{ $values['max_grade_point'] }}
                            </option>
                        @endif
                    </select>
                </x-form-field>
                <x-setup-input name="max_duties_per_day" label="Most invigilation duties one teacher can have in a day"
                    type="number" min="1" max="10" :value="$values['max_duties_per_day']" required />
            </div>
        </x-card>
        <div class="form-actions"><x-button type="submit" icon="check">Save and continue</x-button>
            <x-button variant="secondary" href="/admin/admin-dashboard">Cancel</x-button>
        </div>
    </form>
</x-admin-page>
