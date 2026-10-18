<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owner earnings: overview, payout history, bank account, withdraw. */
class EarningsController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /** ?period=this_month|last_month|this_year|all */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'this_month');
        [$from, $to] = match ($period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'all' => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $base = Payout::where('owner_id', $user->id)->where('status', '!=', Payout::CANCELLED);
        $inPeriod = (clone $base)->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]));

        $available = (clone $base)->where('status', Payout::AVAILABLE)->where('available_at', '<=', now());

        return response()->json([
            'currency' => config('neighbours.currency'),
            'period' => $period,
            'available_balance' => round((float) (clone $available)->sum('amount'), 2),
            'pending_balance' => round((float) (clone $base)->where(fn ($q) => $q->where('status', Payout::PENDING)
                ->orWhere(fn ($w) => $w->where('status', Payout::AVAILABLE)->where('available_at', '>', now())))->sum('amount'), 2),
            'pending_bookings' => (clone $base)->where('status', Payout::PENDING)->count(),
            'total_earnings' => round((float) (clone $inPeriod)->sum('amount'), 2),
            'completed_bookings' => (clone $inPeriod)->whereIn('status', [Payout::AVAILABLE, Payout::PAID])->count(),
            'upcoming_bookings' => (clone $inPeriod)->where('status', Payout::PENDING)->count(),
            'paid_total' => round((float) (clone $base)->where('status', Payout::PAID)->sum('amount'), 2),
            'payout_account' => $user->payoutAccount ? [
                'account_holder' => $user->payoutAccount->account_holder,
                'institution_number' => $user->payoutAccount->institution_number,
                'account_last4' => $user->payoutAccount->account_last4,
            ] : null,
            'recent_payouts' => PayoutResource::collection(
                (clone $base)->with('booking.item')->latest()->limit(10)->get()
            ),
        ]);
    }

    public function payouts(Request $request)
    {
        return PayoutResource::collection(
            Payout::where('owner_id', $request->user()->id)->with('booking.item')
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate((int) $request->input('per_page', 20))
        );
    }

    /** "Set up payouts": Canadian bank account (institution 3 digits, transit 5 digits). */
    public function savePayoutAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_holder' => ['required', 'string', 'max:120'],
            'institution_number' => ['required', 'digits:3'],
            'transit_number' => ['required', 'digits:5'],
            'account_number' => ['required', 'regex:/^\d{7,12}$/'],
        ]);

        $request->user()->payoutAccount()->updateOrCreate([], [
            ...$data,
            'account_last4' => substr($data['account_number'], -4),
        ]);

        return response()->json(['message' => 'Payout account saved.', 'account_last4' => substr($data['account_number'], -4)]);
    }

    /** Withdraw everything that's available. Real bank transfer would be triggered here (e.g. Stripe Connect). */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->payoutAccount) {
            throw ValidationException::withMessages(['payout_account' => 'Add your bank account first.']);
        }

        $result = DB::transaction(function () use ($user) {
            $payouts = Payout::where('owner_id', $user->id)->where('status', Payout::AVAILABLE)
                ->where('available_at', '<=', now())->lockForUpdate()->get();

            if ($payouts->isEmpty()) {
                throw ValidationException::withMessages(['amount' => 'You have no available balance to withdraw.']);
            }

            $reference = 'PO-'.strtoupper(Str::random(10));
            Payout::whereIn('id', $payouts->pluck('id'))->update(['status' => Payout::PAID, 'paid_at' => now(), 'reference' => $reference]);

            return ['amount' => round((float) $payouts->sum('amount'), 2), 'reference' => $reference, 'count' => $payouts->count()];
        });

        $this->notifications->send($user, 'payout_sent', 'updates', 'Payout on its way',
            'C$'.number_format($result['amount'], 2)." is being sent to your account ending {$user->payoutAccount->account_last4}.", $result);

        return response()->json(['message' => 'Withdrawal started.', ...$result]);
    }
}
