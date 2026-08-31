<?php

declare(strict_types=1);

/**
 * The public site.
 *
 * Schedule and Membership are deliberately not routes — they are outbound links
 * to PunchPass, which owns classes, bookings and memberships. Reproducing any
 * of that here would create a second member list that disagrees with the first.
 */
return function ($app) {
    $app->get('/', function ($request, $response) {
        $body = '<section class="hero">
            <h1>FightNight Boxing Club</h1>
            <p class="lead">Real boxing training. All levels welcome.</p>
            <a class="btn" href="' . htmlspecialchars(fn_punchpass_url()) . '" rel="noopener">
                See the class schedule
            </a>
        </section>';
        $response->getBody()->write(fn_page_shell('Home', 'FightNight Boxing Club — boxing training for all levels.', $body));
        return $response;
    });
};
