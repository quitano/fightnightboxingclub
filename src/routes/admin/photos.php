<?php
declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin/photos', function ($request, $response) use ($repos) {
        $q = $request->getQueryParams();
        $flash = !empty($q['saved']) ? 'Saved.' : (!empty($q['err']) ? (string) $q['err'] : '');
        $rows = $repos['photos']->all();

        $html = '<form method="post" action="/admin/photos" enctype="multipart/form-data" class="uploadbar">'
            . '<div class="two">' . fn_photo_field(null, 'photo', 'Add a photo')
            . '<div>' . fn_field('caption', 'Caption', '', 'text')
            . fn_field('album', 'Album', '', 'text', 'Optional, e.g. "Fight Night 2026". Groups the gallery.')
            . '</div></div>' . fn_actions('Upload') . '</form>';

        if (!$rows) {
            $html .= '<p class="muted">No photos yet.</p>';
        } else {
            $html .= '<div class="pgrid">';
            foreach ($rows as $p) {
                $html .= '<form method="post" action="/admin/photos/' . (int) $p['id'] . '" class="pcell">'
                    . '<img src="' . fn_e($p['photo_path']) . '" alt="">'
                    . fn_field('caption', 'Caption', $p['caption'] ?? '')
                    . fn_field('album', 'Album', $p['album'] ?? '')
                    . '<div class="two">'
                    . fn_check('is_published', 'Shown', $p['is_published'])
                    . fn_field('sort_order', 'Order', $p['sort_order'], 'number')
                    . '</div>'
                    . '<div class="actions"><button type="submit">Save</button>'
                    . '<button class="danger" type="submit" formaction="/admin/photos/' . (int) $p['id'] . '/delete">Delete</button>'
                    . '</div></form>';
            }
            $html .= '</div>';
        }
        $response->getBody()->write(fn_admin_shell('Gallery', $html, 'photos', $flash));
        return $response;
    });

    $app->post('/admin/photos', function ($request, $response) use ($repos) {
        $d = $request->getParsedBody() ?? [];
        try {
            $path = Uploads::store($request->getUploadedFiles()['photo'] ?? null);
        } catch (RuntimeException $e) {
            return $response->withHeader('Location', '/admin/photos?err=' . urlencode($e->getMessage()))->withStatus(302);
        }
        if ($path === null) {
            return $response->withHeader('Location', '/admin/photos?err=' . urlencode('Choose an image first.'))->withStatus(302);
        }
        $repos['photos']->create($path, $d['caption'] ?? null, $d['album'] ?? null);
        return $response->withHeader('Location', '/admin/photos?saved=1')->withStatus(302);
    });

    $app->post('/admin/photos/{id}', function ($request, $response, $args) use ($repos) {
        $repos['photos']->update((int) $args['id'], $request->getParsedBody() ?? []);
        return $response->withHeader('Location', '/admin/photos?saved=1')->withStatus(302);
    });

    $app->post('/admin/photos/{id}/delete', function ($request, $response, $args) use ($repos) {
        $id = (int) $args['id'];
        if ($p = $repos['photos']->find($id)) {
            Uploads::delete($p['photo_path']);
            $repos['photos']->delete($id);
        }
        return $response->withHeader('Location', '/admin/photos?saved=1')->withStatus(302);
    });
};
