<?php

namespace App\Services;

class ReminderService
{
    public static function listTools()
    {
        // Ideas:
        // - Store reminders based on data of things that need to be done without a specific time, but that the user wants to be reminded of in the future. Example:
        //  User: Remind me to call Alice about the project update
        //  AI: [stores reminder: "call Alice about the project update"
        //  Set a reminder for a specific time:
        //  User: Remind me to call Bob tomorrow at 3pm
        //  AI: [stores reminder: "call Bob" with time "tomorrow at 3pm"]
        // Then we have a cron job that checks for upcoming reminders and sends them to the user at the appropriate time. 
        // We could also have a tool that lists all upcoming reminders when the user asks.
        return [

        ];
    }
}
