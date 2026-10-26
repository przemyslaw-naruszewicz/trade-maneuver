<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EloquentPostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquent_can_create_read_update_and_delete_a_user_in_postgres(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('trade_maneuver_testing', DB::connection()->getDatabaseName());

        $user = User::factory()->create(['name' => 'Local Developer']);

        $this->assertSame('Local Developer', User::findOrFail($user->id)->name);

        $user->update(['name' => 'Updated Developer']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Developer',
        ]);

        $user->delete();

        $this->assertModelMissing($user);
    }
}
