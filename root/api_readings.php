<?php
require_once "functions.php";
session_start();

$action = $_GET["action"] ?? "";

requireLogin();
ensureToken();
checkTokenHeader(); // Fetch は header 前提

$userId = (int)($_SESSION["id"] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "no_user"], JSON_UNESCAPED_UNICODE);
    exit;
}

function bad($msg, $code = 400) {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === "list") {
    header("Content-Type: application/json; charset=UTF-8");
    $items = loadReadings($userId);
    usort($items, fn($a,$b) => (int)($a["timestamp"] ?? 0) <=> (int)($b["timestamp"] ?? 0));
    echo json_encode(["items" => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === "export_csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=readings.csv");

    $items = loadReadings($userId);
    usort($items, fn($a,$b) => (int)($a["timestamp"] ?? 0) <=> (int)($b["timestamp"] ?? 0));

    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF"); // Excel対策
    fputcsv($out, ["timestamp_ms", "reading"]);
    foreach ($items as $it) {
        fputcsv($out, [(string)($it["timestamp"] ?? ""), (string)($it["reading"] ?? "")]);
    }
    fclose($out);
    exit;
}

$raw = file_get_contents("php://input");
$body = $raw ? json_decode($raw, true) : [];
if (!is_array($body)) $body = [];

if ($action === "add") {
    $reading = isset($body["reading"]) ? (string)$body["reading"] : "";
    if (!preg_match('/^\d{1,10}$/', $reading)) bad("invalid_reading");

    $ts = (int)round(microtime(true) * 1000);
    $items = loadReadings($userId);
    $items[] = ["timestamp" => $ts, "reading" => $reading];
    saveReadings($userId, $items);

    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["ok" => true, "timestamp" => $ts], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === "delete") {
    $ts = isset($body["timestamp"]) ? (int)$body["timestamp"] : 0;
    if ($ts <= 0) bad("invalid_timestamp");

    $items = loadReadings($userId);
    $items = array_values(array_filter($items, fn($x) => (int)($x["timestamp"] ?? 0) !== $ts));
    saveReadings($userId, $items);

    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === "clear") {
    saveReadings($userId, []);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
    exit;
}

bad("unknown_action");
