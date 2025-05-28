<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Oauth2;

echo class_exists(Client::class) ? "✅ Google\Client loaded\n" : "❌ Google\Client missing\n";
echo class_exists(Oauth2::class) ? "✅ Google\Service\Oauth2 loaded\n" : "❌ Google\Service\Oauth2 missing\n";
