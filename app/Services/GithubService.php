<?php

namespace App\Services;

class GithubService
{
    public const TOOLS = [
        // Ideas:
        // Get a list of things i can work on based on events
        // Example:
        // User: "What are issues and prs that i can work on (in webinargeek) right now"

        // TOOLS:
        // - list_issues_and_prs: List issues and pull requests that are open and unassigned in the repositories the user has access to, with filters for labels, milestones, and assignees.
        // - get_issue_or_pr_details: Get detailed information about a specific issue or pull (including comments, commits, and related issues/prs) to help the user understand the context and what needs to be done.
        // - Manage code using a remote coding server (maybe invoke a sub agent?)
        // - create_issue: Create a new issue in a specified repository with a title, description, and optional labels and assignees.
        // - update_issue_or_pr: Update the title, description, labels, or assigne
    ];

    public const TOOL_FUNCTION_MAP = [];
}
