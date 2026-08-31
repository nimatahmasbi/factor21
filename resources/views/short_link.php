<?php
// Short link handler - resolves short_code to quote display
// Keep $shortCode from index.php and delegate to public_quote.php which handles short_code lookup
require __DIR__ . '/public_quote.php';
