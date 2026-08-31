<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Support/WebHelpers.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
// Details on locally, hidden in production — a stack trace leaks paths and
// database config to anyone who can trigger an error.
$app->addErrorMiddleware(!Database::isProduction(), true, true);

(require __DIR__ . '/../src/routes/web.php')($app);

$app->run();
