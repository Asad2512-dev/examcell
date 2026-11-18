<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResourceRequest;
use App\Services\AcademicService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ResourceController
{
    public function save(ResourceRequest $r, string $resource, ?int $id = null)
    {
        try {
            $m = app(AcademicService::class)->save($resource, $r->validated(), $id);
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? 0, [1062, 1452])) {
                throw ValidationException::withMessages(['record' => 'Duplicate record or invalid referenced record. No changes were saved.']);
            }throw $e;
        }

        if ($resource === 'rooms') {
            return redirect('/admin/rooms')->with('status', $id ? 'Room updated.' : 'Room added. Set its layout if needed, then continue to the date sheet.');
        }

        return redirect('/'.$r->user()->role.'/records?resource='.$resource)->with('status', 'Record saved.');
    }
}
