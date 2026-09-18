<?php

declare(strict_types=1);

/**
 * The public site.
 *
 * Schedule is not a route — it is an outbound link to PunchPass, which owns the
 * timetable and the bookings. Reproducing that here would create a second
 * member list that disagrees with the first.
 *
 * Memberships are different: what the club sells and what each one includes is
 * copy, not booking state, and the club sells more than the two passes PunchPass
 * puts up front. So the prices live here and every Sign Up button still hands
 * off to PunchPass to take the money.
 */
return function ($app, $repos) {
    $coaches     = $repos['coaches'];
    $promotions  = $repos['promotions'];
    $memberships = $repos['memberships'];
    $photos      = $repos['photos'];
    $classes     = $repos['classes'];


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
            <p class="lead contact-line">
                TXT or Call: ' . fn_tel_link(fn_setting('phone')) . '<br>
                ' . (fn_setting('map_url') !== ''
                    ? '<a href="' . fn_e(fn_setting('map_url')) . '" rel="noopener">'
                      . fn_e(fn_setting('address')) . '</a>'
                    : fn_e(fn_setting('address'))) . '
            </p>' . '
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

        // Only the ones ticked for the home page — the two main memberships.
        // Extras like a Summer or Kids membership live on /memberships, so
        // adding one never quietly rearranges the front page.
        $tiers = $memberships->forHome(date('Y-m-d'));
        if ($tiers) {
            $html .= '<section><h2>Memberships</h2><div class="tier-grid">';
            foreach ($tiers as $t) {
                $html .= fn_membership_card($t);
            }
            $html .= '</div>'
                . '<p class="more"><a href="/memberships">See all memberships</a></p></section>';
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
    /**
     * The old URL, kept alive on purpose.
     *
     * 301 rather than a second copy of the page: it moves the search ranking
     * this page already has onto the new URL, and anything pointing at the old
     * one — a printed card, a Facebook post, someone's bookmark — still lands in
     * the right place instead of on a 404.
     */
    $app->get('/personal-training', function ($request, $response) {
        return $response->withHeader('Location', '/meet-the-team')->withStatus(301);
    });

    $app->get('/meet-the-team', function ($request, $response) use ($coaches) {
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
            // Title matches the URL and the menu. The description still carries
            // "personal training", which is what people actually search for.
            'Meet the Team',
            'Meet the coaches at FightNight Boxing Club, Niagara Falls NY — personal boxing and fitness training for all levels.',
            $html,
            'training'
        ));
        return $response;
    });


    /* ------------------------------------------------------------- classes */
    $app->get('/classes', function ($request, $response) use ($classes) {
        $list = $classes->published();
        $html = '<h1>Classes</h1>';
        if (!$list) {
            $html .= '<p class="muted">Class details are on their way.</p>';
        } else {
            $html .= '<div class="promo-grid">';
            foreach ($list as $c) {
                $html .= '<article class="promo">';
                if (!empty($c['photo_path'])) {
                    $html .= '<img src="' . fn_e($c['photo_path']) . '" alt="' . fn_e($c['name']) . '" loading="lazy">';
                }
                $html .= '<div class="promo-body"><h3>' . fn_e($c['name']) . '</h3>';
                $age = ClassRepository::ageLabel($c);
                if ($age !== '') {
                    $html .= '<p class="agetag">' . fn_e($age) . '</p>';
                }
                if (!empty($c['summary'])) {
                    $html .= '<p>' . fn_e($c['summary']) . '</p>';
                }
                $html .= '<a class="btn btn-sm" href="/classes/' . fn_e($c['slug']) . '">Read more</a>';
                $html .= '</div></article>';
            }
            $html .= '</div>';
        }
        $response->getBody()->write(fn_page_shell(
            'Classes', 'What each class at FightNight Boxing Club involves.', $html, 'classes'
        ));
        return $response;
    });

    $app->get('/classes/{slug}', function ($request, $response, $args) use ($classes, $memberships) {
        $c = $classes->findBySlug((string) $args['slug']);
        if (!$c) {
            $response->getBody()->write(fn_page_shell('Not found', '', '<h1>Not found</h1>
                <p class="muted">That class does not exist. <a href="/classes">See all classes</a>.</p>'));
            return $response->withStatus(404);
        }
        $html = '<h1>' . fn_e($c['name']) . '</h1>';
        $age = ClassRepository::ageLabel($c);
        if ($age !== '') {
            $html .= '<p class="agetag big">' . fn_e($age) . '</p>';
        }
        if (!empty($c['summary'])) {
            $html .= '<p class="lead">' . fn_e($c['summary']) . '</p>';
        }
        if (!empty($c['photo_path'])) {
            $html .= '<img class="classhero" src="' . fn_e($c['photo_path']) . '" alt="' . fn_e($c['name']) . '">';
        }
        $html .= fn_paragraphs($c['description'] ?? '');
        // Booking goes to this class's own PunchPass page when it has one, and
        // to the general schedule otherwise.
        $book = !empty($c['punchpass_url']) ? $c['punchpass_url'] : fn_punchpass('classes');
        $html .= '<a class="btn" href="' . fn_e($book) . '" rel="noopener">Book this class</a>';

        // A membership attached to this class — a Kids membership under Kids
        // Boxing. Most classes have none, and then this section is not there at
        // all rather than being an empty heading.
        $tiers = $memberships->forClass((int) $c['id'], date('Y-m-d'));
        if ($tiers) {
            $html .= '<section><h2>Membership for this class</h2><div class="tier-grid">';
            foreach ($tiers as $t) {
                $html .= fn_membership_card($t);
            }
            $html .= '</div><p class="more"><a href="/memberships">See all memberships</a></p></section>';
        }

        $html .= '<p class="muted" style="margin-top:2rem;"><a href="/classes">← All classes</a></p>';

        $response->getBody()->write(fn_page_shell(
            $c['name'],
            $c['summary'] ?: ($c['name'] . ' at FightNight Boxing Club.'),
            $html,
            'classes'
        ));
        return $response;
    });

    /* --------------------------------------------------------- memberships */
    /**
     * Every membership currently on sale, main ones first.
     *
     * sort_order already puts the two main memberships at the top, so this is a
     * plain list rather than two sections — a "Main" heading above two cards and
     * an "Extras" heading above one reads as a filing system, not an offer.
     */
    $app->get('/memberships', function ($request, $response) use ($memberships) {
        $list = $memberships->live(date('Y-m-d'));

        $html = '<h1>Memberships</h1>';
        if (!$list) {
            $html .= '<p class="muted">Membership details are on their way. '
                . '<a href="' . fn_e(fn_punchpass('passes')) . '" rel="noopener">See what\'s on PunchPass</a>.</p>';
        } else {
            // Only when it is actually set. A code-side default would come back
            // the moment someone cleared the box, because the settings form
            // writes every key and get() only falls back when the key is absent.
            $intro = fn_setting('memberships_intro');
            if ($intro !== '') {
                $html .= '<p class="lead">' . fn_e($intro) . '</p>';
            }
            $html .= '<div class="tier-grid">';
            foreach ($list as $m) {
                $html .= fn_membership_card($m);
            }
            $html .= '</div>';
        }
        $html .= '<p class="muted" style="margin-top:2rem;">Memberships are billed through PunchPass. '
            . 'Not sure which one you want? <a href="/contact">Talk to us</a> — or just come in.</p>';

        $response->getBody()->write(fn_page_shell(
            'Memberships',
            'Membership options and prices at FightNight Boxing Club, Niagara Falls NY.',
            $html,
            'membership'
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
                ' . (fn_setting('email') !== ''
                    ? '<h3>Email</h3><p><a href="mailto:' . fn_e(fn_setting('email')) . '">'
                      . fn_e(fn_setting('email')) . '</a></p>'
                    : '') . '
                <h3>Hours</h3>
                <p>' . fn_e(fn_setting('hours')) . '</p>
              </div>
              <div>
                <h3>Book a class</h3>
                <p>The schedule and the billing are handled through PunchPass.</p>
                <a class="btn btn-sm" href="' . fn_e(fn_punchpass('classes')) . '" rel="noopener">See the schedule</a>
                <a class="btn btn-sm" href="/memberships">Memberships</a>
              </div>
            </div>';
        $response->getBody()->write(fn_page_shell(
            'Contact', 'Find FightNight Boxing Club at ' . fn_setting('address') . '.', $html, 'contact'
        ));
        return $response;
    });
};
