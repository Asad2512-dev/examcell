
@if($records)<div class="table-footer"><span>{{ $records->total() }} records · page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span><div>
@if($records->previousPageUrl())<a class="btn btn-secondary" href="{{ $records->previousPageUrl() }}">Previous</a>
@endif @if($records->nextPageUrl())<a class="btn btn-secondary" href="{{ $records->nextPageUrl() }}">Next</a>
@endif</div></div>
@endif
