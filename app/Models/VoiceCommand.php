<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceCommand extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'trigger_type',
        'trigger_text',
        'context_text',
        'response_text',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(VoiceSession::class, 'session_id');
    }
}
