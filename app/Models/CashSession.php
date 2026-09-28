<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CashSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'opened_by',
        'closed_by',
        'opening_amount',
        'closing_amount',
        'expected_cash_at_close',
        'variance',
        'started_at',
        'ended_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_cash_at_close' => 'decimal:2',
        'variance' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_AUTO_CLOSED = 'auto_closed';

    /**
     * The user's open session for the current business day. A session left
     * open from a previous business day is auto-closed instead of returned,
     * so new payments never land in yesterday's drawer.
     */
    public static function activeForUser(int $userId): ?self
    {
        $session = static::where('opened_by', $userId)
            ->where('status', self::STATUS_OPEN)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        if ($session && $session->started_at->lt(static::businessDayStart())) {
            $session->autoClose();
            return null;
        }

        return $session;
    }

    /**
     * Start of the business day containing $at (config clinic.cash_day_starts_at).
     */
    public static function businessDayStart(?CarbonInterface $at = null): Carbon
    {
        $at = Carbon::instance($at ?? now());
        [$hour, $minute] = array_map('intval', explode(':', (string) config('clinic.cash_day_starts_at', '00:00')) + [0, 0]);

        $start = $at->copy()->setTime($hour, $minute);
        if ($at->lt($start)) {
            $start->subDay();
        }

        return $start;
    }

    /**
     * Auto-close every open session that started before the current business day.
     */
    public static function closeStale(): int
    {
        $sessions = static::where('status', self::STATUS_OPEN)
            ->whereNull('ended_at')
            ->where('started_at', '<', static::businessDayStart())
            ->get();

        $sessions->each->autoClose();

        return $sessions->count();
    }

    /**
     * Expected cash in the drawer: opening float plus cash in, minus cash out.
     */
    public function expectedCash(): float
    {
        $cashIn = $this->movements()->where('type', 'inflow')->where('method', 'cash')->sum('amount');
        $cashOut = $this->movements()->where('type', 'outflow')->where('method', 'cash')->sum('amount');

        return (float) $this->opening_amount + (float) $cashIn - (float) $cashOut;
    }

    /**
     * Close the session at the end of its business day without a cash count.
     * The counted amount and variance stay empty until someone reconciles it.
     */
    public function autoClose(): void
    {
        $endedAt = static::businessDayStart($this->started_at)->addDay()->subSecond();
        if ($endedAt->gt(now())) {
            $endedAt = now();
        }

        $this->update([
            'expected_cash_at_close' => $this->expectedCash(),
            'ended_at' => $endedAt,
            'status' => self::STATUS_AUTO_CLOSED,
        ]);

        try {
            AuditLog::create([
                'user_id' => null,
                'action' => 'cash_session.auto_close',
                'subject_type' => self::class,
                'subject_id' => $this->id,
                'metadata' => ['expected_cash_at_close' => (float) $this->expected_cash_at_close],
            ]);
        } catch (\Throwable $e) {}
    }

    public function needsReconciliation(): bool
    {
        return $this->status === self::STATUS_AUTO_CLOSED;
    }
}
