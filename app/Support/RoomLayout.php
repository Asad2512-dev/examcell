<?php

namespace App\Support;

use App\Models\Room;

/**
 * Physical seat layout of a room. Seat numbers (1..capacity) are assigned row by row, front to back,
 * left to right, skipping disabled positions. Labels are row letter + column number (A1, A2 … B1).
 * An unconfigured room keeps the old behaviour: `columns()` seats per row, ceil(capacity/columns) rows.
 */
final class RoomLayout
{
    /** @var array<int, array{n:int,r:int,c:int,label:string}> */
    private array $byNumber = [];

    private array $byCell = [];

    public function __construct(public int $rows, public int $cols, public array $aisles = [], public array $disabled = [], public string $door = 'right', ?int $limit = null)
    {
        $this->aisles = array_values(array_unique(array_filter(array_map('intval', $aisles), fn ($a) => $a >= 1 && $a < $cols)));
        sort($this->aisles);
        $off = array_flip(array_map('strtoupper', $disabled));
        $n = 0;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $label = self::label($r, $c);
                if (isset($off[$label]) || ($limit !== null && $n >= $limit)) {
                    continue;
                }
                $n++;
                $this->byNumber[$n] = ['n' => $n, 'r' => $r, 'c' => $c, 'label' => $label];
                $this->byCell[$r.':'.$c] = $n;
            }
        }
    }

    public static function for(Room $room): self
    {
        if ($room->seat_rows) {
            return new self((int) $room->seat_rows, $room->columns(), $room->aisles ?? [], $room->disabled_seats ?? [], $room->door_side ?: 'right');
        }
        $cols = $room->columns();

        return new self((int) ceil(max(1, $room->capacity) / $cols), $cols, [], [], $room->door_side ?: 'right', (int) $room->capacity);
    }

    public static function label(int $r, int $c): string
    {
        $letters = '';
        for ($i = $r; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letters = chr(65 + $i % 26).$letters;
        }

        return $letters.($c + 1);
    }

    /** Parse "A1, b4" into valid labels within this grid (unknown labels are returned in $invalid). */
    public static function parseLabels(string $text, int $rows, int $cols, array &$invalid = []): array
    {
        $valid = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $valid[self::label($r, $c)] = true;
            }
        }
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtoupper(trim($text)), -1, PREG_SPLIT_NO_EMPTY) as $l) {
            isset($valid[$l]) ? $out[] = $l : $invalid[] = $l;
        }

        return array_values(array_unique($out));
    }

    public function capacity(): int
    {
        return count($this->byNumber);
    }

    public function positions(): array
    {
        return array_values($this->byNumber);
    }

    public function position(int $n): ?array
    {
        return $this->byNumber[$n] ?? null;
    }

    public function labelOf(int $n): string
    {
        return $this->byNumber[$n]['label'] ?? (string) $n;
    }

    public function numberOf(string $label): ?int
    {
        foreach ($this->byNumber as $p) {
            if ($p['label'] === strtoupper($label)) {
                return $p['n'];
            }
        }

        return null;
    }

    private function at(int $r, int $c): ?int
    {
        return $this->byCell[$r.':'.$c] ?? null;
    }

    /** Seat directly to the right in the same row, unless an aisle or gap separates them. */
    public function rightOf(int $n): ?int
    {
        $p = $this->byNumber[$n] ?? null;
        if (! $p || in_array($p['c'] + 1, $this->aisles, true)) {
            return null;
        }

        return $this->at($p['r'], $p['c'] + 1);
    }

    public function behind(int $n): ?int
    {
        $p = $this->byNumber[$n] ?? null;

        return $p ? $this->at($p['r'] + 1, $p['c']) : null;
    }

    /** Rows of seat numbers (null = disabled/absent), front row first — compact form for the AI prompt. */
    public function grid(): array
    {
        $g = [];
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                $g[$r][] = $this->at($r, $c);
            }
        }

        return $g;
    }

    public function toArray(): array
    {
        $disabled = [];
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->cols; $c++) {
                if ($this->at($r, $c) === null && in_array(self::label($r, $c), array_map('strtoupper', $this->disabled), true)) {
                    $disabled[] = self::label($r, $c);
                }
            }
        }

        return ['rows' => $this->rows, 'cols' => $this->cols, 'aisles' => $this->aisles, 'door' => $this->door, 'disabled' => $disabled, 'positions' => $this->positions()];
    }

    /** "3rd row from the front, 2nd seat from the door". */
    public function directions(int $n): string
    {
        $p = $this->byNumber[$n] ?? null;
        if (! $p) {
            return '';
        }
        $fromDoor = $this->door === 'left' ? $p['c'] + 1 : $this->cols - $p['c'];

        return self::ordinal($p['r'] + 1).' row from the front, '.self::ordinal($fromDoor).' seat from the door side';
    }

    public static function ordinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;

        return $n.($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
}
