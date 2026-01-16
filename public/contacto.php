<?php

declare(strict_types=1);

$BASE_PATH = '';
$SITE_URL = (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
  ? ('https://' . $_SERVER['HTTP_HOST'])
  : 'https://manriquezrivera.cl';

$TO_EMAILS_BASE = 'contacto@manriquezrivera.cl, manriquezrivera.abogados@gmail.com';
$TO_EMAIL = $TO_EMAILS_BASE;
$FROM_EMAIL = 'contacto@manriquezrivera.cl';
$FROM_NAME = 'Manríquez Rivera';
$BCC_EMAILS = '';

$CONFIG_USED_PATH = '';

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
  $TO_EMAIL = $TO_EMAILS_BASE . ', ' . $ENV_TO_EMAIL;
}

$ENV_FROM_EMAIL = getenv('CONTACT_FROM_EMAIL');
if ($ENV_FROM_EMAIL !== false && $ENV_FROM_EMAIL !== '') {
  $FROM_EMAIL = $ENV_FROM_EMAIL;
}

$ENV_FROM_NAME = getenv('CONTACT_FROM_NAME');
if ($ENV_FROM_NAME !== false && $ENV_FROM_NAME !== '') {
  $FROM_NAME = $ENV_FROM_NAME;
}

$ENV_BCC_EMAILS = getenv('CONTACT_BCC_EMAILS');
if ($ENV_BCC_EMAILS !== false && $ENV_BCC_EMAILS !== '') {
  $BCC_EMAILS = $ENV_BCC_EMAILS;
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
      if (isset($config['TO_EMAIL']) && is_string($config['TO_EMAIL'])) $TO_EMAIL = $TO_EMAILS_BASE . ', ' . $config['TO_EMAIL'];
      if (isset($config['FROM_EMAIL']) && is_string($config['FROM_EMAIL'])) $FROM_EMAIL = $config['FROM_EMAIL'];
      if (isset($config['FROM_NAME']) && is_string($config['FROM_NAME'])) $FROM_NAME = $config['FROM_NAME'];
      if (isset($config['BCC_EMAILS']) && is_string($config['BCC_EMAILS'])) $BCC_EMAILS = $config['BCC_EMAILS'];
    }
    $CONFIG_USED_PATH = $configPath;
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
  if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
      'handler' => 'contacto.php',
      'site_url' => $SITE_URL,
      'base_path' => $BASE_PATH,
      'to_email' => $TO_EMAIL,
      'from_email' => $FROM_EMAIL,
      'from_name' => $FROM_NAME,
      'bcc_emails' => $BCC_EMAILS !== '' ? $BCC_EMAILS : null,
      'config_used' => $CONFIG_USED_PATH !== '' ? basename($CONFIG_USED_PATH) : null,
      'config_used_path' => $CONFIG_USED_PATH !== '' ? $CONFIG_USED_PATH : null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }
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

$subject = 'Nuevo contacto desde manriquezrivera.cl';

$escape = static function (string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$sanitizeHeaderValue = static function (string $value): string {
  $value = str_replace(["\r", "\n"], ' ', $value);
  return trim($value);
};

$encodeDisplayName = static function (string $value) use ($sanitizeHeaderValue): string {
  $value = $sanitizeHeaderValue($value);
  if ($value === '') return '';
  return '=?UTF-8?B?' . base64_encode($value) . '?=';
};

$parseEmailList = static function (string $value) use ($sanitizeHeaderValue): array {
  $value = $sanitizeHeaderValue($value);
  if ($value === '') return [];

  $parts = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
  if ($parts === false) return [];

  $emails = [];
  foreach ($parts as $part) {
    $email = $sanitizeHeaderValue($part);
    if ($email === '') continue;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
    $emails[] = $email;
  }

  return array_values(array_unique($emails));
};

$empresaCell = $empresa !== '' ? $escape($empresa) : '-';
$servicioCell = $servicio !== '' ? $escape($servicio) : '-';
$messageHtml = nl2br($escape($message));

$bodyHtml = '<!doctype html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,Helvetica,sans-serif; color:#111827;">'
  . '<h2 style="margin:0 0 16px; font-size:18px;">Nuevo contacto desde el sitio web</h2>'
  . '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; width:100%; max-width:640px;">'
  . '<tbody>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700; width:180px;">Nombre</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $escape($nombre) . '</td></tr>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700;">Empresa</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $empresaCell . '</td></tr>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700;">Email</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $escape($email) . '</td></tr>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700;">Teléfono</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $escape($telefono) . '</td></tr>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700;">Servicio</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $servicioCell . '</td></tr>'
  . '<tr><td style="padding:8px 10px; border:1px solid #E5E7EB; font-weight:700; vertical-align:top;">Mensaje</td><td style="padding:8px 10px; border:1px solid #E5E7EB;">' . $messageHtml . '</td></tr>'
  . '</tbody></table>'
  . '</body></html>';

$bodyText = "Nuevo contacto desde el sitio web\n\n"
  . "Nombre: {$nombre}\n"
  . "Empresa: " . ($empresa !== '' ? $empresa : '-') . "\n"
  . "Email: {$email}\n"
  . "Teléfono: {$telefono}\n"
  . "Servicio: " . ($servicio !== '' ? $servicio : '-') . "\n\n"
  . "Mensaje:\n{$message}\n";

$boundary = 'mr_' . bin2hex(random_bytes(12));
$body = "--{$boundary}\r\n"
  . "Content-Type: text/plain; charset=UTF-8\r\n"
  . "Content-Transfer-Encoding: 8bit\r\n\r\n"
  . $bodyText . "\r\n\r\n"
  . "--{$boundary}\r\n"
  . "Content-Type: text/html; charset=UTF-8\r\n"
  . "Content-Transfer-Encoding: 8bit\r\n\r\n"
  . $bodyHtml . "\r\n\r\n"
  . "--{$boundary}--\r\n";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
$headers[] = 'Date: ' . date(DATE_RFC2822);
$host = parse_url($SITE_URL, PHP_URL_HOST);
if (!is_string($host) || $host === '') {
  $host = 'manriquezrivera.cl';
}
$headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>';
$headers[] = 'From: ' . $encodeDisplayName($FROM_NAME) . ' <' . $sanitizeHeaderValue($FROM_EMAIL) . '>';
$replyToName = $encodeDisplayName($nombre);
$replyToEmail = $sanitizeHeaderValue($email);
$headers[] = 'Reply-To: ' . ($replyToName !== '' ? ($replyToName . ' ') : '') . '<' . $replyToEmail . '>';

$toEmails = $parseEmailList($TO_EMAIL);
$toHeader = $toEmails !== [] ? implode(', ', $toEmails) : $sanitizeHeaderValue($TO_EMAIL);

$bccEmails = $parseEmailList($BCC_EMAILS);
if ($bccEmails !== []) {
  $headers[] = 'Bcc: ' . implode(', ', $bccEmails);
}

$params = '-f ' . $sanitizeHeaderValue($FROM_EMAIL);
$ok = @mail($toHeader, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), $params);
if (!$ok) {
  $ok = @mail($toHeader, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

if ($ok) {
  redirect_to(base_url($SITE_URL, $BASE_PATH, '/gracias'));
}

redirect_to(contacto_url($SITE_URL, $BASE_PATH, 'mail_failed'));
