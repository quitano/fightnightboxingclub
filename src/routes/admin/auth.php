<?php

declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin/login', function ($request, $response) {
        if (Auth::check()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $err = ($request->getQueryParams()['e'] ?? '') === '1'
            ? '<p class="err">That username and password did not match.</p>' : '';
        $response->getBody()->write('<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in — FightNight Boxing Club</title>
<link rel="stylesheet" href="' . fn_e(fn_asset('/css/admin.css')) . '"></head>
<body class="loginpage">
  <form method="post" action="/admin/login" class="loginbox">
    <h1>FIGHT NIGHT <em>ADMIN</em></h1>' . $err . '
    <div class="f"><label for="u">Username</label>
      <input id="u" name="username" autocomplete="username" autofocus required></div>
    <div class="f"><label for="p">Password</label>
      <input id="p" name="password" type="password" autocomplete="current-password" required></div>
    <button type="submit">Log in</button>
  </form>
</body></html>');
        return $response;
    });

    $app->post('/admin/login', function ($request, $response) use ($repos) {
        $d = $request->getParsedBody() ?? [];
        $user = $repos['users']->findByUsername(trim((string) ($d['username'] ?? '')));

        // password_verify against a fixed dummy hash when the user does not
        // exist, so a missing username takes the same time as a wrong password
        // and cannot be told apart by timing.
        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfoxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $ok = password_verify((string) ($d['password'] ?? ''), $hash) && $user !== null;

        if (!$ok) {
            return $response->withHeader('Location', '/admin/login?e=1')->withStatus(302);
        }

        Auth::login($user);
        $repos['users']->touchLogin((int) $user['id']);

        $to = Auth::isAdmin() ? '/admin'
            : (Auth::coachId() ? '/admin/coaches/' . Auth::coachId() . '/edit' : '/admin/logout');
        return $response->withHeader('Location', $to)->withStatus(302);
    });

    $app->get('/admin/logout', function ($request, $response) {
        Auth::logout();
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    });
};
