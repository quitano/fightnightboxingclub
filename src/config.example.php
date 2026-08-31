<?php

// Copy to config.local.php and fill in. config.local.php is gitignored and
// never leaves the machine it is on — the same arrangement fightnights uses.
declare(strict_types=1);

return [
    'db_host' => '127.0.0.1',
    'db_name' => 'fnboxing_club',
    'db_user' => 'fnboxing_dev',
    'db_pass' => '',
    'env'     => 'development',   // 'production' on the droplet
    'site_url' => 'http://localhost:8090',

    // Classes, bookings and memberships all live in PunchPass. This site links
    // out and never reproduces any of it.
    //
    // They send x-frame-options: sameorigin, so these cannot be embedded in an
    // iframe — checked 2026-08-27. Linking out is the only option unless
    // PunchPass supports a custom domain on their end.
    'punchpass_url'        => 'https://fightnight.punchpass.com',
    'punchpass_classes_url' => 'https://fightnight.punchpass.com/classes',
    'punchpass_passes_url'  => 'https://fightnight.punchpass.com/passes',
];
