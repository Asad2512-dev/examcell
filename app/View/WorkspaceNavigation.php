<?php
namespace App\View;

/** Presentation-only navigation. Route authorization stays in existing middleware/controllers. */
final class WorkspaceNavigation
{
    public static function groups(string $role): array
    {
        $catalog = new \App\Support\ScreenCatalog;
        $screens = $catalog->forRole($role);
        if ($role === 'admin') {
            $definitions = [
                ['Dashboard','admin-dashboard','dashboard'],['Users & Profiles','student-directory','users'],
                ['Academic Structure','departments','book'],['Examinations','examination-list','file'],
                ['Scheduling (Date Sheets)','date-sheet-list','calendar'],['Admit Cards','eligible-admit-card-students','award'],
                ['Rooms','room-directory','home'],['Seating','seating-plan-overview','grid'],
                ['Invigilation','duty-allocation-overview','shield'],['Marks Submissions','marks-submission-overview','edit'],
                ['Results & Corrections','result-batch-overview','file'],['Grading','grading-scheme-list','award'],
                ['Result Processing','result-processing-overview','checklist'],['Rechecking','rechecking-request-queue','history'],
                ['AI Automation','ai-automation','sparkles'],['AI Analytics','analytics-overview','chart'],['AI Assistant','admin-ai-assistant','sparkles'],
                ['Reports','reports-overview','download'],['Notifications','notification-center','bell'],
                ['AI Settings','ai-settings','settings'],['Audit & Administration','audit-logs','settings'],
            ];
            $starts=array_column($definitions,1); $group=0; $children=array_fill(0,count($definitions),[]);
            foreach($screens as $screen){if($screen['kind']==='auth')continue;$found=array_search($screen['slug'],$starts,true);if($found!==false)$group=$found;$children[$group][]=$screen;}
            return array_map(fn($i)=>['label'=>$definitions[$i][0],'slug'=>$definitions[$i][1],'icon'=>$definitions[$i][2],'children'=>$children[$i]],array_keys($definitions));
        }
        $definitions=$role==='teacher' ? [
            ['Dashboard','teacher-dashboard','dashboard',['teacher-dashboard']],
            ['My Courses','my-courses','book',['my-courses','course-details','course-assessments','course-examination-schedule','course-performance']],
            ['My Students','my-students','users',['my-students','student-roster']],
            ['Marks','marks-entry','edit',['marks-entry','marks-csv-import','marks-import-errors','marks-submissions','returned-marks-correction','marks-submission-confirmation']],
            ['Schedule','examination-schedule','calendar',['examination-schedule']],
            ['Seating','seating-plans','grid',['seating-plans']],
            ['Invigilation Duties','invigilation-duties','shield',['invigilation-duties','duty-instructions','replacement-status']],
            ['Availability','profile-and-availability','clock',['profile-and-availability','unavailability-request']],
            ['Analytics','performance-analytics','chart',['performance-analytics']],
            ['Notifications','teacher-notifications','bell',['teacher-notifications']],
            ['AI Assistant','teacher-ai-assistant','sparkles',['teacher-ai-assistant']],
        ] : [
            ['Dashboard','student-dashboard','dashboard',['student-dashboard']],['Profile','my-profile','user',['my-profile']],
            ['Courses','my-courses','book',['my-courses']],['Exam Schedule','examination-schedule','calendar',['examination-schedule','updated-examination-schedule']],
            ['Admit Card','admit-card','file',['admit-card']],['Seating','seating-plan','grid',['seating-plan']],
            ['Results','my-results','award',['my-results','semester-results','unpublished-result']],
            ['Performance','performance-analysis','chart',['performance-analysis']],
            ['Rechecking','rechecking-requests','history',['rechecking-requests','submit-rechecking-request','rechecking-confirmation','rechecking-decision']],
            ['Notifications','student-notifications','bell',['student-notifications']],['AI Assistant','student-ai-assistant','sparkles',['student-ai-assistant']],
        ];
        return array_map(fn($d)=>['label'=>$d[0],'slug'=>$d[1],'icon'=>$d[2],'children'=>array_values(array_filter($screens,fn($s)=>in_array($s['slug'],$d[3],true)))],$definitions);
    }
    public static function active(array $group,string $slug,?string $resource=null): bool
    {
        if(in_array($slug,array_column($group['children'],'slug'),true))return true;
        $fallback=match($slug){'records'=>match($resource){'rooms','room-unavailability'=>'room-directory','examinations','exam-courses'=>'examination-list','grading','boundaries','components'=>'grading-scheme-list','users','students','teacher-profiles'=>'student-directory',default=>'departments'},'duty-replacements','my-duties'=>['duty-allocation-overview','invigilation-duties'],'exam-schedule'=>['date-sheet-list','examination-schedule'],'recheck-requests'=>['rechecking-request-queue','rechecking-requests'],'forecast'=>['analytics-overview','performance-analytics','performance-analysis'],default=>null};
        return in_array($group['slug'],(array)$fallback,true);
    }
}
