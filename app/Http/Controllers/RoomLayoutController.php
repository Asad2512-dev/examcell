<?php

namespace App\Http\Controllers;

use App\Models as M;
use App\Services\AuditService;
use App\Support\RoomLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as V;

class RoomLayoutController
{
    public function update(Request $r, M\Room $room)
    {
        $d = $r->validate(['rows' => 'required|integer|between:1,40', 'cols' => 'required|integer|between:1,30', 'aisles' => 'nullable|string|max:200', 'disabled' => 'nullable|string|max:2000', 'accessible' => 'nullable|string|max:500', 'door_side' => 'required|in:left,right']);
        $bad = [];
        $disabled = RoomLayout::parseLabels($d['disabled'] ?? '', $d['rows'], $d['cols'], $bad);
        $aisles = array_values(array_unique(array_map('intval', preg_split('/[\s,;]+/', trim($d['aisles'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))));
        if ($bad) {
            throw V::withMessages(['disabled' => 'Unknown seat labels: '.implode(', ', $bad).'.']);
        }
        if (array_filter($aisles, fn ($a) => $a < 1 || $a >= $d['cols'])) {
            throw V::withMessages(['aisles' => 'Aisles go after a column between 1 and '.($d['cols'] - 1).'.']);
        }
        $layout = new RoomLayout($d['rows'], $d['cols'], $aisles, $disabled, $d['door_side']);
        if ($layout->capacity() < 1) {
            throw V::withMessages(['disabled' => 'At least one usable seat is required.']);
        }
        $badAcc = [];
        $accLabels = RoomLayout::parseLabels($d['accessible'] ?? '', $d['rows'], $d['cols'], $badAcc);
        $accessible = array_values(array_filter(array_map(fn ($l) => $layout->numberOf($l), $accLabels)));
        if ($badAcc || count($accessible) !== count($accLabels)) {
            throw V::withMessages(['accessible' => 'Accessible seats must be existing, enabled seats.']);
        }
        $used = M\SeatAssignment::where('room_id', $room->id)->max('seat_number') ?? 0;
        if ($used > $layout->capacity()) {
            throw V::withMessages(['rows' => "Seat $used in this room is already assigned; keep at least that many usable seats or regenerate those seating plans first."]);
        }
        $before = $room->only('seat_rows', 'seat_columns', 'aisles', 'disabled_seats', 'capacity', 'door_side', 'accessible_seats');
        $room->update(['seat_rows' => $d['rows'], 'seat_columns' => $d['cols'], 'aisles' => $layout->aisles, 'disabled_seats' => $disabled, 'door_side' => $d['door_side'], 'accessible_seats' => $accessible, 'capacity' => $layout->capacity()]);
        app(AuditService::class)->record('room.layout_updated', $room, ['before' => $before, 'capacity' => $layout->capacity()]);

        return back()->with('status', $room->name.' layout saved — '.$layout->capacity().' usable seats.');
    }
}
