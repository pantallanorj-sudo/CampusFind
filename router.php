<?php
/**
 * Router for PHP's built-in development server. Apache ignores this file (it uses .htaccess).
 *
 *   php -S localhost:8000 router.php        (run from the project folder)
 *
 * It refuses direct downloads of the database, configuration, source folders, logs and private
 * claim evidence, which the built-in server would otherwise serve because it ignores .htaccess.
 * Everything else is passed back to the built-in server unchanged.
 */
$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$path = '/' . ltrim(str_replace('\\', '/', rawurldecode((string) $path)), '/');

$blockedFolders = '#^/(config|includes|services|database|logs|tools|tests)(/|$)#i';
$blockedFiles = '#\.(sqlite|sqlite3|db|sql|log|lock|ini|md)$#i';
$privateUploads = '#^/uploads/claim-evidence(/|$)#i';
$uploadScripts = '#^/uploads/.*\.(php|phtml|phar|php[0-9])$#i';
$dotFiles = '#/\.#';
$traversal = '#(^|/)\.\.(/|$)#';

if (preg_match($blockedFolders, $path) || preg_match($blockedFiles, $path) || preg_match($privateUploads, $path)
    || preg_match($uploadScripts, $path) || preg_match($dotFiles, $path) || preg_match($traversal, $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}
return false;
