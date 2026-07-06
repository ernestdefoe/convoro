<?php

/**
 * Convoro root shim.
 *
 * The correct setup points the web server's document root at the public/
 * directory. Some budget shared hosts serve the application root instead (and
 * won't let you change it). This forwards those requests to the real front
 * controller so the site still works, while the sibling .htaccess denies direct
 * access to sensitive files. The installer/admin will remind you to move the
 * document root to public/ for best security.
 *
 * When the document root is public/ (the correct setup) this file lives ABOVE
 * the web root and is never served, so it has zero effect on a proper install.
 */

$front = __DIR__.'/public/index.php';

if (! is_file($front)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Convoro: public/index.php not found.\nPoint your web server's document root at the public/ directory.");
}

$_SERVER['CONVORO_ROOT_SHIM'] = '1';

require $front;
