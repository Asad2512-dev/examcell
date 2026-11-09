@props(['role'])
@php
$tasks=match($role){
'admin'=>[
['calendar','Create / view date sheet','Choose an exam and make its timetable','/admin/exams?tab=datesheet'],
['edit','Review marks','Submissions, results and corrections','/admin/marks'],
['grid','Organize seating','Room plans and seat assignments','/admin/seating'],
],
'teacher'=>[
['edit','Enter marks','Save a draft or submit for review','/teacher/courses?tab=marks'],
['shield','View my duties','Invigilation dates and rooms','/teacher/duties'],
['calendar','Check exam schedule','Upcoming papers and seating','/teacher/schedule'],
],
default=>[
['calendar','Exam schedule','Check dates, times and rooms','/student/exams'],
['file','My admit card','Open your available admit cards','/student/exams?tab=admit'],
['award','My results','View published grades and GPA','/student/results'],
]};
@endphp
<nav class="workspace-actions" aria-label="{{ ui_text('common.copy.everyday_tasks') }}">
@foreach($tasks as [$icon,$title,$description,$url])
<a href="{{ $url }}"><span class="workspace-action-icon"><x-icon :name="$icon" :size="21"/></span><span><strong>{{ $title }}</strong><small>{{ $description }}</small></span><x-icon name="chevron" :size="16"/></a>
@endforeach
</nav>
