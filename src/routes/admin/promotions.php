<?php
declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin/promotions', function ($request, $response) use ($repos) {
        $rows = $repos['promotions']->all();
        $live = array_column($repos['promotions']->live(date('Y-m-d')), 'id');

        $html = '<p><a class="btn" href="/admin/promotions/new">Add a promotion</a></p>'
            . '<p class="muted">A promotion shows on the home page only while it is published '
            . '<em>and</em> inside its dates. Leave both dates empty to run it until you unpublish it.</p>';
        if (!$rows) {
            $html .= '<p class="muted">Nothing yet.</p>';
        } else {
            $html .= '<table><thead><tr><th>Promotion</th><th>Runs</th><th>Status</th><th></th></tr></thead><tbody>';
            foreach ($rows as $p) {
                $window = ($p['starts_on'] ?? null) || ($p['ends_on'] ?? null)
                    ? fn_e(($p['starts_on'] ?: 'any time') . ' → ' . ($p['ends_on'] ?: 'no end'))
                    : '<span class="muted">always</span>';
                // Three states, and the middle one is the whole reason for this
                // column: published but outside its window, so it is invisible
                // and nobody can tell why.
                if (!$p['is_published']) {
                    $status = '<span class="muted">Unpublished</span>';
                } elseif (in_array($p['id'], $live)) {
                    $status = '<span class="ok">Showing now</span>';
                } else {
                    $status = '<span class="warn">Published, outside its dates</span>';
                }
                $html .= '<tr><td><strong>' . fn_e($p['title']) . '</strong></td>'
                    . '<td>' . $window . '</td><td>' . $status . '</td>'
                    . '<td class="right"><a href="/admin/promotions/' . (int) $p['id'] . '/edit">Edit</a></td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $response->getBody()->write(fn_admin_shell('Promotions', $html, 'promotions'));
        return $response;
    });

    $form = function (?array $p): string {
        $id = $p['id'] ?? null;
        $h = '<form method="post" action="' . ($id ? '/admin/promotions/' . (int) $id : '/admin/promotions')
           . '" enctype="multipart/form-data">';
        $h .= fn_field('title', 'Title', $p['title'] ?? '', 'text',
            'Whatever it is this time — "Back to School Special", not a fixed "Monthly Special".');
        $h .= fn_area('body', 'Details', $p['body'] ?? '', 5);
        $h .= fn_photo_field($p['photo_path'] ?? null);
        $h .= '<div class="two">'
            . fn_field('link_url', 'Button link', $p['link_url'] ?? '', 'text', 'Usually a PunchPass pass.')
            . fn_field('link_label', 'Button text', $p['link_label'] ?? '', 'text', 'e.g. Sign up')
            . '</div>';
        $h .= '<div class="two">'
            . fn_field('starts_on', 'Starts', $p['starts_on'] ?? '', 'date', 'Empty = right away.')
            . fn_field('ends_on', 'Ends', $p['ends_on'] ?? '', 'date', 'Empty = until you unpublish it.')
            . '</div>';
        $h .= '<div class="two">'
            . fn_check('is_published', 'Published', !isset($p['is_published']) || $p['is_published'])
            . fn_field('sort_order', 'Order', $p['sort_order'] ?? 0, 'number')
            . '</div>';
        return $h . fn_actions('Save', '/admin/promotions') . '</form>';
    };

    $app->get('/admin/promotions/new', function ($request, $response) use ($form) {
        $response->getBody()->write(fn_admin_shell('Add a promotion', $form(null), 'promotions'));
        return $response;
    });

    $app->get('/admin/promotions/{id}/edit', function ($request, $response, $args) use ($repos, $form) {
        $p = $repos['promotions']->find((int) $args['id']);
        if (!$p) {
            return $response->withHeader('Location', '/admin/promotions')->withStatus(302);
        }
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $response->getBody()->write(fn_admin_shell('Edit promotion', $form($p), 'promotions', $flash));
        return $response;
    });

    $save = function ($request, $response, $repos, ?int $id) {
        $d = $request->getParsedBody() ?? [];
        $back = $id ? '/admin/promotions/' . $id . '/edit' : '/admin/promotions/new';
        try {
            $new = Uploads::store($request->getUploadedFiles()['photo'] ?? null);
        } catch (RuntimeException $e) {
            return $response->withHeader('Location', $back . '?err=' . urlencode($e->getMessage()))->withStatus(302);
        }
        if ($id) {
            $existing = $repos['promotions']->find($id);
            $d['photo_path'] = $new ?? ($existing['photo_path'] ?? null);
            if ($new && !empty($existing['photo_path'])) {
                Uploads::delete($existing['photo_path']);
            }
            $repos['promotions']->update($id, $d);
        } else {
            $d['photo_path'] = $new;
            $id = $repos['promotions']->create($d);
        }
        return $response->withHeader('Location', '/admin/promotions/' . $id . '/edit?saved=1')->withStatus(302);
    };

    $app->post('/admin/promotions', fn($rq, $rs) => $save($rq, $rs, $repos, null));
    $app->post('/admin/promotions/{id}', fn($rq, $rs, $a) => $save($rq, $rs, $repos, (int) $a['id']));

    $app->post('/admin/promotions/{id}/delete', function ($request, $response, $args) use ($repos) {
        $id = (int) $args['id'];
        if ($p = $repos['promotions']->find($id)) {
            Uploads::delete($p['photo_path'] ?? null);
            $repos['promotions']->delete($id);
        }
        return $response->withHeader('Location', '/admin/promotions')->withStatus(302);
    });
};
