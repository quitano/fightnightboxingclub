<?php
declare(strict_types=1);

return function ($app, $repos) {
    $app->get('/admin', function ($request, $response) use ($repos) {
        $counts = [
            'Coaches'    => ['/admin/coaches',    count($repos['coaches']->all())],
            'Classes'    => ['/admin/classes',    count($repos['classes']->all())],
            'Memberships'=> ['/admin/memberships', count($repos['memberships']->all())],
            'Promotions' => ['/admin/promotions', count($repos['promotions']->all())],
            'Photos'     => ['/admin/photos',     count($repos['photos']->all())],
        ];
        $html = '<div class="cards">';
        foreach ($counts as $label => [$href, $n]) {
            $html .= '<a class="card" href="' . fn_e($href) . '"><span class="n">' . $n . '</span>'
                . fn_e($label) . '</a>';
        }
        $html .= '</div>';

        // Promotions are the thing most likely to be silently wrong — published
        // but outside its date window, so nobody sees it and nobody knows why.
        $live = $repos['promotions']->live(date('Y-m-d'));
        $all  = $repos['promotions']->all();
        $hidden = array_filter($all, fn($p) => $p['is_published'] && !in_array($p, $live, true));
        $html .= '<h2>Right now</h2><ul class="plain">';
        $html .= '<li>' . count($live) . ' promotion(s) showing on the home page.</li>';
        if ($hidden) {
            $html .= '<li class="warn">' . count($hidden)
                . ' published promotion(s) are outside their date window, so they are not showing.</li>';
        }

        // Memberships can go quiet the same two ways: out of season, or on sale
        // but with nothing on the home page at all.
        $mLive   = $repos['memberships']->live(date('Y-m-d'));
        $mHome   = $repos['memberships']->forHome(date('Y-m-d'));
        $liveIds = array_column($mLive, 'id');
        $mHidden = array_filter(
            $repos['memberships']->all(),
            fn($m) => $m['is_published'] && !in_array($m['id'], $liveIds)
        );
        $html .= '<li>' . count($mLive) . ' membership(s) on sale, '
            . count($mHome) . ' of them on the home page.</li>';
        if ($mHidden) {
            $html .= '<li class="warn">' . count($mHidden)
                . ' published membership(s) are outside their dates, so they are not showing.</li>';
        }
        if ($mLive && !$mHome) {
            $html .= '<li class="warn">No membership is ticked for the home page.</li>';
        }
        $html .= '</ul>';

        $response->getBody()->write(fn_admin_shell('Dashboard', $html, 'dashboard'));
        return $response;
    });
};
