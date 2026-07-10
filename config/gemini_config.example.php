<?php

// Copy this file to "config/gemini_config.php" (git-ignored).
// The API key and model are loaded from the project-root .env file via
// config/ai_config.php — set GEMINI_API_KEY there, not here.
require_once __DIR__ . '/ai_config.php';

if (!defined('GEMINI_API_ENDPOINT')) {
    define(
        'GEMINI_API_ENDPOINT',
        'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode(GEMINI_MODEL) . ':generateContent'
    );
}
