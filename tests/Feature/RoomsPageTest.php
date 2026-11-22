<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

class RoomsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_room_creation_and_edit_forms(): void
    {
        $admin = User::create([
            'name' => 'Room Admin',
            'email' => 'rooms@example.test',
            'password' => 'TestPassword2026!',
            'role' => 'admin',
        ]);
        $room = Room::create(['name' => 'Room regression', 'capacity' => 30, 'active' => true]);

        $this->actingAs($admin)->get('/admin/rooms')
            ->assertOk()
            ->assertSee('Add a room')
            ->assertSee('Continue to date sheet')
            ->assertDontSee('academic-module-picker', false)
            ->assertSee('name="capacity"', false)
            ->assertSee('Room regression')
            ->assertDontSee('@php', false);

        $this->get('/admin/records?resource=rooms&id='.$room->id)
            ->assertOk()
            ->assertSee('Edit room')
            ->assertSee('value="Room regression"', false);
    }

    public function test_room_saves_return_to_the_simple_rooms_screen(): void
    {
        $admin = User::create(['name' => 'Room Admin', 'email' => 'save-rooms@example.test', 'password' => 'TestPassword2026!', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('resource.save', ['rooms']), ['name' => 'Hall B', 'capacity' => 40, 'active' => 1])
            ->assertRedirect('/admin/rooms')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rooms', ['name' => 'Hall B', 'capacity' => 40, 'active' => true]);
    }

    public function test_admin_can_create_an_ai_ready_room_from_its_physical_arrangement(): void
    {
        $admin = User::create(['name' => 'Room Admin', 'email' => 'grid-rooms@example.test', 'password' => 'TestPassword2026!', 'role' => 'admin']);

        $this->actingAs($admin)->post(route('resource.save', ['rooms']), [
            'name' => 'Examination Hall',
            'layout_mode' => 'grid',
            'rows' => 5,
            'cols' => 8,
            'aisles' => '4',
            'disabled' => 'A1, E8',
            'accessible' => 'A2, A3',
            'door_side' => 'left',
            'active' => 1,
        ])->assertRedirect('/admin/rooms')->assertSessionHasNoErrors();

        $room = Room::where('name', 'Examination Hall')->firstOrFail();
        $this->assertSame(38, $room->capacity);
        $this->assertSame(5, $room->seat_rows);
        $this->assertSame(8, $room->seat_columns);
        $this->assertSame([4], $room->aisles);
        $this->assertSame(['A1', 'E8'], $room->disabled_seats);
        $this->assertSame([1, 2], $room->accessible_seats);
        $this->assertSame('left', $room->door_side);
    }

    public function test_authentication_icons_render_svg_paths(): void
    {
        $html = view('components.auth-icon', [
            'name' => 'mail',
            'size' => 20,
            'attributes' => new ComponentAttributeBag,
        ])->render();

        $this->assertStringContainsString('<rect', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('?>', $html);
    }
}
