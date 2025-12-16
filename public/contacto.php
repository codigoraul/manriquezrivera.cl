<?php

declare(strict_types=1);

$BASE_PATH = '';
$SITE_URL = (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
  ? ('https://' . $_SERVER['HTTP_HOST'])
  : 'https://manriquezrivera.cl';

$TO_EMAIL = 'contacto@manriquezrivera.cl';
$FROM_EMAIL = 'contacto@manriquezrivera.cl';
$FROM_NAME = 'Manríquez Rivera';

$ENV_BASE_PATH = getenv('ASTRO_BASE');
if ($ENV_BASE_PATH !== false && $ENV_BASE_PATH !== '') {
  $BASE_PATH = $ENV_BASE_PATH;
}

$ENV_SITE_URL = getenv('SITE_URL');
if ($ENV_SITE_URL !== false && $ENV_SITE_URL !== '') {
  $SITE_URL = $ENV_SITE_URL;
}

$ENV_TO_EMAIL = getenv('CONTACT_TO_EMAIL');
if ($ENV_TO_EMAIL !== false && $ENV_TO_EMAIL !== '') {
  $TO_EMAIL = $ENV_TO_EMAIL;
}

$ENV_FROM_EMAIL = getenv('CONTACT_FROM_EMAIL');
if ($ENV_FROM_EMAIL !== false && $ENV_FROM_EMAIL !== '') {
  $FROM_EMAIL = $ENV_FROM_EMAIL;
}

$ENV_FROM_NAME = getenv('CONTACT_FROM_NAME');
if ($ENV_FROM_NAME !== false && $ENV_FROM_NAME !== '') {
  $FROM_NAME = $ENV_FROM_NAME;
}

$RECAPTCHA_SECRET = getenv('RECAPTCHA_SECRET');
if ($RECAPTCHA_SECRET === false) {
  $RECAPTCHA_SECRET = '';
}

$CONFIG_PATHS = [
  __DIR__ . '/contacto-config.php',
  dirname(__DIR__) . '/contacto-config.php',
];

foreach ($CONFIG_PATHS as $configPath) {
  if (is_file($configPath)) {
    $config = include $configPath;
    if (is_array($config)) {
      if (isset($config['BASE_PATH']) && is_string($config['BASE_PATH'])) $BASE_PATH = $config['BASE_PATH'];
      if (isset($config['SITE_URL']) && is_string($config['SITE_URL'])) $SITE_URL = $config['SITE_URL'];
      if (isset($config['TO_EMAIL']) && is_string($config['TO_EMAIL'])) $TO_EMAIL = $config['TO_EMAIL'];
      if (isset($config['FROM_EMAIL']) && is_string($config['FROM_EMAIL'])) $FROM_EMAIL = $config['FROM_EMAIL'];
      if (isset($config['FROM_NAME']) && is_string($config['FROM_NAME'])) $FROM_NAME = $config['FROM_NAME'];
      if (isset($config['RECAPTCHA_SECRET']) && is_string($config['RECAPTCHA_SECRET'])) $RECAPTCHA_SECRET = $config['RECAPTCHA_SECRET'];
    }
    break;
  }
}

function redirect_to(string $url): void {
  header('Location: ' . $url, true, 303);
  exit;
}

function base_url(string $siteUrl, string $basePath, string $path): string {
  $basePath = rtrim($basePath, '/');
  $path = '/' . ltrim($path, '/');
  return rtrim($siteUrl, '/') . ($basePath ? $basePath : '') . $path;
}

function contacto_url(string $siteUrl, string $basePath, string $status): string {
  $base = base_url($siteUrl, $basePath, '/contacto');
  $qs = http_build_query(['status' => $status]);
  return $base . '?' . $qs . '#contacto';
}

function contacto_url_with_error(string $siteUrl, string $basePath, string $status, string $error): string {
  $base = base_url($siteUrl, $basePath, '/contacto');
  $qs = http_build_query(['status' => $status, 'error' => $error]);
  return $base . '?' . $qs . '#contacto';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to(base_url($SITE_URL, $BASE_PATH, '/contacto#contacto'));
}

$gotcha = trim((string)($_POST['_gotcha'] ?? ''));
if ($gotcha !== '') {
  redirect_to(base_url($SITE_URL, $BASE_PATH, '/gracias'));
}

$nombre = trim((string)($_POST['nombre'] ?? ''));
$empresa = trim((string)($_POST['empresa'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));
$servicio = trim((string)($_POST['servicio'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($nombre === '' || $email === '' || $telefono === '' || $message === '') {
  redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'missing_fields'));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'invalid_email'));
}

$recaptchaResponse = trim((string)($_POST['g-recaptcha-response'] ?? ''));
if ($RECAPTCHA_SECRET === '' || $recaptchaResponse === '') {
  redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'recaptcha_required'));
}

$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
$postData = http_build_query([
  'secret' => $RECAPTCHA_SECRET,
  'response' => $recaptchaResponse,
  'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
]);

$verifyResponse = null;
if (function_exists('curl_init')) {
  $ch = curl_init($verifyUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  $verifyResponse = curl_exec($ch);
  curl_close($ch);
} else {
  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => $postData,
      'timeout' => 10,
    ]
  ]);
  $verifyResponse = @file_get_contents($verifyUrl, false, $context);
}

$verifyJson = is_string($verifyResponse) ? json_decode($verifyResponse, true) : null;
@file_put_contents(__DIR__ . '/recaptcha.log', ($verifyResponse !== null ? $verifyResponse : 'NO_RESPONSE') . "\n", FILE_APPEND);
if (!is_array($verifyJson) || empty($verifyJson['success'])) {
  $errorCodes = '';
  if (is_array($verifyJson) && isset($verifyJson['error-codes']) && is_array($verifyJson['error-codes'])) {
    $errorCodes = implode(',', array_map('strval', $verifyJson['error-codes']));
  }
  if ($errorCodes !== '') {
    redirect_to(contacto_url_with_error($SITE_URL, $BASE_PATH, 'recaptcha_failed', $errorCodes));
  }
  redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'recaptcha_failed'));
}

$subject = 'Nuevo contacto desde manriquezrivera.cl';

$lines = [];
$lines[] = 'Nuevo contacto desde el sitio web:';
$lines[] = '';
$lines[] = 'Nombre: ' . $nombre;
if ($empresa !== '') $lines[] = 'Empresa: ' . $empresa;
$lines[] = 'Email: ' . $email;
$lines[] = 'Teléfono: ' . $telefono;
if ($servicio !== '') $lines[] = 'Servicio: ' . $servicio;
$lines[] = '';
$lines[] = 'Mensaje:';
$lines[] = $message;
$bodyText = implode("\n", $lines);

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . $FROM_NAME . ' <' . $FROM_EMAIL . '>';
$headers[] = 'Reply-To: ' . $nombre . ' <' . $email . '>';

$ok = @mail($TO_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));

if ($ok) {
  redirect_to(base_url($SITE_URL, $BASE_PATH, '/gracias'));
}

redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'mail_failed'));
