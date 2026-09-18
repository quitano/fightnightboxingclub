<?php
declare(strict_types=1);

/**
 * Coach profiles.
 *
 * The only admin section a coach can reach, and only for their own row. The
 * middleware in admin.php stops them at the door; Auth::canEditCoach() is
 * checked again here, because a form that is never rendered can still be
 * posted to.
 */
return function ($app, $repos) {

    $app->get('/admin/coaches', function ($request, $response) use ($repos) {
        $rows = $repos['coaches']->all();
        $html = '<p><a class="btn" href="/admin/coaches/new">Add a coach</a></p>';
        if (!$rows) {
            $html .= '<p class="muted">No coaches yet.</p>';
        } else {
            $html .= '<table><thead><tr><th></th><th>Name</th><th>Phone</th><th>Shown</th><th></th></tr></thead><tbody>';
            foreach ($rows as $c) {
                $html .= '<tr>'
                    . '<td>' . ($c['photo_path']
                        ? '<img class="mini" src="' . fn_e($c['photo_path']) . '" alt="">'
                        : '<span class="nothumb mini"></span>') . '</td>'
                    . '<td><strong>' . fn_e($c['name']) . '</strong>'
                    . ($c['role_title'] ? '<br><small>' . fn_e($c['role_title']) . '</small>' : '') . '</td>'
                    . '<td>' . fn_e($c['phone'] ?? '') . '</td>'
                    . '<td>' . ($c['is_published'] ? 'Yes' : '<span class="warn">Hidden</span>') . '</td>'
                    . '<td class="right"><a href="/admin/coaches/' . (int) $c['id'] . '/edit">Edit</a></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';
        }
        $response->getBody()->write(fn_admin_shell('Coaches', $html, 'coaches'));
        return $response;
    });

    /** The form, shared by new and edit. */
    $form = function (?array $c, bool $isAdmin): string {
        $id = $c['id'] ?? null;
        $action = $id ? '/admin/coaches/' . (int) $id : '/admin/coaches';
        $h = '<form method="post" action="' . $action . '" enctype="multipart/form-data">';
        $h .= fn_field('name', 'Name', $c['name'] ?? '');
        // The coach's own page — the link they put on a card. Only an admin can
        // change it, because changing it is what breaks whatever is already
        // printed. A coach sees theirs, greyed out, so they know what to hand out.
        if ($isAdmin) {
            $h .= fn_field('slug', 'Their own URL', $c['slug'] ?? '', 'text',
                'Leave empty to build it from the name. Changing this breaks any link already out there.',
                'kristen-alcime');
        } elseif (!empty($c['slug'])) {
            $h .= '<div class="f"><label>Your own page</label>
                <input type="text" value="' . fn_e(fn_absolute_url($c['slug'])) . '" readonly>
                <small>Share this link. Ask an admin if it needs changing.</small></div>';
        }
        $h .= fn_field('role_title', 'Title', $c['role_title'] ?? '', 'text', 'e.g. Head Coach');
        $h .= fn_photo_field($c['photo_path'] ?? null);
        $h .= fn_area('bio', 'Bio', $c['bio'] ?? '', 5, 'Leave a blank line between paragraphs.');
        $h .= '<div class="two">'
            . fn_field('phone', 'Phone', $c['phone'] ?? '', 'text', 'Shown as "Call or Text".')
            . fn_field('email', 'Email', $c['email'] ?? '', 'email')
            . '</div>';
        $h .= fn_area('certifications', 'Certifications', $c['certifications'] ?? '', 5, 'One per line.');
        $h .= fn_area('specialties', 'Specialties', $c['specialties'] ?? '', 5, 'One per line.');
        $h .= fn_area('rates', 'Your training rates', $c['rates'] ?? '', 4,
            'One per line, e.g. "$50 for 45 mins". Leave empty to show the gym\'s standard prices.');
        $h .= '<div class="two">'
            . fn_field('instagram', 'Instagram', $c['instagram'] ?? '', 'text', 'Full URL or @handle.')
            . fn_field('facebook', 'Facebook', $c['facebook'] ?? '', 'text')
            . '</div>';
        $h .= '<div class="two">'
            . fn_field('tiktok', 'TikTok', $c['tiktok'] ?? '', 'text')
            . fn_field('booking_url', 'Booking link', $c['booking_url'] ?? '', 'text',
                'Optional. Leave empty while booking goes through PunchPass.')
            . '</div>';
        // Only an admin decides whether a profile is live or where it sits.
        if ($isAdmin) {
            $h .= '<div class="two">'
                . fn_check('is_published', 'Show on the website', !isset($c['is_published']) || $c['is_published'])
                . fn_field('sort_order', 'Order', $c['sort_order'] ?? 0, 'number', 'Lower shows first.')
                . '</div>';
        }
        $h .= fn_actions('Save', $isAdmin ? '/admin/coaches' : '');
        return $h . '</form>';
    };

    $app->get('/admin/coaches/new', function ($request, $response) use ($form) {
        if (!Auth::isAdmin()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $response->getBody()->write(fn_admin_shell('Add a coach', $form(null, true), 'coaches'));
        return $response;
    });

    $app->post('/admin/coaches', function ($request, $response) use ($repos) {
        if (!Auth::isAdmin()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $d = $request->getParsedBody() ?? [];
        try {
            $path = Uploads::store($request->getUploadedFiles()['photo'] ?? null);
        } catch (RuntimeException $e) {
            return $response->withHeader('Location', '/admin/coaches/new?err=' . urlencode($e->getMessage()))
                            ->withStatus(302);
        }
        $d['photo_path'] = $path;
        $id = $repos['coaches']->create($d);
        return $response->withHeader('Location', '/admin/coaches/' . $id . '/edit?saved=1')->withStatus(302);
    });

    $app->get('/admin/coaches/{id}/edit', function ($request, $response, $args) use ($repos, $form) {
        $id = (int) $args['id'];
        if (!Auth::canEditCoach($id)) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $c = $repos['coaches']->find($id);
        if (!$c) {
            return $response->withHeader('Location', '/admin/coaches')->withStatus(302);
        }
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $title = Auth::isAdmin() ? 'Edit ' . $c['name'] : 'My Profile';
        $response->getBody()->write(fn_admin_shell($title, $form($c, Auth::isAdmin()), 'coaches', $flash));
        return $response;
    });

    $app->post('/admin/coaches/{id}', function ($request, $response, $args) use ($repos) {
        $id = (int) $args['id'];
        // Checked again here: the middleware guards the URL, this guards the act.
        if (!Auth::canEditCoach($id)) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $existing = $repos['coaches']->find($id);
        if (!$existing) {
            return $response->withHeader('Location', '/admin/coaches')->withStatus(302);
        }

        $d = $request->getParsedBody() ?? [];
        // A coach's form shows their URL read-only, but readonly is a hint to
        // the browser, not a rule — the field still posts and the markup can be
        // edited. Only an admin's post is allowed to move a coach's URL, and
        // dropping the key entirely leaves the stored slug alone, because
        // update() writes only the fields it is given.
        if (!Auth::isAdmin()) {
            unset($d['slug']);
        }
        try {
            $new = Uploads::store($request->getUploadedFiles()['photo'] ?? null);
        } catch (RuntimeException $e) {
            return $response->withHeader('Location', '/admin/coaches/' . $id . '/edit?err=' . urlencode($e->getMessage()))
                            ->withStatus(302);
        }
        if ($new !== null) {
            $d['photo_path'] = $new;
            Uploads::delete($existing['photo_path'] ?? null);
        }

        // A coach's form has no publish or order controls on it, so those keys
        // are absent and update() leaves those columns alone rather than
        // blanking them.
        if (!Auth::isAdmin()) {
            unset($d['is_published'], $d['sort_order']);
        } else {
            $d['is_published'] = !empty($d['is_published']) ? 1 : 0;
        }

        $repos['coaches']->update($id, $d);
        return $response->withHeader('Location', '/admin/coaches/' . $id . '/edit?saved=1')->withStatus(302);
    });

    $app->post('/admin/coaches/{id}/delete', function ($request, $response, $args) use ($repos) {
        if (!Auth::isAdmin()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        $id = (int) $args['id'];
        if ($c = $repos['coaches']->find($id)) {
            Uploads::delete($c['photo_path'] ?? null);
            $repos['coaches']->delete($id);
        }
        return $response->withHeader('Location', '/admin/coaches')->withStatus(302);
    });
};
