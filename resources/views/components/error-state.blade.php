@props(['code'=>'403','title'=>'Access restricted','description'=>'You do not have access to this resource.'])
<div class="error-state"><div class="error-code">{{ $code }}</div><x-empty-state :title="$title" :description="$description" icon="shield">{{ $slot }}</x-empty-state></div>
