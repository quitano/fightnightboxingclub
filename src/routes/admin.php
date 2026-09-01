<?php

declare(strict_types=1);

require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../Support/AdminHelpers.php';
require_once __DIR__ . '/../Support/Uploads.php';

return function ($app, $repos) {

    /**
     * Deny by default.
     *
     * Everything under /admin needs a login except the login page itself. A
     * coach is allowed no further than their own profile — the allowlist below
     * is the only thing a non-admin can reach, and the routes check ownership
     * again before saving, because a form that is never rendered can still be
     * posted to.
     */
    $app->add(function ($request, $handler) {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, '/admin')) {
            return $handler->handle($request);
        }
        if ($path === '/admin/login' || $path === '/admin/logout') {
            return $handler->handle($request);
        }

        if (!Auth::check()) {
            $r = new \Slim\Psr7\Response();
            return $r->withHeader('Location', '/admin/login')->withStatus(302);
        }

        if (!Auth::isAdmin()) {
            $coachId = Auth::coachId();
            $allowed = $coachId !== null && (
                preg_match('#^/admin/coaches/' . $coachId . '(/|$)#', $path) === 1
            );
            if (!$allowed) {
                $r = new \Slim\Psr7\Response();
                // Send them where they can actually go rather than a dead end.
                $to = $coachId !== null ? '/admin/coaches/' . $coachId . '/edit' : '/admin/logout';
                return $r->withHeader('Location', $to)->withStatus(302);
            }
        }

        return $handler->handle($request);
    });

    (require __DIR__ . '/admin/auth.php')($app, $repos);
    (require __DIR__ . '/admin/dashboard.php')($app, $repos);
    (require __DIR__ . '/admin/coaches.php')($app, $repos);
    (require __DIR__ . '/admin/classes.php')($app, $repos);
    (require __DIR__ . '/admin/promotions.php')($app, $repos);
    (require __DIR__ . '/admin/photos.php')($app, $repos);
    (require __DIR__ . '/admin/settings.php')($app, $repos);
    (require __DIR__ . '/admin/users.php')($app, $repos);
};
