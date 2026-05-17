<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, App-Id, App-Key");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/*
|--------------------------------------------------------------------------
| LOAD API KEYS
|--------------------------------------------------------------------------
*/
$app_id  = getenv('APP_ID');
$app_key = getenv('APP_KEY');

if (!$app_id || !$app_key) {
    http_response_code(500);
    echo json_encode(["error" => "Missing API credentials"]);
    exit();
}

/*
|--------------------------------------------------------------------------
| READ JSON INPUT
|--------------------------------------------------------------------------
*/
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input"]);
    exit();
}

/*
|--------------------------------------------------------------------------
| DISCLAIMER CHECK (IMPORTANT FIX)
|--------------------------------------------------------------------------
| Accept both true (boolean) and "1" (string)
|--------------------------------------------------------------------------
*/
if (
    !isset($input['agreeDisclaimer']) ||
    $input['agreeDisclaimer'] != true
) {
    http_response_code(400);
    echo json_encode([
        "error" => "You must agree to the medical disclaimer before continuing."
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| REMOVE FRONTEND FIELD
|--------------------------------------------------------------------------
*/
unset($input['agreeDisclaimer']);

/*
|--------------------------------------------------------------------------
| CALL INFERMEDICA API
|--------------------------------------------------------------------------
*/
$ch = curl_init("https://api.infermedica.com/v3/diagnosis");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "App-Id: $app_id",
    "App-Key: $app_key",
    "Content-Type: application/json",
    "Accept: application/json",
    "Model: infermedica-en"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(["error" => curl_error($ch)]);
    curl_close($ch);
    exit();
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;