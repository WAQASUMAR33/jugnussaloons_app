<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountLedger;
use App\Models\Appointment;
use App\Models\DiscountRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountApprovalController extends Controller
{
    /**
     * Display a listing of discount approval requests.
     */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = DiscountRequest::with(['appointment.customer', 'appointment.employee', 'requester', 'actionBy']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $pendingCount = DiscountRequest::where('status', 'pending')->count();
        $approvedCount = DiscountRequest::where('status', 'approved')->count();
        $rejectedCount = DiscountRequest::where('status', 'rejected')->count();

        return view('admin.discount_requests.index', compact('requests', 'status', 'pendingCount', 'approvedCount', 'rejectedCount'));
    }

    /**
     * Approve a high-discount request (>10%).
     */
    public function approve(Request $request, DiscountRequest $discountRequest)
    {
        DB::transaction(function () use ($discountRequest, $request) {
            $discountRequest->update([
                'status' => 'approved',
                'action_by_user_id' => auth()->id(),
                'notes' => $request->input('notes', 'Approved by Admin'),
            ]);

            $appointment = $discountRequest->appointment;
            if ($appointment) {
                // Discount is approved: calculate net bill with requested discount applied
                $grossTotal = (float) $appointment->total_amount;
                $billDiscount = (float) $discountRequest->discount_amount;
                $extraAmount = ($appointment->payment_mode === 'Bank') ? (float) ($appointment->extra_amount ?? 0) : 0.00;
                $paidAmount = (float) $appointment->paid_amount;

                $netAmount = max(0, round($grossTotal - $billDiscount + $extraAmount, 2));
                $balanceDue = round($netAmount - $paidAmount, 2);

                $appointment->update([
                    'discount' => $billDiscount,
                    'discount_percentage' => $discountRequest->discount_percentage,
                    'discount_status' => 'approved',
                    'discount_approved_by' => auth()->id(),
                    'net_amount' => $netAmount,
                    'balance_due' => $balanceDue,
                ]);

                // Synchronize customer & employee ledgers
                self::synchronizeAppointmentLedgers($appointment);
            }
        });

        return redirect()->back()->with('success', 'Discount request approved successfully! Discount applied and account ledgers updated.');
    }

    /**
     * Reject a high-discount request (>10%).
     */
    public function reject(Request $request, DiscountRequest $discountRequest)
    {
        DB::transaction(function () use ($discountRequest, $request) {
            $discountRequest->update([
                'status' => 'rejected',
                'action_by_user_id' => auth()->id(),
                'notes' => $request->input('notes', 'Rejected by Admin'),
            ]);

            $appointment = $discountRequest->appointment;
            if ($appointment) {
                // Discount is rejected: reset discount to 0.00
                $grossTotal = (float) $appointment->total_amount;
                $extraAmount = ($appointment->payment_mode === 'Bank') ? (float) ($appointment->extra_amount ?? 0) : 0.00;
                $paidAmount = (float) $appointment->paid_amount;

                $netAmount = max(0, round($grossTotal + $extraAmount, 2));
                $balanceDue = round($netAmount - $paidAmount, 2);

                $appointment->update([
                    'discount' => 0.00,
                    'discount_percentage' => 0.00,
                    'discount_status' => 'rejected',
                    'discount_approved_by' => auth()->id(),
                    'net_amount' => $netAmount,
                    'balance_due' => $balanceDue,
                ]);

                // Synchronize customer & employee ledgers
                self::synchronizeAppointmentLedgers($appointment);
            }
        });

        return redirect()->back()->with('warning', 'Discount request rejected. Booking net bill updated without discount.');
    }

    /**
     * Synchronize ledgers for an appointment after status or discount approval changes.
     */
    public static function synchronizeAppointmentLedgers(Appointment $appointment)
    {
        $bookingNo = $appointment->booking_no;
        $customer = Account::find($appointment->account_id);
        $employee = Account::find($appointment->employee_id);

        if (!$customer) {
            return;
        }

        // 1. Delete previous ledgers for this booking reference
        AccountLedger::where('reference_no', 'like', "%{$bookingNo}%")->delete();

        // Recalculate customer balance from remaining ledgers
        $lastCustLedger = AccountLedger::where('account_id', $customer->id)->orderBy('id', 'desc')->first();
        $baseCustBalance = $lastCustLedger ? (float) $lastCustLedger->running_balance : 0.00;

        $netAmount = (float) $appointment->net_amount;
        $paidAmount = (float) $appointment->paid_amount;
        $totalCommission = (float) $appointment->total_commission;
        $appointmentDate = $appointment->appointment_date ? (is_string($appointment->appointment_date) ? $appointment->appointment_date : $appointment->appointment_date->format('Y-m-d')) : date('Y-m-d');
        $employeeName = $employee ? $employee->name : 'Stylist';

        // 2. Create customer sale ledger entry
        $newCustBalance = $baseCustBalance + $netAmount - $paidAmount;

        AccountLedger::create([
            'account_id' => $customer->id,
            'date' => $appointmentDate,
            'type' => 'sale',
            'reference_no' => $bookingNo,
            'description' => "Saloon Service Booking #{$bookingNo} (Stylist: {$employeeName})",
            'debit' => $netAmount,
            'credit' => 0.00,
            'running_balance' => $baseCustBalance + $netAmount,
        ]);

        if ($paidAmount > 0) {
            AccountLedger::create([
                'account_id' => $customer->id,
                'date' => $appointmentDate,
                'type' => 'receiving',
                'reference_no' => $bookingNo . '-REC',
                'description' => "Payment received for Service Booking #{$bookingNo}",
                'debit' => 0.00,
                'credit' => $paidAmount,
                'running_balance' => $newCustBalance,
            ]);
        }

        $customer->update(['balance' => $newCustBalance]);

        // 3. Create employee commission ledger entry
        if ($employee && $totalCommission > 0) {
            $lastEmpLedger = AccountLedger::where('account_id', $employee->id)->orderBy('id', 'desc')->first();
            $baseEmpBalance = $lastEmpLedger ? (float) $lastEmpLedger->running_balance : 0.00;
            $newEmpBalance = $baseEmpBalance + $totalCommission;

            AccountLedger::create([
                'account_id' => $employee->id,
                'date' => $appointmentDate,
                'type' => 'commission',
                'reference_no' => $bookingNo . '-COMM',
                'description' => "Stylist Commission for Service Booking #{$bookingNo} (Client: {$customer->name})",
                'debit' => 0.00,
                'credit' => $totalCommission,
                'running_balance' => $newEmpBalance,
            ]);

            $employee->update(['balance' => $newEmpBalance]);
        }
    }
}
