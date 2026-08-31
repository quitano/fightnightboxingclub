<?php

declare(strict_types=1);

return function ($app) {
    (require __DIR__ . '/web/pages.php')($app);
};
