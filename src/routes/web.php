<?php

declare(strict_types=1);

return function ($app, $coaches, $promotions, $memberships, $photos) {
    (require __DIR__ . '/web/pages.php')($app, $coaches, $promotions, $memberships, $photos);
};
