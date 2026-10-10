<!-- Header / Brand Info -->
<div class="text-center space-y-1" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    @if(!empty($brandSettings->brand_logo))
        <div class="flex justify-center pb-1">
            <img src="{{ asset($brandSettings->brand_logo) }}" alt="Logo" class="h-20 max-h-20 w-auto object-contain mx-auto" style="max-height: 80px; max-width: 200px; display: inline-block;">
        </div>
    @endif
    <h2 class="text-base font-bold tracking-normal uppercase text-black leading-tight">{{ $brandSettings->brand_name ?? 'JUGNU SALOON' }}</h2>
    @if(!empty($brandSettings->brand_slogan))
        <p class="text-xs font-normal text-black uppercase tracking-tight leading-snug">{{ $brandSettings->brand_slogan }}</p>
    @endif
    @if(!empty($brandSettings->brand_address))
        <p class="text-xs font-normal text-black leading-snug">{{ $brandSettings->brand_address }}</p>
    @endif
    @if(!empty($brandSettings->brand_phone1) || !empty($brandSettings->brand_phone2))
        <p class="text-xs font-normal text-black">Tel: {{ $brandSettings->brand_phone1 }} {{ $brandSettings->brand_phone2 ? '/ ' . $brandSettings->brand_phone2 : '' }}</p>
    @endif
</div>

<div class="border-y border-black py-1 my-1.5 text-center font-semibold text-xs uppercase tracking-wider text-black" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    SERVICE BILL / CASH RECEIPT
</div>

<!-- Bill & Customer Metadata -->
<div class="text-xs space-y-1 pt-0.5 text-black font-normal" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    <div class="flex justify-between">
        <span class="text-black">Invoice #:</span>
        <span class="font-medium text-black" x-text="receiptData ? receiptData.billNo : '—'"></span>
    </div>
    <div class="flex justify-between">
        <span class="text-black">Date & Time:</span>
        <span class="text-black" x-text="receiptData ? (receiptData.billDate + ' ' + (receiptData.billTime || '')) : '—'"></span>
    </div>
    <div class="flex justify-between">
        <span class="text-black">Channel:</span>
        <span class="font-medium uppercase text-black" x-text="receiptData ? receiptData.orderType : 'On Site'"></span>
    </div>
    <div class="flex justify-between">
        <span class="text-black">Client:</span>
        <span class="font-medium text-black" x-text="receiptData ? receiptData.customerName : 'Walk-in Client'"></span>
    </div>
    <template x-if="receiptData && receiptData.customerPhone">
        <div class="flex justify-between">
            <span class="text-black">Phone:</span>
            <span class="text-black" x-text="receiptData.customerPhone"></span>
        </div>
    </template>
</div>

<!-- Items Table -->
<div class="border-t border-black pt-1.5 mt-1.5" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    <table class="w-full text-left text-xs text-black border-collapse">
        <thead>
            <tr class="border-b border-black font-semibold text-xs text-black">
                <th class="py-1 text-left font-semibold">ITEM / SERVICE</th>
                <th class="py-1 text-center w-7 font-semibold">QTY</th>
                <th class="py-1 text-right w-13 font-semibold">PRICE</th>
                <th class="py-1 text-right w-13 font-semibold">DISC</th>
                <th class="py-1 text-right w-15 font-semibold">TOTAL</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-black">
            <template x-for="(item, i) in (receiptData ? receiptData.items : [])" :key="i">
                <tr class="border-b border-black">
                    <td class="py-1 text-left font-normal text-black leading-snug">
                        <div x-text="item.title"></div>
                        <template x-if="item.discountText">
                            <div class="text-[10px] text-black font-normal" x-text="item.discountText"></div>
                        </template>
                    </td>
                    <td class="py-1 text-center font-normal text-black" x-text="item.quantity"></td>
                    <td class="py-1 text-right font-normal text-black" x-text="Number(item.price).toLocaleString()"></td>
                    <td class="py-1 text-right font-normal text-black" x-text="Number(item.discount || 0).toLocaleString()"></td>
                    <td class="py-1 text-right font-medium text-black" x-text="Number(item.total).toLocaleString()"></td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<!-- Calculations Summary -->
