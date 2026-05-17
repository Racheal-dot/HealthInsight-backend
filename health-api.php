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

$app_id = getenv('APP_ID');
$app_key = getenv('APP_KEY');

if (!$app_id || !$app_key) {
    http_response_code(500);
    echo json_encode([
        'error' => 'APP_ID or APP_KEY is not set in Railway environment variables.'
    ]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No input data received.'
    ]);
    exit();
}

$agree = filter_var($input['agreeDisclaimer'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$agree) {
    http_response_code(400);
    echo json_encode([
        'error' => 'You must agree to the medical disclaimer before continuing.'
    ]);
    exit();
}

unset($input['agreeDisclaimer']);

function callInfermedica($url, $payload, $app_id, $app_key)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "App-Id: $app_id",
        "App-Key: $app_key",
        "Content-Type: application/json",
        "Accept: application/json",
        "Model: infermedica-en"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'error' => $error,
            'status' => 500
        ];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'body' => json_decode($response, true),
        'status' => $status
    ];
}

$diagnosisResponse = callInfermedica(
    'https://api.infermedica.com/v3/diagnosis',
    $input,
    $app_id,
    $app_key   
);

if (isset($diagnosisResponse['error'])) {
    http_response_code(500);
    echo json_encode([
        'error' => $diagnosisResponse['error']
    ]);
    exit();
}

if ($diagnosisResponse['status'] >= 400) {
    http_response_code($diagnosisResponse['status']);
    echo json_encode($diagnosisResponse['body']);
    exit();
}

$triageResponse = callInfermedica(
    'https://api.infermedica.com/v3/triage',
    $input,
    $app_id,
    $app_key
);

$triage = null;

if (
    !isset($triageResponse['error']) &&
    isset($triageResponse['status']) &&
    $triageResponse['status'] < 400
) {
    $triage = $triageResponse['body'];
}

http_response_code(200);

echo json_encode([
    'diagnosis' => $diagnosisResponse['body'],
    'triage' => $triage
]);