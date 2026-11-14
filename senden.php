<?php
// Wunschformular „Dir fehlt etwas?“ der Cornnector-Seite (Anleitung DE/EN).
// Erwartet POST (fetch, FormData) und antwortet mit JSON {ok: bool}.
// Läuft nur auf einem Server mit PHP und funktionierendem mail() (Mittwald).
// Auf GitHub Pages gibt es kein PHP: dort öffnet die Seite stattdessen das Mailprogramm.

declare(strict_types=1);

const EMPFAENGER = 'hey@cornnector.com';
// Absender muss zur Domain des Servers passen, sonst landen Mails im Spam.
const ABSENDER = 'mail@cornnector.com';
const THEMEN = ['Neue Funktion', 'Verbesserung', 'Fehler', 'Sonstiges', 'New feature', 'Improvement', 'Bug', 'Other'];

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function antwort(int $status, bool $ok, string $fehler = ''): void
{
    http_response_code($status);
    echo json_encode($fehler === '' ? ['ok' => $ok] : ['ok' => $ok, 'fehler' => $fehler]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(405, false, 'method');
}
// Honeypot: Bots füllen das versteckte Feld aus, dann so tun, als sei alles gut.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    antwort(200, true);
}

$feld = static fn(string $k, int $max): string => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
$einzeilig = static fn(string $s): string => trim(preg_replace('/[\r\n]+/', ' ', $s) ?? '');

$thema = $einzeilig($feld('t', 40));
$msg   = $feld('w', 10000);
$name  = $einzeilig($feld('n', 200));
$email = $einzeilig($feld('e', 200));
$nl    = ($_POST['nl'] ?? '') !== '';

if ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    antwort(422, false, 'validierung');
}
if (!in_array($thema, THEMEN, true)) {
    $thema = 'Sonstiges';
}

$sprache = ($_POST['lang'] ?? '') === 'en' ? ' [EN]' : '';
$betreff = 'Cornnector' . $sprache . ': ' . $thema;
$text = "Neue Nachricht über „Dir fehlt etwas?“\n\n"
      . "Thema:      {$thema}\n"
      . "Name:       {$name}\n"
      . "E-Mail:     {$email}\n"
      . "Newsletter: " . ($nl ? 'ja (Bestätigung läuft über MailerLite)' : 'nein') . "\n\n"
      . "Nachricht:\n{$msg}\n";

$header = [
    'From' => 'Cornnector Website <' . ABSENDER . '>',
    'Reply-To' => $email,
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
    'MIME-Version' => '1.0',
];

$ok = mail(EMPFAENGER, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $text, $header);
antwort($ok ? 200 : 500, $ok, $ok ? '' : 'versand');
