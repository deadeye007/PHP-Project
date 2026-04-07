<?php
// Application-wide configuration
require_once __DIR__ . '/includes/env.php';

// Rich text HTML allowed tags in sanitizeHTML() (editable, safe subset)
define('ALLOWED_HTML_TAGS', '<p><br><strong><b><em><i><u><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre><span><div><table><thead><tbody><tr><th><td>');

// Default editor mode ('rich' or 'markdown')
define('EDITOR_DEFAULT_MODE', 'rich');

// Markdown settings
define('ENABLE_MARKDOWN', true);

// TinyMCE configuration
define('TINYMCE_API_KEY', getenv('TINYMCE_API_KEY') ?: '');

// HTTPS feature flags
define('HTTPS_ENABLED', getenv('HTTPS_ENABLED') === 'true');
define('HTTPS_REDIRECT', getenv('HTTPS_REDIRECT') === 'true');
define('HTTPS_HSTS', getenv('HTTPS_HSTS') === 'true');
