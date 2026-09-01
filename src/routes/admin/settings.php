<?php
declare(strict_types=1);

return function ($app, $repos) {
    /** The settings the form exposes: key => [label, hint, multiline]. */
    $fields = [
        'tagline'    => ['Tagline', 'The big line on the home page.', false],
        'intro'      => ['Intro', 'One or two sentences under the tagline.', true],
        'phone'      => ['Phone', 'Shown everywhere, and tappable on a mobile.', false],
        'email'      => ['Email', '', false],
        'address'    => ['Address', '', false],
        'hours'      => ['Hours', '', false],
        'map_url'    => ['Map link', 'Google Maps URL the address links to.', false],
        'youtube_id' => ['YouTube video ID', 'Just the ID, e.g. dQw4w9WgXcQ — not the whole URL.', false],
        'cta_label'  => ['Main button text', '', false],
        'training_intro'  => ['Personal training intro', 'Top of the Personal Training page.', true],
        'training_prices' => ['Standard training prices', 'One per line. Coaches with their own rates override this.', true],
    ];

    $app->get('/admin/settings', function ($request, $response) use ($repos, $fields) {
        $saved = ($request->getQueryParams()['saved'] ?? '') === '1';
        $html = '<form method="post" action="/admin/settings">';
        foreach ($fields as $key => [$label, $hint, $multi]) {
            $val = $repos['settings']->get($key);
            $html .= $multi
                ? fn_area($key, $label, $val, 4, $hint)
                : fn_field($key, $label, $val, 'text', $hint);
        }
        $html .= fn_actions('Save settings') . '</form>';
        $response->getBody()->write(fn_admin_shell('Settings', $html, 'settings',
            $saved ? 'Settings saved.' : ''));
        return $response;
    });

    $app->post('/admin/settings', function ($request, $response) use ($repos, $fields) {
        $d = $request->getParsedBody() ?? [];
        foreach (array_keys($fields) as $key) {
            $repos['settings']->save($key, trim((string) ($d[$key] ?? '')));
        }
        return $response->withHeader('Location', '/admin/settings?saved=1')->withStatus(302);
    });
};
