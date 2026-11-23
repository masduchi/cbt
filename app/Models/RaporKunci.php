<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaporKunci extends Model
{
    protected $table = 'rapor_kunci';

    protected $primaryKey = 'id_kunci';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'locked_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
