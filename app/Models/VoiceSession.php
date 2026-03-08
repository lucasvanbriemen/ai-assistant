<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceSession extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function transcripts()
    {
        return $this->hasMany(VoiceTranscript::class, 'session_id');
    }

    public function commands()
    {
        return $this->hasMany(VoiceCommand::class, 'session_id');
    }
}
