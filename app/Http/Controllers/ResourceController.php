<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResourceRequest;
use App\Models as M;
use App\Services\AcademicService;
use App\Support\RoomLayout;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ResourceController
{
    public function save(ResourceRequest $r, string $resource, ?int $id = null)
    {
        $data = $r->validated();
        if ($resource === 'rooms') {
            $data = $this->roomData($data, $id);
        }
        try {
            $m = app(AcademicService::class)->save($resource, $data, $id);
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

    private function roomData(array $data, ?int $id): array
    {
        $mode = $data['layout_mode'] ?? 'capacity';
        foreach (['layout_mode', 'rows', 'cols', 'aisles', 'disabled', 'accessible', 'door_side'] as $field) {
            $layoutInput[$field] = $data[$field] ?? null;
            unset($data[$field]);
        }
        if ($mode !== 'grid') {
            // Switching back to a capacity-only room deliberately removes the old
            // physical grid; RoomLayout will use the safe legacy row arrangement.
            if ($id && M\SeatAssignment::where('room_id', $id)->exists()) {
                $room = M\Room::findOrFail($id);
                if ($room->seat_rows) {
                    throw ValidationException::withMessages(['layout_mode' => 'This room already has seating assignments. Keep its row layout, or regenerate those seating plans before switching to seat-count-only mode.']);
                }
                $highestSeat = (int) M\SeatAssignment::where('room_id', $id)->max('seat_number');
                if ((int) $data['capacity'] < $highestSeat) {
                    throw ValidationException::withMessages(['capacity' => "Seat $highestSeat is already assigned in this room. Keep at least $highestSeat seats or regenerate its seating plans first."]);
                }
            }

            return $data + ['seat_rows' => null, 'seat_columns' => null, 'aisles' => null, 'disabled_seats' => null, 'accessible_seats' => null, 'door_side' => 'right'];
        }

        $rows = (int) $layoutInput['rows'];
        $cols = (int) $layoutInput['cols'];
        $bad = [];
        $disabled = RoomLayout::parseLabels((string) $layoutInput['disabled'], $rows, $cols, $bad);
        if ($bad) {
            throw ValidationException::withMessages(['disabled' => 'Unknown seat labels: '.implode(', ', $bad).'.']);
        }
        $aisles = array_values(array_unique(array_map('intval', preg_split('/[\s,;]+/', trim((string) $layoutInput['aisles']), -1, PREG_SPLIT_NO_EMPTY))));
        if (array_filter($aisles, fn ($aisle) => $aisle < 1 || $aisle >= $cols)) {
            throw ValidationException::withMessages(['aisles' => 'Aisles go after a column between 1 and '.($cols - 1).'.']);
        }
        $layout = new RoomLayout($rows, $cols, $aisles, $disabled, $layoutInput['door_side'] ?: 'right');
        if ($layout->capacity() < 1) {
            throw ValidationException::withMessages(['disabled' => 'At least one usable seat is required.']);
        }
        $badAccessible = [];
        $labels = RoomLayout::parseLabels((string) $layoutInput['accessible'], $rows, $cols, $badAccessible);
        $accessible = array_values(array_filter(array_map(fn ($label) => $layout->numberOf($label), $labels)));
        if ($badAccessible || count($accessible) !== count($labels)) {
            throw ValidationException::withMessages(['accessible' => 'Accessible seats must be existing, usable seats.']);
        }
        $used = $id ? (M\SeatAssignment::where('room_id', $id)->max('seat_number') ?? 0) : 0;
        if ($used > $layout->capacity()) {
            throw ValidationException::withMessages(['rows' => "Seat $used is already assigned in this room. Keep at least $used usable seats or regenerate its seating plans first."]);
        }

        return $data + ['capacity' => $layout->capacity(), 'seat_rows' => $rows, 'seat_columns' => $cols, 'aisles' => $layout->aisles, 'disabled_seats' => $disabled, 'accessible_seats' => $accessible, 'door_side' => $layoutInput['door_side']];
    }
}
