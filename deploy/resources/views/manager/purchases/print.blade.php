@extends('layouts.material')

@section('title', 'Purchase Bill Report - ' . $purchase->invoice_no)

@section('content')
<div class="space-y-4">

    <!-- Top Action & Navigation Bar (WEB ONLY) -->
    <div class="bg-white border border-slate-200 shadow-sm p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('manager.purchases.index') }}" 
               class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Back to Purchases</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 leading-tight">Purchase Bill #{{ $purchase->invoice_no }}</h1>
                <p class="text-[11px] text-slate-500 font-semibold">Official Supplier Goods Receiving Voucher</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Official Report</span>
            </button>
        </div>
    </div>

    <!-- CRYSTAL REPORTS OFFICIAL DOCUMENT (Visible in Web Preview & Print) -->
    <div class="bg-white border border-slate-200 shadow-sm p-6 sm:p-8 crystal-container print:p-0 print:border-none print:shadow-none">
        
        <!-- Top Company Brand Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if(isset($appSetting) && $appSetting->brand_logo)
                    <img src="{{ asset($appSetting->brand_logo) }}" class="h-12 w-auto object-contain">
                @endif
                <div>
                    <h1 class="text-xl font-black uppercase text-slate-900 tracking-tight">{{ $appSetting->brand_name ?? 'Jugnu Saloon' }}</h1>
                    @if(isset($appSetting) && $appSetting->brand_slogan)
                        <p class="text-[10px] font-bold text-slate-600 italic leading-none">{{ $appSetting->brand_slogan }}</p>
                    @endif
                    @if(isset($appSetting) && $appSetting->brand_address)
                        <p class="text-[9px] text-slate-500 mt-0.5 leading-tight">{{ $appSetting->brand_address }}</p>
                    @endif
                    @if(isset($appSetting) && ($appSetting->brand_phone1 || $appSetting->brand_phone2))
                        <p class="text-[9px] font-bold text-slate-700 mt-0.5">
                            Tel: {{ implode(' | ', array_filter([$appSetting->brand_phone1, $appSetting->brand_phone2])) }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="text-right text-[8.5pt]">
                <div class="font-bold text-slate-800 uppercase tracking-wide">Procurement & Inventory</div>
                <div class="text-[7.5pt] text-slate-500">Official Purchase Bill & Goods Inward Note</div>
                <div class="text-[7.5pt] font-semibold text-slate-600 mt-0.5">Doc ID: {{ $purchase->invoice_no }}</div>
            </div>
        </div>

        <!-- Double Divider Rule -->
        <div class="crystal-double-rule"></div>

        <!-- Title Banner -->
        <div class="crystal-title-banner">
            <h2>GOODS PURCHASE INVOICE & RECEIVING REPORT</h2>
            <p>Official Vendor Accounts Payable & Stock Inward Record</p>
        </div>

        <!-- Crystal Reports Parameters Matrix -->
        <table class="crystal-param-grid">
            <tr>
                <td class="label">Invoice No:</td>
                <td class="val font-mono font-bold text-slate-900">{{ $purchase->invoice_no }}</td>
                <td class="label">Purchase Date:</td>
                <td class="val font-mono">{{ $purchase->purchase_date->format('d-M-Y') }}</td>
            </tr>
            <tr>
                <td class="label">Supplier Account:</td>
                <td class="val font-bold">
                    {{ $purchase->supplier->name ?? 'N/A' }}
                    @if($purchase->supplier && $purchase->supplier->category)
                        <span class="text-[8.5pt] font-normal text-slate-600">({{ $purchase->supplier->category->title }})</span>
                    @endif
                </td>
                <td class="label">Supplier Tel:</td>
                <td class="val font-mono">{{ $purchase->supplier->phone_no1 ?: ($purchase->supplier->phone_no2 ?: '—') }}</td>
            </tr>
            <tr>
                <td class="label">Main Category:</td>
                <td class="val font-bold text-slate-800">
                    🏛️ {{ $purchase->mainCategory ? $purchase->mainCategory->title : 'General Category' }}
                </td>
                <td class="label">Payment Status:</td>
                <td class="val">
                    @if($purchase->balance_due <= 0)
                        <span class="font-bold text-emerald-800 uppercase">PAID / SETTLED IN FULL</span>
                    @elseif($purchase->paid_amount > 0)
                        <span class="font-bold text-amber-800 uppercase">PARTIALLY PAID</span>
                    @else
                        <span class="font-bold text-rose-800 uppercase">UNPAID / OUTSTANDING</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Gross Bill Amount:</td>
                <td class="val font-mono font-bold">PKR {{ number_format($purchase->total_amount, 2) }}</td>
                <td class="label">Amount Paid:</td>
                <td class="val font-mono font-bold text-emerald-800">PKR {{ number_format($purchase->paid_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Balance Due / Payable:</td>
                <td class="val font-mono font-bold {{ $purchase->balance_due > 0 ? 'text-rose-800' : 'text-slate-800' }}">
                    PKR {{ number_format($purchase->balance_due, 2) }}
                </td>
                <td class="label">Generated By:</td>
                <td class="val">{{ Auth::user()->name }} | {{ now()->format('d-M-Y h:i A') }}</td>
            </tr>
            @if($purchase->notes)
            <tr>
                <td class="label">Notes / Remarks:</td>
                <td colspan="3" class="val italic text-slate-700">{{ $purchase->notes }}</td>
            </tr>
            @endif
        </table>

        <!-- Purchase Line Items Table -->
        <div class="mt-4">
            <table class="crystal-grid">
                <thead>
                    <tr>
                        <th class="w-10 text-center">Sr #</th>
                        <th class="text-left">Product / Item Description</th>
                        <th class="text-left w-36">Category</th>
                        <th class="text-center w-20">Received Qty</th>
                        <th class="text-right w-28">Purchase Rate</th>
                        <th class="text-right w-28">Retail Sale Rate</th>
                        <th class="text-right w-32">Subtotal Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $totalQty = 0; 
                    @endphp
                    @forelse($purchase->items as $index => $item)
                    @php 
                        $totalQty += (int) $item->quantity; 
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $index + 1 }}</td>
                        <td class="font-bold text-slate-900">
                            {{ $item->product ? $item->product->title : 'Unknown Product' }}
                        </td>
                        <td class="text-slate-600">
                            {{ $item->product && $item->product->category ? $item->product->category->name : ($item->product && $item->product->category ? $item->product->category->title : 'General') }}
                        </td>
                        <td class="text-center font-bold font-mono text-slate-900">
                            {{ $item->quantity }}
                        </td>
                        <td class="text-right font-mono">
                            PKR {{ number_format($item->unit_price, 2) }}
                        </td>
                        <td class="text-right font-mono text-slate-700">
                            PKR {{ number_format($item->product ? $item->product->price : 0, 2) }}
                        </td>
                        <td class="text-right font-mono font-bold text-slate-900">
                            PKR {{ number_format($item->subtotal, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-6 text-slate-400 italic">No line items attached to this purchase invoice.</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right uppercase font-black">Total Quantity & Bill Summary:</td>
                        <td class="text-center font-mono font-black">{{ $totalQty }} units</td>
                        <td colspan="2" class="text-right uppercase font-bold text-slate-600">Grand Total:</td>
                        <td class="text-right font-mono font-black">PKR {{ number_format($purchase->total_amount, 2) }}</td>
                    </tr>
                    @if($purchase->paid_amount > 0)
                    <tr>
                        <td colspan="6" class="text-right uppercase font-bold text-emerald-800">Total Amount Paid:</td>
                        <td class="text-right font-mono font-black text-emerald-800">PKR {{ number_format($purchase->paid_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="6" class="text-right uppercase font-bold {{ $purchase->balance_due > 0 ? 'text-rose-800' : 'text-slate-800' }}">Net Outstanding Balance Due:</td>
                        <td class="text-right font-mono font-black {{ $purchase->balance_due > 0 ? 'text-rose-800' : 'text-slate-800' }}">PKR {{ number_format($purchase->balance_due, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Official Signatures Section -->
        <div class="crystal-sign-band">
            <div class="crystal-sign-box">
                <div>Received By</div>
                <div class="text-[7pt] text-slate-500 font-normal">Store Incharge / Keeper</div>
            </div>
            <div class="crystal-sign-box">
                <div>Audited & Verified By</div>
                <div class="text-[7pt] text-slate-500 font-normal">Internal Audit / Accounts</div>
            </div>
            <div class="crystal-sign-box">
                <div>Supplier / Vendor Sign</div>
                <div class="text-[7pt] text-slate-500 font-normal">Delivery Representative</div>
            </div>
            <div class="crystal-sign-box">
                <div>Authorized Signature</div>
                <div class="text-[7pt] text-slate-500 font-normal">General Manager / Owner</div>
            </div>
        </div>

        <!-- Audit Footer -->
        <div class="crystal-audit-footer">
            <span>System Generated Official Purchase Bill • Verification Ref: {{ $purchase->invoice_no }}</span>
            <span>Printed by {{ Auth::user()->name }} on {{ now()->format('d-M-Y h:i:s A') }}</span>
        </div>

    </div>

</div>
@endsection
