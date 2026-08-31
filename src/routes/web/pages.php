<?php

declare(strict_types=1);

/**
 * The public site.
 *
 * Schedule and Membership are not routes — they are outbound links to
 * PunchPass, which owns classes, bookings and memberships. Reproducing any of
 * it here would create a second member list that disagrees with the first.
 */
return function ($app, $coaches, $promotions, $memberships, $photos) {

    /* ---------------------------------------------------------------- home */
    $app->get('/', function ($request, $response) use ($promotions, $memberships) {
        $video = fn_setting('youtube_id');
        $html = '';

        if ($video !== '') {
            $html .= '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/'
                . fn_e($video) . '" title="FightNight Boxing Club"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"
                allowfullscreen loading="lazy"></iframe></div>';
        }

        $html .= '<section class="hero">
            <h1>' . fn_e(fn_setting('tagline', "WE'RE IN YOUR CORNER!")) . '</h1>
            <p class="lead">' . fn_e(fn_setting('intro')) . '</p>
            <p class="lead">TXT or Call: ' . fn_tel_link(fn_setting('phone')) . '</p>
            <a class="btn" href="' . fn_e(fn_punchpass('passes')) . '" rel="noopener">'
            . fn_e(fn_setting('cta_label', 'START TODAY!')) . '</a>
        </section>';

        // Promotions only appear when there are live ones — an empty "Whats On"
        // heading looks more neglected than no section at all.
        $live = $promotions->live(date('Y-m-d'));
        if ($live) {
            $html .= '<section><h2>What\'s On</h2><div class="promo-grid">';
            foreach ($live as $p) {
                $html .= '<article class="promo">';
                if (!empty($p['photo_path'])) {
                    $html .= '<img src="' . fn_e($p['photo_path']) . '" alt="' . fn_e($p['title']) . '" loading="lazy">';
                }
                $html .= '<div class="promo-body"><h3>' . fn_e($p['title']) . '</h3>'
                    . fn_paragraphs($p['body'] ?? '');
                if (!empty($p['link_url'])) {
                    $html .= '<a class="btn btn-sm" href="' . fn_e($p['link_url']) . '" rel="noopener">'
                        . fn_e($p['link_label'] ?: 'Find out more') . '</a>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></section>';
        }

        $tiers = $memberships->published();
        if ($tiers) {
            $html .= '<section><h2>Memberships</h2><div class="tier-grid">';
            foreach ($tiers as $t) {
                $html .= '<article class="tier"><h3>' . fn_e($t['name']) . '</h3>';
                if ($t['price'] !== null) {
                    $html .= '<p class="price">$' . number_format((float) $t['price'], 0)
                        . '<span>/' . fn_e($t['period'] ?: 'month') . '</span></p>';
                }
                $html .= fn_paragraphs($t['description'] ?? '');
                if (!empty($t['punchpass_url'])) {
                    $html .= '<a class="btn btn-sm" href="' . fn_e($t['punchpass_url']) . '" rel="noopener">Join</a>';
                }
                $html .= '</article>';
            }
            $html .= '</div></section>';
        }

        $html .= '<div class="info-banner">
            <strong>' . fn_e(fn_setting('address')) . '</strong>
            TXT or Call: ' . fn_tel_link(fn_setting('phone')) . '<br>
            ' . fn_e(fn_setting('hours')) . '
        </div>';

        $response->getBody()->write(fn_page_shell(
            'Home',
            fn_setting('intro', 'FightNight Boxing Club — boxing training for all levels.'),
            $html,
            'home'
        ));
        return $response;
    });

    /* --------------------------------------------------- personal training */
    $app->get('/personal-training', function ($request, $response) use ($coaches) {
        $html = '<section class="hero"><h1>Meet Our Team</h1>'
            . fn_paragraphs(fn_setting('training_intro',
                'Private training sessions are personalized to your fitness goals. Sessions run 30 to 60 minutes and include a physical warmup, calisthenics, shadowboxing, mitt work and heavy bag work, plus strength and conditioning to build a fighter\'s mind, body and spirit.'))
            . '</section>';

        $prices = fn_setting('training_prices', "\$40 for 30 mins\n\$50 for 45 mins\n\$60 for 60 mins");
        $lines = CoachRepository::lines($prices);
        if ($lines) {
            $html .= '<section><h2>Personal Training Prices</h2><ul class="price-list">';
            foreach ($lines as $l) {
                $html .= '<li>' . fn_e($l) . '</li>';
            }
            $html .= '</ul></section>';
        }

        $list = $coaches->published();
        if ($list) {
            $html .= '<div class="coach-grid">';
            foreach ($list as $c) {
                $html .= '<article class="coach"><div class="coach-head"><h3>' . fn_e($c['name']) . '</h3>';
                if (!empty($c['phone'])) {
                    $html .= fn_tel_link($c['phone'], 'Call or Text: ' . $c['phone']);
                }
                $html .= '</div>';
                if (!empty($c['role_title'])) {
                    $html .= '<p class="coach-role">' . fn_e($c['role_title']) . '</p>';
                }
                if (!empty($c['photo_path'])) {
                    $html .= '<img src="' . fn_e($c['photo_path']) . '" alt="' . fn_e($c['name']) . '" loading="lazy">';
                }
                $html .= '<div class="coach-body">' . fn_paragraphs($c['bio'] ?? '');
                foreach ([['Certifications', $c['certifications']], ['Specialties', $c['specialties']]] as [$heading, $raw]) {
                    $items = CoachRepository::lines($raw);
                    if ($items) {
                        $html .= '<h4>' . $heading . '</h4><ul>';
                        foreach ($items as $i) {
                            $html .= '<li>' . fn_e($i) . '</li>';
                        }
                        $html .= '</ul>';
                    }
                }
                $html .= '</div></article>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p class="muted">Coach profiles are on their way.</p>';
        }

        $response->getBody()->write(fn_page_shell(
            'Personal Training',
            'Personal boxing and fitness training at FightNight Boxing Club, Niagara Falls NY.',
            $html,
            'training'
        ));
        return $response;
    });

    /* ------------------------------------------------------------- gallery */
    $app->get('/gallery', function ($request, $response) use ($photos) {
        $album = trim((string) ($request->getQueryParams()['album'] ?? ''));
        $albums = $photos->albums();
        $list = $photos->published($album !== '' ? $album : null);

        $html = '<h1>Gallery</h1>';
        if ($albums) {
            $html .= '<nav class="pills"><a href="/gallery"' . ($album === '' ? ' class="active"' : '') . '>All</a>';
            foreach ($albums as $a) {
                $html .= '<a href="/gallery?album=' . urlencode($a) . '"'
                    . ($album === $a ? ' class="active"' : '') . '>' . fn_e($a) . '</a>';
            }
            $html .= '</nav>';
        }
        if ($list) {
            $html .= '<div class="gallery">';
            foreach ($list as $p) {
                $html .= '<figure><img src="' . fn_e($p['photo_path']) . '" alt="'
                    . fn_e($p['caption'] ?: 'FightNight Boxing Club') . '" loading="lazy">';
                if (!empty($p['caption'])) {
                    $html .= '<figcaption>' . fn_e($p['caption']) . '</figcaption>';
                }
                $html .= '</figure>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p class="muted">Photos coming soon.</p>';
        }

        $response->getBody()->write(fn_page_shell(
            'Gallery', 'Photos from FightNight Boxing Club in Niagara Falls, NY.', $html, 'gallery'
        ));
        return $response;
    });

    /* ------------------------------------------------------------- contact */
    $app->get('/contact', function ($request, $response) {
        $mapUrl = fn_setting('map_url');
        $html = '<h1>Contact</h1>
            <div class="contact-grid">
              <div>
                <h3>Come and see us</h3>
                <p>' . ($mapUrl !== ''
                    ? '<a href="' . fn_e($mapUrl) . '" rel="noopener">' . fn_e(fn_setting('address')) . '</a>'
                    : fn_e(fn_setting('address'))) . '</p>
                <h3>Call or text</h3>
                <p>' . fn_tel_link(fn_setting('phone')) . '</p>
                <h3>Hours</h3>
                <p>' . fn_e(fn_setting('hours')) . '</p>
              </div>
              <div>
                <h3>Book a class</h3>
                <p>Classes and memberships are handled through PunchPass.</p>
                <a class="btn btn-sm" href="' . fn_e(fn_punchpass('classes')) . '" rel="noopener">See the schedule</a>
                <a class="btn btn-sm" href="' . fn_e(fn_punchpass('passes')) . '" rel="noopener">Memberships</a>
              </div>
            </div>';
        $response->getBody()->write(fn_page_shell(
            'Contact', 'Find FightNight Boxing Club at ' . fn_setting('address') . '.', $html, 'contact'
        ));
        return $response;
    });
};
