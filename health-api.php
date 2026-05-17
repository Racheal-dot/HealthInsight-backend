<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| CORS HEADERS
|--------------------------------------------------------------------------
*/
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, App-Id, App-Key");
header("Content-Type: application/json");

/*
|--------------------------------------------------------------------------
| HANDLE PREFLIGHT REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/*
|--------------------------------------------------------------------------
| GET INFERMEDICA CREDENTIALS FROM RAILWAY VARIABLES
|--------------------------------------------------------------------------
*/
$app_id  = getenv('APP_ID');
$app_key = getenv('APP_KEY');

if (!$app_id || !$app_key) {
    http_response_code(500);
    echo json_encode([
        "error" => "APP_ID or APP_KEY is not set in Railway environment variables."
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| READ INPUT DATA
|--------------------------------------------------------------------------
*/
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode([
        "error" => "No input data received"
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| SEND REQUEST TO INFERMEDICA
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

/* Force HTTP/1.1 to avoid HTTP/2 PROTOCOL_ERROR */
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

/* Additional reliability settings */
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

/*
|--------------------------------------------------------------------------
| HANDLE cURL ERRORS
|--------------------------------------------------------------------------
*/
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        "error" => curl_error($ch)
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| GET RESPONSE STATUS CODE
|--------------------------------------------------------------------------
*/
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

/*
|--------------------------------------------------------------------------
| RETURN INFERMEDICA RESPONSE
|--------------------------------------------------------------------------
*/
http_response_code($httpCode);
echo $response;
?>