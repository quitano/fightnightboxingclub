<?php

// Copy to config.local.php and fill in. config.local.php is gitignored and
// never leaves the machine it is on — the same arrangement fightnights uses.
declare(strict_types=1);

return [
    'db_host' => '127.0.0.1',
    'db_name' => 'fnbc_app',
    'db_user' => 'fnbc_dev',
    'db_pass' => '',
    'env'     => 'development',   // 'production' on the droplet
    'site_url' => 'http://localhost:8090',

    // Everything about classes, bookings and memberships lives in PunchPass.
    // This site links out to it and never tries to reproduce it.
    'punchpass_url' => 'https://fightnight.punchpass.com',
];
