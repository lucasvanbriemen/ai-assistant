<?php

namespace App\Http\Controllers;

use App\Models\VoiceCommand;
use App\Models\VoiceTranscript;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    public function addTranscript(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        VoiceTranscript::create($request->only([
            'text',
            'language',
            'confidence',
            'audio_duration_ms',
            'started_at',
        ]));

        return response()->json(['ok' => true]);
    }

    public function addCommand(Request $request)
    {
        $request->validate([
            'trigger_type' => 'required|string',
            'trigger_text' => 'required|string',
        ]);

        VoiceCommand::create([
            ...$request->only(['trigger_type', 'trigger_text', 'context_text']),
            'status' => 'pending',
        ]);

        return response()->json(['ok' => true]);
    }

    public function completeCommand(Request $request)
    {
        $request->validate([
            'trigger_text' => 'required|string',
            'response_text' => 'required|string',
        ]);

        VoiceCommand::where('trigger_text', $request->input('trigger_text'))
            ->where('status', 'pending')
            ->latest('id')
            ->first()
            ?->update([
                'response_text' => $request->input('response_text'),
                'status' => 'completed',
            ]);

        return response()->json(['ok' => true]);
    }
}
