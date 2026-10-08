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

$name = clean_string($_POST['name'] ?? '', 60);
$email = clean_string($_POST['email'] ?? '', 100);
$phone = clean_string($_POST['phone'] ?? '', 20);
$position = clean_string($_POST['position'] ?? '', 100);
$message = clean_string($_POST['message'] ?? '', 2000);

if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    respond(400, ['error' => 'Validation failed.', 'details' => ['resume' => 'Please attach your resume.']]);
}
$file = $_FILES['resume'];
if ((int)$file['size'] > 5 * 1024 * 1024) respond(400, ['error' => 'Validation failed.', 'details' => ['resume' => 'Resume must be under 5 MB.']]);

$filename = basename((string)$file['name']);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
    respond(400, ['error' => 'Validation failed.', 'details' => ['resume' => 'Please upload a PDF, DOC, or DOCX file.']]);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(400, ['error' => 'Validation failed.', 'details' => ['email' => 'Please provide a valid email address.']]);
if ($phone !== '' && !preg_match('/^[+0-9() .-]{7,20}$/', $phone)) respond(400, ['error' => 'Validation failed.', 'details' => ['phone' => 'Please provide a valid phone number.']]);

$content = file_get_contents($file['tmp_name']);
if ($content === false) respond(500, ['error' => 'Unable to read the uploaded resume.']);

$to = 'career@tcongsinfotech.com';
$from = 'careers@tcongsinfotech.com';
$boundary = '=_TCONGS_' . md5((string)microtime(true));

$html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937"><h2>New Career Application</h2>';
foreach ([['Source','Career Page'],['Position',$position],['Name',$name],['Email',$email],['Phone',$phone],['Message',$message]] as [$label,$value]) {
    $html .= '<p><strong>' . esc($label) . ':</strong><br>' . nl2br(esc($value)) . '</p>';
}
$html .= '</div>';

$safeFilename = str_replace(['"', "\r", "\n"], '', $filename);
$headers = [
    'From: Tcongs Website - Careers <' . $from . '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
];
if ($email !== '') $headers[] = 'Reply-To: ' . str_replace(["\r", "\n"], '', $email);

$body = '--' . $boundary . "\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n\r\n";
$body .= '--' . $boundary . "\r\n";
$body .= 'Content-Type: application/octet-stream; name="' . $safeFilename . "\"\r\n";
$body .= 'Content-Disposition: attachment; filename="' . $safeFilename . "\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content)) . "\r\n";
$body .= '--' . $boundary . "--\r\n";

if (!mail($to, 'New Career Application', $body, implode("\r\n", $headers))) {
    respond(500, ['error' => 'Failed to send your application. Please try again later.']);
}
respond(200, ['success' => true, 'message' => 'Your application has been sent successfully.']);
