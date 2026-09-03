<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $table = 'staff_attendances';

    protected $fillable = [
        'account_id',
        'date',
        'status',
        'check_in',
        'check_out',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Staff Employee account relationship.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * User who logged the attendance.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
