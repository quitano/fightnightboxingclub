<?php

declare(strict_types=1);

/** Absolute URL from config — never the request Host header, which a visitor controls. */
function fn_absolute_url(string $path): string
{
    $base = rtrim((string) (Database::config()['site_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Where members actually book. Config, not hardcoded, so it can move. */
function fn_punchpass_url(): string
{
    return (string) (Database::config()['punchpass_url'] ?? 'https://fightnight.punchpass.com');
}

/**
 * The page wrapper: head, nav, footer.
 *
 * Schedule and Membership point at PunchPass by design — see routes/web/pages.php.
 */
function fn_page_shell(string $title, string $metaDescription, string $body): string
{
    $pp = htmlspecialchars(fn_punchpass_url());
    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars($title) . ' — FightNight Boxing Club</title>
<meta name="description" content="' . htmlspecialchars($metaDescription) . '">
<link rel="stylesheet" href="/css/site.css">
</head>
<body>
<header class="nav">
  <a class="brand" href="/">FightNight Boxing Club</a>
  <nav>
    <a href="/coaches">Coaches</a>
    <a href="/gallery">Gallery</a>
    <a href="' . $pp . '" rel="noopener">Schedule</a>
    <a href="' . $pp . '" rel="noopener">Membership</a>
    <a href="/contact">Contact</a>
  </nav>
</header>
<main>' . $body . '</main>
<footer class="foot">
  <p>&copy; ' . date('Y') . ' FightNight Boxing Club</p>
</footer>
</body>
</html>';
}
