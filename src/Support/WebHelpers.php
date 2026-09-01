<?php

declare(strict_types=1);

/** Absolute URL from config — never the request Host header, which a visitor controls. */
function fn_absolute_url(string $path): string
{
    $base = rtrim((string) (Database::config()['site_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Where members book and pay.
 *
 * PunchPass sends x-frame-options: sameorigin, so none of these can be embedded
 * in the page — checked 2026-08-27. They have to be links.
 */
function fn_punchpass(string $which = 'url'): string
{
    $c = Database::config();
    return (string) match ($which) {
        'classes' => $c['punchpass_classes_url'] ?? '',
        'passes'  => $c['punchpass_passes_url'] ?? '',
        default   => $c['punchpass_url'] ?? '',
    };
}

/** One site setting. Cached per request inside SettingsRepository. */
function fn_setting(string $key, string $default = ''): string
{
    static $repo = null;
    if ($repo === null) {
        $repo = new SettingsRepository(Database::connection());
    }
    return $repo->get($key, $default);
}

/**
 * Classes that have earned their own nav tab.
 *
 * The shell is a plain function with no repository handy, and the result is
 * cached in ClassRepository, so this stays one query per request however many
 * times the nav is rendered.
 */
function fn_nav_classes(): array
{
    static $repo = null;
    if ($repo === null) {
        $repo = new ClassRepository(Database::connection());
    }
    return $repo->navClasses();
}

/** A phone number as a tel: link — the whole point on a phone. */
function fn_tel_link(string $phone, string $label = ''): string
{
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    return '<a href="tel:' . htmlspecialchars($digits) . '">'
        . htmlspecialchars($label !== '' ? $label : $phone) . '</a>';
}

function fn_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Paragraphs from a textarea, without letting raw HTML through. */
function fn_paragraphs(?string $text): string
{
    if (!$text) {
        return '';
    }
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) as $para) {
        $out .= '<p>' . nl2br(fn_e(trim($para))) . '</p>';
    }
    return $out;
}

/**
 * The page wrapper.
 *
 * Schedule and Membership are outbound PunchPass links by design, not routes —
 * PunchPass owns classes, bookings and memberships.
 */
function fn_page_shell(string $title, string $metaDescription, string $body, string $active = ''): string
{
    $classesUrl = fn_e(fn_punchpass('classes'));
    $passes  = fn_e(fn_punchpass('passes'));
    $phone   = fn_setting('phone');
    $address = fn_setting('address');
    $hours   = fn_setting('hours');
    $mapUrl  = fn_setting('map_url');
    $email   = fn_setting('email');

    $nav = ['/' => ['Home', 'home']];

    // Any class flagged show_in_nav earns its own tab — Kids Boxing was the ask,
    // but it is a flag rather than a hardcoded slug so the next one is free.
    // Cached inside the repository, so this costs one query per request.
    foreach (fn_nav_classes() as $c) {
        $nav['/classes/' . $c['slug']] = [$c['name'], 'class-' . $c['slug']];
    }

    $nav += [
        '/classes'           => ['Classes', 'classes'],
        '/personal-training' => ['Personal Training', 'training'],
        '/gallery'           => ['Gallery', 'gallery'],
        $classesUrl          => ['Schedule', 'schedule'],
        $passes              => ['Membership', 'membership'],
        '/contact'           => ['Contact', 'contact'],
    ];
    $navHtml = '';
    foreach ($nav as $href => [$label, $key]) {
        $external = str_starts_with($href, 'http');
        $navHtml .= '<a href="' . fn_e($href) . '"'
            . ($key === $active ? ' class="active"' : '')
            . ($external ? ' rel="noopener"' : '')
            . '>' . fn_e($label) . '</a>';
    }

    // The logo file is dropped in later; until then the wordmark is type.
    $brand = is_file(__DIR__ . '/../../public/img/logo.png')
        ? '<img src="/img/logo.png" alt="FightNight Boxing Club">'
        : '<span>FIGHT NIGHT <em>BOXING CLUB</em></span>';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . fn_e($title) . ' — FightNight Boxing Club</title>
<meta name="description" content="' . fn_e($metaDescription) . '">
<meta property="og:title" content="' . fn_e($title) . '">
<meta property="og:description" content="' . fn_e($metaDescription) . '">
<meta property="og:type" content="website">
<link rel="stylesheet" href="/css/site.css">
</head>
<body>
<header class="nav">
  <a class="brand" href="/">' . $brand . '</a>
  <nav>' . $navHtml . '</nav>
</header>
<main>' . $body . '</main>
<footer class="foot">
  <div class="foot-grid">
    <div>
      <strong>FightNight Boxing Club</strong><br>
      ' . ($mapUrl !== ''
            ? '<a href="' . fn_e($mapUrl) . '" rel="noopener">' . fn_e($address) . '</a>'
            : fn_e($address)) . '<br>
      ' . fn_tel_link($phone) . '<br>
      ' . ($email !== '' ? '<a href="mailto:' . fn_e($email) . '">' . fn_e($email) . '</a><br>' : '') . '
      ' . fn_e($hours) . '
    </div>
    <div>
      <a href="' . $classesUrl . '" rel="noopener">Class schedule</a><br>
      <a href="' . $passes . '" rel="noopener">Memberships</a><br>
      <a href="/classes">Classes</a><br>
      <a href="/personal-training">Personal training</a><br>
      <a href="/contact">Contact</a>
    </div>
  </div>
  <p class="copy">&copy; ' . date('Y') . ' FightNight Boxing Club</p>
</footer>
</body>
</html>';
}
