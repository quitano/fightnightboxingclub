<?php
declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin/memberships', function ($request, $response) use ($repos) {
        $rows = $repos['memberships']->all();
        $live = array_column($repos['memberships']->live(date('Y-m-d')), 'id');

        $html = '<p><a class="btn" href="/admin/memberships/new">Add a membership</a></p>'
            . '<p class="muted">The two main memberships are the ones ticked for the home page. '
            . 'Anything else — a Summer or Kids membership — is an extra: it shows on the '
            . '<a href="/memberships" target="_blank">Memberships page</a> and leaves the home page alone.</p>';

        if (!$rows) {
            $html .= '<p class="muted">Nothing yet.</p>';
        } else {
            $html .= '<table><thead><tr><th>Membership</th><th>Price</th><th>Where</th>'
                . '<th>Runs</th><th>Status</th><th></th></tr></thead><tbody>';
            foreach ($rows as $m) {
                $price = $m['price'] !== null
                    ? '$' . number_format((float) $m['price'], 0) . '<small>/' . fn_e($m['period'] ?: 'month') . '</small>'
                    : '<span class="muted">—</span>';

                // Where it shows up. The Memberships page lists everything live,
                // so it is the baseline rather than something to spell out.
                $where = ['Memberships page'];
                if ($m['show_on_home']) { $where[] = 'Home'; }
                if (!empty($m['class_name'])) { $where[] = fn_e($m['class_name']); }

                $window = ($m['starts_on'] ?? null) || ($m['ends_on'] ?? null)
                    ? fn_e(($m['starts_on'] ?: 'any time') . ' → ' . ($m['ends_on'] ?: 'no end'))
                    : '<span class="muted">always</span>';

                // The middle state is why this column exists: published but out
                // of season, so it is invisible and nobody can tell why.
                if (!$m['is_published']) {
                    $status = '<span class="muted">Unpublished</span>';
                } elseif (in_array($m['id'], $live)) {
                    $status = '<span class="ok">Showing now</span>';
                } else {
                    $status = '<span class="warn">Published, outside its dates</span>';
                }

                $html .= '<tr><td><strong>' . fn_e($m['name']) . '</strong>'
                    . (!empty($m['badge']) ? '<br><small>' . fn_e($m['badge']) . '</small>' : '') . '</td>'
                    . '<td>' . $price . '</td>'
                    . '<td><small>' . implode(', ', $where) . '</small></td>'
                    . '<td>' . $window . '</td><td>' . $status . '</td>'
                    . '<td class="right"><a href="/admin/memberships/' . (int) $m['id'] . '/edit">Edit</a></td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $response->getBody()->write(fn_admin_shell('Memberships', $html, 'memberships'));
        return $response;
    });

    $form = function (?array $m) use ($repos): string {
        $id = $m['id'] ?? null;
        $h = '<form method="post" action="' . ($id ? '/admin/memberships/' . (int) $id : '/admin/memberships') . '">';
        $h .= fn_field('name', 'Name', $m['name'] ?? '', 'text', 'e.g. Monthly Membership, The Ultimate, Summer Membership.');

        // Whole dollars only, so step=1 and the hint says so. Cents are rounded
        // away on save rather than shown as a price PunchPass disagrees with.
        $h .= '<div class="two">'
            . fn_field('price', 'Price ($)', $m['price'] !== null ? (int) $m['price'] : '', 'number',
                'Whole dollars — 35, not 34.99.')
            . fn_select('period', 'Per', MembershipRepository::PERIODS, $m['period'] ?? 'month')
            . '</div>';

        $h .= fn_area('description', 'Description', $m['description'] ?? '', 4,
            'A line or two. Blank line between paragraphs.');
        $h .= fn_area('includes', "What's included", $m['includes'] ?? '', 5,
            'One per line. Shown as a ticked list on the card.');
        $h .= fn_field('badge', 'Badge', $m['badge'] ?? '', 'text',
            'A short ribbon across the card — "Most popular", "Best value", "Summer only". Leave empty for none.');
        $h .= fn_field('punchpass_url', 'PunchPass link', $m['punchpass_url'] ?? '', 'text',
            'Where the Sign Up button goes. Buying still happens in PunchPass.');

        $h .= '<div class="two">'
            . fn_field('starts_on', 'Available from', $m['starts_on'] ?? '', 'date', 'Empty = always available.')
            . fn_field('ends_on', 'Available until', $m['ends_on'] ?? '', 'date',
                'Empty = no end. Past this date it drops off the site but stays here.')
            . '</div>';

        // A class is optional and most memberships have none, so "—" is the
        // first option rather than the first class in the list.
        $classes = ['' => '—'];
        foreach ($repos['classes']->all() as $c) {
            $classes[(string) $c['id']] = $c['name'];
        }
        $h .= fn_select('class_id', 'Also show on a class page', $classes, (string) ($m['class_id'] ?? ''),
            'Puts this membership under that class\'s description — a Kids membership on Kids Boxing.');

        $h .= '<div class="two">'
            . fn_check('is_published', 'Published', !isset($m['is_published']) || $m['is_published'],
                'Off hides it everywhere.')
            . fn_check('show_on_home', 'Show on the home page', !empty($m['show_on_home']),
                'The two main memberships only. Extras leave this off.')
            . '</div>';
        $h .= fn_field('sort_order', 'Order', $m['sort_order'] ?? 0, 'number', 'Lower shows first.');

        return $h . fn_actions('Save', '/admin/memberships') . '</form>';
    };

    $app->get('/admin/memberships/new', function ($request, $response) use ($form) {
        $response->getBody()->write(fn_admin_shell('Add a membership', $form(null), 'memberships'));
        return $response;
    });

    $app->get('/admin/memberships/{id}/edit', function ($request, $response, $args) use ($repos, $form) {
        $m = $repos['memberships']->find((int) $args['id']);
        if (!$m) {
            return $response->withHeader('Location', '/admin/memberships')->withStatus(302);
        }
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $response->getBody()->write(fn_admin_shell('Edit ' . $m['name'], $form($m), 'memberships', $flash));
        return $response;
    });

    $save = function ($request, $response, $repos, ?int $id) {
        $d = $request->getParsedBody() ?? [];
        if ($id) {
            $repos['memberships']->update($id, $d);
        } else {
            $id = $repos['memberships']->create($d);
        }
        return $response->withHeader('Location', '/admin/memberships/' . $id . '/edit?saved=1')->withStatus(302);
    };

    $app->post('/admin/memberships', fn($rq, $rs) => $save($rq, $rs, $repos, null));
    $app->post('/admin/memberships/{id}', fn($rq, $rs, $a) => $save($rq, $rs, $repos, (int) $a['id']));

    $app->post('/admin/memberships/{id}/delete', function ($request, $response, $args) use ($repos) {
        $repos['memberships']->delete((int) $args['id']);
        return $response->withHeader('Location', '/admin/memberships')->withStatus(302);
    });
};
