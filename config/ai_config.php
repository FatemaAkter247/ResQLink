<?php

define('AI_PROVIDER', 'gemini');

// Load from environment variables
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', 'gemini-2.0-flash');

define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL', 'gpt-4.1');

define('AI_MAX_OUTPUT_TOKENS', 700);
define('AI_TEMPERATURE', 0.25);