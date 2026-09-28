<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class CashSessionsAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewReports');

        $query = CashSession::query()->with(['openedBy', 'closedBy'])->orderByDesc('started_at');

        $status = $request->input('status');
        if ($status && in_array($status, ['open','closed','auto_closed'])) {
            $query->where('status', $status);
        }
        $userId = $request->input('user_id');
        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('opened_by', $userId)->orWhere('closed_by', $userId);
            });
        }
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) {
            $query->where('started_at', '>=', $from.' 00:00:00');
        }
        if ($to) {
            $query->where('started_at', '<=', $to.' 23:59:59');
        }

        // CSV export
        if ($request->boolean('export')) {
            $rows = $query->get();
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="cash-sessions.csv"',
            ];
            $callback = function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, [
                    'id','opened_by','closed_by','opening_amount','closing_amount','expected_cash_at_close','variance','started_at','ended_at','status','notes'
                ]);
                foreach ($rows as $s) {
                    fputcsv($out, [
                        $s->id,
                        optional($s->openedBy)->name.' (#'.$s->opened_by.')',
                        optional($s->closedBy)->name.' (#'.$s->closed_by.')',
                        number_format((float) $s->opening_amount, 2, '.', ''),
                        $s->closing_amount === null ? null : number_format((float) $s->closing_amount, 2, '.', ''),
                        $s->expected_cash_at_close === null ? null : number_format((float) $s->expected_cash_at_close, 2, '.', ''),
                        $s->variance === null ? null : number_format((float) $s->variance, 2, '.', ''),
                        optional($s->started_at)->format('Y-m-d H:i:s'),
                        optional($s->ended_at)->format('Y-m-d H:i:s'),
                        $s->status,
                        $s->notes,
                    ]);
                }
                fclose($out);
            };
            return Response::stream($callback, 200, $headers);
        }

        $sessions = $query->paginate(20)->withQueryString();
        $users = User::orderBy('name')->get(['id','name']);

        return Inertia::render('CashDrawer/Sessions', [
            'filters' => [
                'status' => $status,
                'user_id' => $userId,
                'from' => $from,
                'to' => $to,
            ],
            'sessions' => $sessions,
            'users' => $users,
        ]);
    }
}
