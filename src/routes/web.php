<?php

declare(strict_types=1);

return function ($app, $repos) {
    (require __DIR__ . '/web/pages.php')($app, $repos);
};
