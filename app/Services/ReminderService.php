<?php

namespace App\Services;

class ReminderService
{
    public const TOOLS = [
        // Ideas:
        // - Store reminders based on data of things that need to be done without a specific time, but that the user wants to be reminded of in the future. Example:
        //  User: Remind me to call Alice about the project update
        //  AI: [stores reminder: "call Alice about the project update"
        //  Set a reminder for a specific time:
        //  User: Remind me to call Bob tomorrow at 3pm
        //  AI: [stores reminder: "call Bob" with time "tomorrow at 3pm"]
        // Then we have a cron job that checks for upcoming reminders and sends them to the user at the appropriate time.
        // We could also have a tool that lists all upcoming reminders when the user asks.

        // TOOLS:
        // - store_reminder: Store a reminder with a title and optional time.
        // - retrieve_reminders: Retrieve all reminders.
        // - update_reminder: Update a reminder's title or time.
        // - delete_reminder: Delete a reminder.
    ];

    public const TOOL_FUNCTION_MAP = [];
}
