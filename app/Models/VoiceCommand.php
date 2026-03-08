<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceCommand extends Model
{
    public $timestamps = false;

    protected $fillable = [
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

}
