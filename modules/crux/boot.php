<?php

// CrUX module boot: make API key available via putenv() so the module's getenv() works
// The platform's .env is already loaded via phpdotenv ($_ENV)
$cruxKey = $_ENV['CRUX_API_KEY'] ?? '';
putenv("CRUX_API_KEY={$cruxKey}");
