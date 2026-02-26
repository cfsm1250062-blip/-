<?php
require_once "functions.php";
require_once "config.php";

session_start();
requireLogin();
ensureToken();
checkTokenHeader();

header("Content-Type: application/json; charset=UTF-8");

$raw = file_get_contents("php://input");
$body = json_decode($raw, true);

$prompt = trim($body["prompt"] ?? "");
if ($prompt === "") {
    http_response_code(400);
    echo json_encode(["error" => "empty_prompt"]);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . GEMINI_API_KEY;

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($data),
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "gemini_failed"]);
    exit;
}

echo $result;
