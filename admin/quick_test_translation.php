<?php
require_once __DIR__ . '/../core/bootstrap.php';

$keys = [
  'registration_verification_email_subject',
  'registration_email_main_message',
  'verify_email_button_text',
  'check_inbox_spam_notice'
];
$langs = [ $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us') ];
if (!in_array('pt-br', $langs, true)) $langs[] = 'pt-br';
if (!in_array('en-us', $langs, true)) $langs[] = 'en-us';
if (!in_array('es-es', $langs, true)) $langs[] = 'es-es';

$contexts = ['email_templates','emails','ui_messages','default'];

header('Content-Type: text/plain; charset=utf-8');

echo "Language in session: ".($_SESSION['language'] ?? 'none')."\n\n";
foreach ($langs as $l) {
  foreach ($keys as $k) {
    $val = getTranslation($k, $l, 'email_templates');
    echo "[$l] $k => $val\n";
  }
  echo "\n";
}
