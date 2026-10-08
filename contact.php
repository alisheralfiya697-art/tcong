<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function clean_string($value, int $max): string {
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}
function esc(string $value): string {
    return htmlspecialchars($value !== '' ? $value : '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'Method not allowed.']);

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$name = clean_string($data['name'] ?? '', 60);
$email = clean_string($data['email'] ?? '', 100);
$phone = clean_string($data['phone'] ?? '', 20);
$company = clean_string($data['company'] ?? '', 100);
$service = clean_string($data['service'] ?? '', 100);
$message = clean_string($data['message'] ?? '', 2000);
$source = clean_string($data['source'] ?? '', 80);
$conversation = clean_string($data['conversation'] ?? '', 2000);

$errors = [];
if (mb_strlen($name) < 2) $errors['name'] = 'Please provide your name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please provide a valid email address.';
if (mb_strlen($message) < 5) $errors['message'] = 'Please tell us a little about your enquiry (min 5 characters).';
if ($errors) respond(400, ['error' => 'Validation failed.', 'details' => $errors]);

$to = 'info@tcongsinfotech.com';
$from = 'website@tcongsinfotech.com';
$isAssistant = $source === 'AI Assistant';
$subject = $isAssistant ? 'New AI Assistant Lead' : 'New Website Enquiry from ' . $name;

$rows = [
    ['Source', $isAssistant ? 'AI Assistant' : 'Website'],
    ['Name', $name], ['Email', $email], ['Phone', $phone],
    ['Company', $company], ['Service / Topic', $service], ['Message', $message]
];
if ($conversation !== '') $rows[] = ['Conversation / Request', $conversation];

$html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937"><h2 style="color:#111827">' .
    esc($isAssistant ? 'New AI Assistant Lead' : 'Tcongs Infotech Website Enquiry') . '</h2>';
foreach ($rows as [$label, $value]) {
    $html .= '<p><strong>' . esc($label) . ':</strong><br>' . nl2br(esc($value)) . '</p>';
}
$html .= '</div>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Tcongs Website <' . $from . '>',
    'Reply-To: ' . str_replace(["\r", "\n"], '', $email),
];

if (!mail($to, $subject, $html, implode("\r\n", $headers))) {
    respond(500, ['error' => 'Failed to send your details. Please try again later.']);
}
respond(200, ['success' => true, 'message' => 'Your details have been sent successfully.']);
