<?php

declare(strict_types=1);

/**
 * The admin's chrome and form building blocks.
 *
 * The nav is built from what the logged-in user may actually reach, so a coach
 * sees one link — their own profile — rather than a row of things that would
 * bounce them. The permission check still happens in the routes; this is
 * presentation, not enforcement.
 */
function fn_admin_shell(string $title, string $body, string $active = '', string $flash = ''): string
{
    $items = [];
    if (Auth::isAdmin()) {
        $items = [
            '/admin'            => ['Dashboard', 'dashboard'],
            '/admin/coaches'    => ['Coaches', 'coaches'],
            '/admin/classes'    => ['Classes', 'classes'],
            '/admin/promotions' => ['Promotions', 'promotions'],
            '/admin/photos'     => ['Gallery', 'photos'],
            '/admin/settings'   => ['Settings', 'settings'],
            '/admin/users'      => ['Users', 'users'],
        ];
    } elseif (Auth::coachId()) {
        $items = ['/admin/coaches/' . Auth::coachId() . '/edit' => ['My Profile', 'coaches']];
    }

    $nav = '';
    foreach ($items as $href => [$label, $key]) {
        $nav .= '<a href="' . fn_e($href) . '"' . ($key === $active ? ' class="active"' : '') . '>'
            . fn_e($label) . '</a>';
    }

    $flashHtml = $flash !== ''
        ? '<div class="flash">' . fn_e($flash) . '</div>'
        : '';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . fn_e($title) . ' — Admin</title>
<link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<header class="abar">
  <a class="abrand" href="/admin">FIGHT NIGHT <em>ADMIN</em></a>
  <nav>' . $nav . '</nav>
  <div class="awho">
    <span>' . fn_e(Auth::displayName()) . (Auth::isAdmin() ? '' : ' (coach)') . '</span>
    <a href="/" target="_blank">View site</a>
    <a href="/admin/logout">Log out</a>
  </div>
</header>
<main class="awrap">' . $flashHtml . '<h1>' . fn_e($title) . '</h1>' . $body . '</main>
</body>
</html>';
}

/** A labelled text input. */
function fn_field(string $name, string $label, $value = '', string $type = 'text', string $hint = '', string $placeholder = ''): string
{
    return '<div class="f"><label for="f_' . fn_e($name) . '">' . fn_e($label) . '</label>
        <input type="' . fn_e($type) . '" id="f_' . fn_e($name) . '" name="' . fn_e($name) . '"
               value="' . fn_e((string) $value) . '" placeholder="' . fn_e($placeholder) . '">'
        . ($hint !== '' ? '<small>' . fn_e($hint) . '</small>' : '') . '</div>';
}

/** A labelled textarea. */
function fn_area(string $name, string $label, $value = '', int $rows = 4, string $hint = ''): string
{
    return '<div class="f"><label for="f_' . fn_e($name) . '">' . fn_e($label) . '</label>
        <textarea id="f_' . fn_e($name) . '" name="' . fn_e($name) . '" rows="' . $rows . '">'
        . fn_e((string) $value) . '</textarea>'
        . ($hint !== '' ? '<small>' . fn_e($hint) . '</small>' : '') . '</div>';
}

/** A checkbox that posts 1 when ticked. */
function fn_check(string $name, string $label, $checked = false, string $hint = ''): string
{
    return '<div class="f fcheck">
        <input type="checkbox" id="f_' . fn_e($name) . '" name="' . fn_e($name) . '" value="1"'
        . ($checked ? ' checked' : '') . '>
        <label for="f_' . fn_e($name) . '">' . fn_e($label) . '</label>'
        . ($hint !== '' ? '<small>' . fn_e($hint) . '</small>' : '') . '</div>';
}

/** Current image plus a replace field. */
function fn_photo_field(?string $current, string $name = 'photo', string $label = 'Photo'): string
{
    $preview = $current
        ? '<img class="thumb" src="' . fn_e($current) . '" alt="">'
        : '<span class="nothumb">No image yet</span>';
    return '<div class="f"><label>' . fn_e($label) . '</label>
        <div class="photorow">' . $preview . '
          <div><input type="file" name="' . fn_e($name) . '" accept="image/*">
          <small>jpg, png, gif or webp. Up to 8MB. Leave empty to keep the current one.</small></div>
        </div></div>';
}

/** Save / cancel row. */
function fn_actions(string $saveLabel = 'Save', string $cancelUrl = ''): string
{
    return '<div class="actions"><button type="submit">' . fn_e($saveLabel) . '</button>'
        . ($cancelUrl !== '' ? '<a class="cancel" href="' . fn_e($cancelUrl) . '">Cancel</a>' : '')
        . '</div>';
}
