<?php

namespace App\Services;

class MemoryService
{
    public static function listTools()
    {
        // Ideas:
        // - Store and retrieve memories based on user input and interactions vector based
        //  Example:
        //  User: My favorite color is blue
        //  AI: [stores memory: "favorite_color" => "blue"]
        //  User: Tells about a named Alice (new person)
        //  AI: Checks if it has a memory for "Alice", finds nothing, then stores person
        //  If unclear about what to store, ask the user for clarification:
        //  User: appretnly Al who loves hiking and cooking
        //  Is al a nick name for Alice? If so, should I link this new information to the existing memory about Alice?
        return [

        ];
    }
}
