<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../auth.php';

header('Content-Type: application/json');

// Ensure user is logged in and is admin/manager
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$command = $input['command'] ?? '';

if (empty($command)) {
    echo json_encode(['success' => false, 'error' => 'No command provided']);
    exit;
}

// System Prompt for the AI Boss
$system_instruction = "You are the 'Smart Manager Boss' of a Freelancer CRM. 
Your job is to parse natural language commands and suggest database actions.
You MUST respond in a valid JSON format with the following keys:
- 'reply': A friendly human response.
- 'action': (Optional) The type of action ('create_task', 'notify_user', 'analyze').
- 'data': (Optional) Structured data for the action (e.g., project_id, title, assigned_to).
- 'requires_confirm': (Boolean) Always true for actions that modify data.

Example: If user says 'Assign task fix bug to Hamza for project 5', you respond:
{
  \"reply\": \"Sure, I'll prepare a task for Hamza on Project #5.\",
  \"action\": \"create_task\",
  \"data\": { \"project_id\": 5, \"title\": \"Fix bug\", \"assigned_to\": \"Hamza\" },
  \"requires_confirm\": true
}";

$prompt = $system_instruction . "\n\nUser Command: " . $command;

$result = AI_Service::call($prompt, 'ChatBoss');

if ($result['success']) {
    // Attempt to parse JSON from AI content
    $content = $result['content'];
    // Clean up potential markdown formatting from AI
    $clean_json = preg_replace('/```json\n?|\n?```/', '', $content);
    $parsed = json_decode($clean_json, true);

    if ($parsed) {
        echo json_encode(['success' => true, 'response' => $parsed]);
    } else {
        // Fallback for non-structured responses
        echo json_encode(['success' => true, 'response' => ['reply' => $content, 'action' => null]]);
    }
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
