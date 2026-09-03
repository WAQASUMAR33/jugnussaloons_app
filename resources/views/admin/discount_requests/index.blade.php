@extends('layouts.material')

@section('title', 'Discount Approval Requests')

@section('content')
<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-amber-50 text-amber-600 border border-amber-200">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Service Discount Approval Requests</h1>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">Review and authorize service appointment discount requests (> 10% threshold).</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.discount-requests.index', ['status' => 'pending']) }}" 
               class="px-3.5 py-2 font-bold text-xs flex items-center gap-1.5 transition-colors {{ $status == 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                <span>Pending</span>
                <span class="px-1.5 py-0.2 bg-white/20 text-white font-extrabold text-[10px]">{{ $pendingCount }}</span>
            </a>

            <a href="{{ route('admin.discount-requests.index', ['status' => 'approved']) }}" 
               class="px-3.5 py-2 font-bold text-xs flex items-center gap-1.5 transition-colors {{ $status == 'approved' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                <span>Approved</span>
                <span class="px-1.5 py-0.2 bg-white/20 text-white font-extrabold text-[10px]">{{ $approvedCount }}</span>
            </a>

            <a href="{{ route('admin.discount-requests.index', ['status' => 'rejected']) }}" 
               class="px-3.5 py-2 font-bold text-xs flex items-center gap-1.5 transition-colors {{ $status == 'rejected' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                <span>Rejected</span>
                <span class="px-1.5 py-0.2 bg-white/20 text-white font-extrabold text-[10px]">{{ $rejectedCount }}</span>
            </a>

            <a href="{{ route('admin.discount-requests.index', ['status' => 'all']) }}" 
               class="px-3.5 py-2 font-bold text-xs flex items-center gap-1.5 transition-colors {{ $status == 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                <span>All</span>
            </a>
        </div>
    </div>

    <!-- Requests Table Card -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-4 px-4">Booking #</th>
                        <th class="py-4 px-4">Customer</th>
                        <th class="py-4 px-4">Stylist</th>
                        <th class="py-4 px-4">Gross Amount</th>
                        <th class="py-4 px-4">Requested Discount</th>
                        <th class="py-4 px-4">Discount %</th>
                        <th class="py-4 px-4">Requested By</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($requests as $req)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-4 px-4 font-black text-indigo-700">
                            #{{ $req->appointment ? $req->appointment->booking_no : 'N/A' }}
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-900">
                            {{ $req->appointment && $req->appointment->customer ? $req->appointment->customer->name : 'Client' }}
                        </td>
                        <td class="py-4 px-4 font-semibold text-purple-900">
                            {{ $req->appointment && $req->appointment->employee ? $req->appointment->employee->name : 'Staff' }}
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">
                            PKR {{ number_format($req->gross_amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-black text-amber-700">
                            PKR {{ number_format($req->discount_amount, 2) }}
                        </td>
                        <td class="py-4 px-4 font-black">
                            <span class="px-2 py-1 bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[11px]">
                                {{ $req->discount_percentage }}%
                            </span>
                        </td>
                        <td class="py-4 px-4 font-medium text-slate-600">
                            {{ $req->requester ? $req->requester->name : 'System User' }}
                            <span class="block text-[10px] text-slate-400">{{ $req->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="py-4 px-4">
                            @if($req->status == 'approved')
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold uppercase">Approved</span>
                            @elseif($req->status == 'rejected')
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-bold uppercase">Rejected</span>
                            @else
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold uppercase animate-pulse">Pending Approval</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-right">
                            @if($req->status == 'pending')
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.discount-requests.approve', $req) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs transition-all">
                                            ✓ Approve
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.discount-requests.reject', $req) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-2xs transition-all">
                                            ✕ Reject
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-[11px] text-slate-400 font-semibold">
                                    Processed by {{ $req->actionBy ? $req->actionBy->name : 'Admin' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-slate-400 font-semibold">
                            No discount approval requests found for status "{{ ucfirst($status) }}".
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