<div class="border-t border-black pt-1.5 mt-1 space-y-1 text-xs text-black font-normal" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    <div class="flex justify-between">
        <span>Subtotal (Gross):</span>
        <span class="font-medium text-black">PKR <span x-text="receiptData ? Number(receiptData.subtotal).toLocaleString() : '0'"></span></span>
    </div>

    <!-- If both Service Item Discount and Bill Discount exist -->
    <template x-if="receiptData && receiptData.serviceDiscount > 0 && receiptData.billDiscount > 0">
        <div class="space-y-1">
            <div class="flex justify-between text-black">
                <span>Service Discount (Items):</span>
                <span class="font-medium">- PKR <span x-text="Number(receiptData.serviceDiscount).toLocaleString()"></span></span>
            </div>
            <div class="flex justify-between text-black">
                <span>Bill Discount:</span>
                <span class="font-medium">- PKR <span x-text="Number(receiptData.billDiscount).toLocaleString()"></span></span>
            </div>
            <div class="flex justify-between text-black font-medium">
                <span>Total Service Discount:</span>
                <span class="font-medium">- PKR <span x-text="Number(receiptData.discount).toLocaleString()"></span></span>
            </div>
        </div>
    </template>

    <!-- If only one Discount exists -->
    <template x-if="receiptData && receiptData.discount > 0 && !(receiptData.serviceDiscount > 0 && receiptData.billDiscount > 0)">
        <div class="flex justify-between text-black">
            <span>Service Discount:</span>
            <span class="font-medium">- PKR <span x-text="Number(receiptData.discount).toLocaleString()"></span></span>
        </div>
    </template>

    <template x-if="receiptData && receiptData.extraAmount > 0">
        <div class="flex justify-between text-black">
            <span>Card / Bank Fee <span x-text="receiptData.extraPercentage > 0 ? '(' + receiptData.extraPercentage + '%)' : ''">:</span></span>
            <span class="font-medium">+ PKR <span x-text="Number(receiptData.extraAmount).toLocaleString()"></span></span>
        </div>
    </template>

    <div class="flex justify-between font-bold text-sm border-y border-black py-1.5 my-1.5 text-black">
        <span>TOTAL NET BILL:</span>
        <span>PKR <span x-text="receiptData ? Number(receiptData.netAmount).toLocaleString() : '0'"></span></span>
    </div>

    <div class="flex justify-between">
        <span class="text-black">Payment Mode:</span>
        <span class="font-medium uppercase text-black" x-text="receiptData ? (receiptData.paymentMode === 'Split' ? 'Split Payment' : receiptData.paymentMode) : 'Cash'"></span>
    </div>

    <!-- Split Payment Breakdown in Receipt -->
    <template x-if="receiptData && receiptData.paymentMode === 'Split' && receiptData.splitPayments">
        <div class="pl-2 space-y-0.5 text-[11px] text-black border-l-2 border-black/40 my-1 py-0.5">
            <template x-if="receiptData.splitPayments.Cash && parseFloat(receiptData.splitPayments.Cash) > 0">
                <div class="flex justify-between">
                    <span>• Cash:</span>
                    <span class="font-medium">PKR <span x-text="Number(receiptData.splitPayments.Cash).toLocaleString()"></span></span>
                </div>
            </template>
            <template x-if="receiptData.splitPayments.Card && parseFloat(receiptData.splitPayments.Card) > 0">
                <div class="flex justify-between">
                    <span>• Card:</span>
                    <span class="font-medium">PKR <span x-text="Number(receiptData.splitPayments.Card).toLocaleString()"></span></span>
                </div>
            </template>
            <template x-if="receiptData.splitPayments.Bank && parseFloat(receiptData.splitPayments.Bank) > 0">
                <div class="flex justify-between">
                    <span>• Bank Transfer:</span>
                    <span class="font-medium">PKR <span x-text="Number(receiptData.splitPayments.Bank).toLocaleString()"></span></span>
                </div>
            </template>
            <template x-if="receiptData.splitPayments.Other && parseFloat(receiptData.splitPayments.Other) > 0">
                <div class="flex justify-between">
                    <span>• Other:</span>
                    <span class="font-medium">PKR <span x-text="Number(receiptData.splitPayments.Other).toLocaleString()"></span></span>
                </div>
            </template>
        </div>
    </template>

    <div class="flex justify-between text-black">
        <span>Paid Amount:</span>
        <span class="font-medium">PKR <span x-text="receiptData ? Number(receiptData.paidAmount).toLocaleString() : '0'"></span></span>
    </div>

    <template x-if="receiptData && receiptData.balanceDue > 0">
        <div class="flex justify-between font-semibold text-black">
            <span>Balance Remaining:</span>
            <span>PKR <span x-text="Number(receiptData.balanceDue).toLocaleString()"></span> (DUE)</span>
        </div>
    </template>

    <template x-if="receiptData && receiptData.balanceDue <= 0">
        <div class="flex justify-between font-medium text-black">
            <span>Bill Status:</span>
            <span>PAID IN FULL (CLEARED)</span>
        </div>
    </template>

    <template x-if="receiptData && receiptData.changeReturn > 0">
        <div class="flex justify-between text-black">
            <span>Change Return:</span>
            <span class="font-medium">PKR <span x-text="Number(receiptData.changeReturn).toLocaleString()"></span></span>
        </div>
    </template>
</div>

<!-- Notes (if any) -->
<template x-if="receiptData && receiptData.notes">
    <div class="border-t border-black pt-1.5 mt-1 text-xs text-black font-normal leading-snug" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
        <span class="text-black font-semibold">Notes:</span> <span x-text="receiptData.notes"></span>
    </div>
</template>

<!-- Attached Online Payment Slip Notice -->
<template x-if="receiptData && receiptData.receiptImage">
    <div class="border-t border-black py-1.5 text-xs text-center font-medium text-black uppercase tracking-wider" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
        [&#10003; Online Payment Slip Attached]
    </div>
</template>

<!-- Footer -->
<div class="border-t border-black pt-2.5 mt-2 text-center text-xs space-y-1.5 text-black font-normal" style="font-family: Arial, 'Segoe UI', Roboto, 'Helvetica Neue', Helvetica, sans-serif;">
    <p class="font-semibold tracking-wider uppercase">*** THANK YOU FOR VISITING ***</p>
    @if(!empty($brandSettings->appointment_receipt_note))
        <p class="text-xs text-black font-normal whitespace-pre-line leading-relaxed text-center px-1">{{ $brandSettings->appointment_receipt_note }}</p>
    @else
        <p class="text-xs text-black font-normal">Please inspect your receipt before leaving.</p>
    @endif
    <div class="pt-1 text-xs text-black font-normal">
        Generated By: <span class="font-medium text-black" x-text="receiptData ? (receiptData.generatedBy || '{{ addslashes(auth()->user()->name ?? 'Staff') }}') : '{{ addslashes(auth()->user()->name ?? 'Staff') }}'">{{ auth()->user()->name ?? 'Staff' }}</span>
    </div>
    <p class="text-[11px] text-black font-normal pt-1">Powered by RAPIDTECHPRO &bull; {{ date('Y') }}</p>
</div>
