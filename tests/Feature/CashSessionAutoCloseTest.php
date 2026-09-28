<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashSessionAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function receptionist(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('receptionist');

        return $user;
    }

    protected function openSessionAt(User $user, string $at, float $opening = 50000): CashSession
    {
        $session = CashSession::create([
            'opened_by' => $user->id,
            'opening_amount' => $opening,
            'started_at' => Carbon::parse($at),
            'status' => 'open',
        ]);
        CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 120000,
            'reason' => 'Invoice payment',
            'created_by' => $user->id,
        ]);

        return $session;
    }

    public function test_command_auto_closes_sessions_from_a_previous_business_day(): void
    {
        $user = $this->receptionist();
        Carbon::setTestNow('2026-09-28 00:00:05');

        $stale = $this->openSessionAt($user, '2026-09-27 08:00:00');
        $today = $this->openSessionAt($user, '2026-09-28 00:00:01');

        $this->artisan('cash-sessions:auto-close')->assertSuccessful();

        $stale->refresh();
        $this->assertSame('auto_closed', $stale->status);
        $this->assertEquals(170000, (float) $stale->expected_cash_at_close);
        $this->assertNull($stale->closing_amount);
        $this->assertSame('2026-09-27 23:59:59', $stale->ended_at->format('Y-m-d H:i:s'));

        $this->assertSame('open', $today->refresh()->status);
    }

    public function test_business_day_cutoff_is_configurable(): void
    {
        config(['clinic.cash_day_starts_at' => '06:00']);
        Carbon::setTestNow('2026-09-28 03:00:00');

        $this->assertSame('2026-09-27 06:00:00', CashSession::businessDayStart()->format('Y-m-d H:i:s'));
    }

    public function test_stale_session_is_closed_lazily_so_new_cash_payments_need_a_new_session(): void
    {
        $user = $this->receptionist();
        Carbon::setTestNow('2026-09-28 09:00:00');
        $stale = $this->openSessionAt($user, '2026-09-27 08:00:00');

        $this->assertNull(CashSession::activeForUser($user->id));
        $this->assertSame('auto_closed', $stale->refresh()->status);

        $this->actingAs($user)
            ->post(route('cash-drawer.open'), ['opening_amount' => 20000])
            ->assertRedirect(route('cash-drawer.index'));
        $this->assertNotNull(CashSession::activeForUser($user->id));
    }

    public function test_auto_closed_session_can_be_reconciled_and_variance_needs_a_note(): void
    {
        $user = $this->receptionist();
        Carbon::setTestNow('2026-09-28 09:00:00');
        $session = $this->openSessionAt($user, '2026-09-27 08:00:00');
        CashSession::closeStale();

        $this->actingAs($user)
            ->post(route('cash-drawer.reconcile', $session), ['closing_amount' => 165000])
            ->assertSessionHasErrors('notes');

        $this->actingAs($user)
            ->post(route('cash-drawer.reconcile', $session), ['closing_amount' => 165000, 'notes' => 'Short 5k, change given'])
            ->assertRedirect(route('cash-drawer.index'));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertEquals(-5000, (float) $session->variance);
        $this->assertSame($user->id, $session->closed_by);
    }

    public function test_receptionist_cannot_reconcile_someone_elses_session(): void
    {
        $owner = $this->receptionist();
        $other = User::factory()->create();
        $other->assignRole('receptionist');
        Carbon::setTestNow('2026-09-28 09:00:00');
        $session = $this->openSessionAt($owner, '2026-09-27 08:00:00');
        CashSession::closeStale();

        $this->actingAs($other)
            ->post(route('cash-drawer.reconcile', $session), ['closing_amount' => 170000])
            ->assertForbidden();
    }
}
