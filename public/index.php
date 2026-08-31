<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/SettingsRepository.php';
require __DIR__ . '/../src/CoachRepository.php';
require __DIR__ . '/../src/PromotionRepository.php';
require __DIR__ . '/../src/MembershipRepository.php';
require __DIR__ . '/../src/PhotoRepository.php';
require __DIR__ . '/../src/Support/WebHelpers.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
// Details on locally, hidden in production — a stack trace leaks paths and
// database config to anyone who can trigger an error.
$app->addErrorMiddleware(!Database::isProduction(), true, true);

$db          = Database::connection();
$coaches     = new CoachRepository($db);
$promotions  = new PromotionRepository($db);
$memberships = new MembershipRepository($db);
$photos      = new PhotoRepository($db);

(require __DIR__ . '/../src/routes/web.php')($app, $coaches, $promotions, $memberships, $photos);

$app->run();
