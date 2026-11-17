<?php
/** Receives the contact form. Responds with JSON for fetch() requests, or redirects for plain form posts. */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

function respond(bool $ok, string $msg, int $code = 200): never
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $msg]);
        exit;
    }
    flash($ok ? 'success' : 'error', $msg);
    redirect('index.php#contact');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contact');
}
if (!is_installed()) {
    respond(false, 'The site is not set up yet.', 503);
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    respond(false, 'Your session expired. Please refresh the page and try again.', 419);
}
// Honeypot: real people never fill the hidden "website" field. Pretend success for bots.
if (!empty($_POST['website'])) {
    respond(true, 'Thanks! Your message has been sent.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$allowedServices = array_column(fetch_rows('services'), 'title');
$allowedServices[] = 'Other';
if (!in_array($service, $allowedServices, true)) $service = '';
$message = trim((string) ($_POST['message'] ?? ''));
// Booking details (optional)
$eventDate = trim((string) ($_POST['event_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) || !strtotime($eventDate)) $eventDate = null;
$eventLocation = mb_substr(trim((string) ($_POST['event_location'] ?? '')), 0, 200) ?: null;
$budget = mb_substr(trim((string) ($_POST['budget'] ?? '')), 0, 60) ?: null;

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Please enter your name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Please enter a valid email address.';
if (mb_strlen($subject) > 150) $errors[] = 'Subject is too long.';
if (mb_strlen($message) < 10) $errors[] = 'Your message is too short.';
if (mb_strlen($message) > 5000) $errors[] = 'Your message is too long (max 5000 characters).';
if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

$ip = client_ip();
$st = db()->prepare('SELECT COUNT(*) FROM messages WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$st->execute([$ip]);
if ((int) $st->fetchColumn() >= 3) {
    respond(false, 'You have sent several messages recently. Please wait a few minutes and try again.', 429);
}

$st = db()->prepare('INSERT INTO messages (name, email, service, subject, event_date, event_location, budget, message, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$st->execute([$name, $email, $service ?: null, $subject, $eventDate, $eventLocation, $budget, $message, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);

respond(true, "Thanks, {$name}! Your message has been sent. I'll get back to you soon.");
