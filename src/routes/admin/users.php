<?php
declare(strict_types=1);

/** Accounts. Admin only — the middleware already blocks coaches from here. */
return function ($app, $repos) {
    $app->get('/admin/users', function ($request, $response) use ($repos) {
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $rows = $repos['users']->all();

        $html = '<p><a class="btn" href="/admin/users/new">Add a login</a></p>'
            . '<p class="muted">A <strong>coach</strong> login can edit one profile and nothing else — '
            . 'not other coaches, not promotions, not settings. Tick <em>gallery</em> on a coach login to let '
            . 'them manage photos as well. An <strong>admin</strong> can do everything.</p>'
            . '<table><thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Profile</th><th>Gallery</th><th>Last login</th><th></th></tr></thead><tbody>';
        foreach ($rows as $u) {
            $html .= '<tr><td><strong>' . fn_e($u['username']) . '</strong></td>'
                . '<td>' . fn_e($u['display_name'] ?? '') . '</td>'
                . '<td>' . fn_e($u['role']) . '</td>'
                . '<td>' . fn_e($u['coach_name'] ?? '—') . '</td>'
                . '<td>' . ($u['role'] === 'admin' || !empty($u['can_manage_photos']) ? 'Yes' : '—') . '</td>'
                . '<td>' . fn_e($u['last_login_at'] ?? 'never') . '</td>'
                . '<td class="right"><a href="/admin/users/' . (int) $u['id'] . '/edit">Edit</a></td></tr>';
        }
        $html .= '</tbody></table>';
        $response->getBody()->write(fn_admin_shell('Users', $html, 'users', $flash));
        return $response;
    });

    $form = function (?array $u, array $coaches): string {
        $id = $u['id'] ?? null;
        $opts = '<option value="">— none (admin) —</option>';
        foreach ($coaches as $c) {
            $sel = (int) ($u['coach_id'] ?? 0) === (int) $c['id'] ? ' selected' : '';
            $opts .= '<option value="' . (int) $c['id'] . '"' . $sel . '>' . fn_e($c['name']) . '</option>';
        }
        $roleC = ($u['role'] ?? 'coach') === 'coach' ? ' selected' : '';
        $roleA = ($u['role'] ?? '') === 'admin' ? ' selected' : '';

        $h = '<form method="post" action="' . ($id ? '/admin/users/' . (int) $id : '/admin/users') . '">';
        $h .= fn_field('username', 'Username', $u['username'] ?? '', 'text',
            $id ? 'Cannot be changed.' : '');
        if ($id) {
            $h = str_replace('name="username"', 'name="username" readonly', $h);
        }
        $h .= fn_field('display_name', 'Display name', $u['display_name'] ?? '');
        $h .= fn_field('email', 'Email', $u['email'] ?? '', 'email');
        $h .= fn_field('password', $id ? 'New password' : 'Password', '', 'password',
            $id ? 'Leave empty to keep the current one.' : 'At least 10 characters.');
        $h .= '<div class="two">'
            . '<div class="f"><label for="f_role">Role</label><select id="f_role" name="role">'
            . '<option value="coach"' . $roleC . '>Coach — their own profile only</option>'
            . '<option value="admin"' . $roleA . '>Admin — everything</option>'
            . '</select></div>'
            . '<div class="f"><label for="f_coach">Which profile</label><select id="f_coach" name="coach_id">'
            . $opts . '</select><small>Required for a coach login.</small></div>'
            . '</div>';
        $h .= fn_check('can_manage_photos', 'Can manage the photo gallery', !empty($u['can_manage_photos']),
            'For a coach login. Admins always can.');
        return $h . fn_actions('Save', '/admin/users') . '</form>';
    };

    $app->get('/admin/users/new', function ($request, $response) use ($repos, $form) {
        $response->getBody()->write(fn_admin_shell('Add a login', $form(null, $repos['coaches']->all()), 'users'));
        return $response;
    });

    $app->get('/admin/users/{id}/edit', function ($request, $response, $args) use ($repos, $form) {
        $u = $repos['users']->find((int) $args['id']);
        if (!$u) {
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }
        $response->getBody()->write(fn_admin_shell('Edit ' . $u['username'],
            $form($u, $repos['coaches']->all()), 'users'));
        return $response;
    });

    /** Shared validation: a coach login with no profile could edit nothing. */
    $validate = function (array $d, bool $isNew): ?string {
        if ($isNew && strlen((string) ($d['password'] ?? '')) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (!empty($d['password']) && strlen((string) $d['password']) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (($d['role'] ?? '') === 'coach' && empty($d['coach_id'])) {
            return 'A coach login needs a profile to attach to.';
        }
        return null;
    };

    $app->post('/admin/users', function ($request, $response) use ($repos, $validate) {
        $d = $request->getParsedBody() ?? [];
        if ($err = $validate($d, true)) {
            return $response->withHeader('Location', '/admin/users?err=' . urlencode($err))->withStatus(302);
        }
        if ($repos['users']->findByUsername(trim((string) $d['username']))) {
            return $response->withHeader('Location', '/admin/users?err=' . urlencode('That username is taken.'))->withStatus(302);
        }
        $repos['users']->create($d);
        return $response->withHeader('Location', '/admin/users?saved=1')->withStatus(302);
    });

    $app->post('/admin/users/{id}', function ($request, $response, $args) use ($repos, $validate) {
        $id = (int) $args['id'];
        $d = $request->getParsedBody() ?? [];
        if ($err = $validate($d, false)) {
            return $response->withHeader('Location', '/admin/users?err=' . urlencode($err))->withStatus(302);
        }
        // Never let the last admin demote themselves out of the building.
        $existing = $repos['users']->find($id);
        if ($existing && $existing['role'] === 'admin' && ($d['role'] ?? '') !== 'admin'
            && $repos['users']->countAdmins() <= 1) {
            return $response->withHeader('Location', '/admin/users?err='
                . urlencode('That is the only admin account — promote another first.'))->withStatus(302);
        }
        $repos['users']->update($id, $d);
        return $response->withHeader('Location', '/admin/users?saved=1')->withStatus(302);
    });

    $app->post('/admin/users/{id}/delete', function ($request, $response, $args) use ($repos) {
        $id = (int) $args['id'];
        $u = $repos['users']->find($id);
        if ($u && $u['role'] === 'admin' && $repos['users']->countAdmins() <= 1) {
            return $response->withHeader('Location', '/admin/users?err='
                . urlencode('That is the only admin account.'))->withStatus(302);
        }
        if ($id === Auth::id()) {
            return $response->withHeader('Location', '/admin/users?err='
                . urlencode('You cannot delete the account you are logged in with.'))->withStatus(302);
        }
        $repos['users']->delete($id);
        return $response->withHeader('Location', '/admin/users?saved=1')->withStatus(302);
    });
};
