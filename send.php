<?php
// Приём заявок на консультацию с сайта likiiigor.ru
// Адрес, на который приходят заявки:
$TO = 'likii.1988@mail.ru';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Moscow');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

// Защита от спам-ботов: скрытое поле должно быть пустым
if (!empty($_POST['website'])) {
  echo json_encode(['ok' => true]);
  exit;
}

function clean($v, $max) {
  $v = trim((string)$v);
  $v = str_replace(["\r", "\0"], '', $v);
  return mb_substr($v, 0, $max, 'UTF-8');
}

$name    = clean($_POST['name'] ?? '', 100);
$phone   = clean($_POST['phone'] ?? '', 40);
$time    = clean($_POST['time'] ?? '', 100);
$comment = clean($_POST['comment'] ?? '', 2000);
$consent = !empty($_POST['consent']);

$digits = preg_replace('/\D+/', '', $phone);
if ($name === '' || strlen($digits) < 10 || !$consent) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'validation']);
  exit;
}

// Простое ограничение: не чаще одной заявки в 30 секунд с одного адреса
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lock = sys_get_temp_dir() . '/likiiigor_form_' . md5($ip);
if (file_exists($lock) && time() - filemtime($lock) < 30) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'rate']);
  exit;
}
@touch($lock);

$name1 = str_replace("\n", ' ', $name);
$subject = 'Заявка на консультацию: ' . $name1;
$body  = "Новая заявка с сайта likiiigor.ru\n\n";
$body .= "Имя: $name1\n";
$body .= "Телефон: $phone\n";
if ($time !== '')    $body .= "Удобное время: " . str_replace("\n", ' ', $time) . "\n";
if ($comment !== '') $body .= "\nКомментарий:\n$comment\n";
$body .= "\n—\nОтправлено: " . date('d.m.Y H:i') . " (МСК)\n";

$headers  = "From: =?UTF-8?B?" . base64_encode('Сайт likiiigor.ru') . "?= <noreply@likiiigor.ru>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

$sent = mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), $headers, '-fnoreply@likiiigor.ru');

if ($sent) {
  echo json_encode(['ok' => true]);
} else {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'mail']);
}
