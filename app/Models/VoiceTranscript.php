<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceTranscript extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'text',
        'language',
        'confidence',
        'audio_duration_ms',
        'started_at',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'audio_duration_ms' => 'integer',
            'started_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(VoiceSession::class, 'session_id');
    }
}
