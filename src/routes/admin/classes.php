<?php
declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin/classes', function ($request, $response) use ($repos) {
        $rows = $repos['classes']->all();
        $html = '<p><a class="btn" href="/admin/classes/new">Add a class</a></p>';
        if (!$rows) {
            $html .= '<p class="muted">No classes yet. Add one for each thing you run — '
                . 'Kids Boxing, Fundamentals, Sparring — so people know what they are booking.</p>';
        } else {
            $html .= '<table><thead><tr><th>Class</th><th>Ages</th><th>Own tab</th><th>Shown</th><th></th></tr></thead><tbody>';
            foreach ($rows as $c) {
                $html .= '<tr>'
                    . '<td><strong>' . fn_e($c['name']) . '</strong><br><small>/classes/' . fn_e($c['slug']) . '</small></td>'
                    . '<td>' . fn_e(ClassRepository::ageLabel($c) ?: '—') . '</td>'
                    . '<td>' . ($c['show_in_nav'] ? 'Yes' : '—') . '</td>'
                    . '<td>' . ($c['is_published'] ? 'Yes' : '<span class="warn">Hidden</span>') . '</td>'
                    . '<td class="right"><a href="/admin/classes/' . (int) $c['id'] . '/edit">Edit</a></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';
        }
        $response->getBody()->write(fn_admin_shell('Classes', $html, 'classes'));
        return $response;
    });

    $form = function (?array $c): string {
        $id = $c['id'] ?? null;
        $h = '<form method="post" action="' . ($id ? '/admin/classes/' . (int) $id : '/admin/classes')
           . '" enctype="multipart/form-data">';
        $h .= fn_field('name', 'Class name', $c['name'] ?? '', 'text', 'e.g. Kids Boxing');
        $h .= fn_field('summary', 'One-line summary', $c['summary'] ?? '', 'text', 'Shown on the classes list.');
        $h .= fn_area('description', 'Full description', $c['description'] ?? '', 8,
            'What the class actually involves. Blank line between paragraphs.');
        $h .= fn_photo_field($c['photo_path'] ?? null);
        $h .= '<div class="two">'
            . fn_field('age_min', 'Minimum age', $c['age_min'] ?? '', 'number', 'Leave empty if there is none.')
            . fn_field('age_max', 'Maximum age', $c['age_max'] ?? '', 'number')
            . '</div>';
        $h .= fn_field('punchpass_url', 'PunchPass link', $c['punchpass_url'] ?? '', 'text',
            'Where people book this class.');
        $h .= '<div class="two">'
            . fn_check('show_in_nav', 'Give this class its own tab in the menu',
                !empty($c['show_in_nav']), 'Use sparingly — the menu gets long fast.')
            . fn_check('is_published', 'Show on the website',
                !isset($c['is_published']) || $c['is_published'])
            . '</div>';
        $h .= fn_field('sort_order', 'Order', $c['sort_order'] ?? 0, 'number', 'Lower shows first.');
        return $h . fn_actions('Save', '/admin/classes') . '</form>';
    };

    $app->get('/admin/classes/new', function ($request, $response) use ($form) {
        $response->getBody()->write(fn_admin_shell('Add a class', $form(null), 'classes'));
        return $response;
    });

    $app->get('/admin/classes/{id}/edit', function ($request, $response, $args) use ($repos, $form) {
        $c = $repos['classes']->find((int) $args['id']);
        if (!$c) {
            return $response->withHeader('Location', '/admin/classes')->withStatus(302);
        }
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $response->getBody()->write(fn_admin_shell('Edit ' . $c['name'], $form($c), 'classes', $flash));
        return $response;
    });

    $save = function ($request, $response, $repos, ?int $id) {
        $d = $request->getParsedBody() ?? [];
        $back = $id ? '/admin/classes/' . $id . '/edit' : '/admin/classes/new';
        try {
            $new = Uploads::store($request->getUploadedFiles()['photo'] ?? null);
        } catch (RuntimeException $e) {
            return $response->withHeader('Location', $back . '?err=' . urlencode($e->getMessage()))->withStatus(302);
        }
        if ($id) {
            $existing = $repos['classes']->find($id);
            $d['photo_path'] = $new ?? ($existing['photo_path'] ?? null);
            if ($new && !empty($existing['photo_path'])) {
                Uploads::delete($existing['photo_path']);
            }
            $repos['classes']->update($id, $d);
        } else {
            $d['photo_path'] = $new;
            $id = $repos['classes']->create($d);
        }
        return $response->withHeader('Location', '/admin/classes/' . $id . '/edit?saved=1')->withStatus(302);
    };

    $app->post('/admin/classes', fn($rq, $rs) => $save($rq, $rs, $repos, null));
    $app->post('/admin/classes/{id}', fn($rq, $rs, $a) => $save($rq, $rs, $repos, (int) $a['id']));

    $app->post('/admin/classes/{id}/delete', function ($request, $response, $args) use ($repos) {
        $id = (int) $args['id'];
        if ($c = $repos['classes']->find($id)) {
            Uploads::delete($c['photo_path'] ?? null);
            $repos['classes']->delete($id);
        }
        return $response->withHeader('Location', '/admin/classes')->withStatus(302);
    });
};
