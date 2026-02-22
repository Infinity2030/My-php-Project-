<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$publicDir = dirname(__DIR__);

$autoload = $publicDir . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

$connectionClass = $publicDir . '/../src/Database/Connection.php';
if (file_exists($connectionClass)) {
    require_once $connectionClass;
}


function db(): \App\Database\Connection
{
    $publicDir = dirname(__DIR__);
    $configFile = $publicDir . '/../config/config.php';

    if (!file_exists($configFile)) {
        page('Server error', "<p>Missing config/config.php</p>");
    }

    $config = require $configFile;
    return new \App\Database\Connection($config);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /?route=admin-login');
        exit;
    }
}

function requireSuperadmin(): void
{
    requireLogin();
    if (($_SESSION['user']['role_name'] ?? '') !== 'superadmin') {
        page('403 Forbidden', "<p>You do not have permission to view this page.</p>");
    }
}

function requireAdminOrSuperadmin(): void
{
    requireLogin();
    $role = $_SESSION['user']['role_name'] ?? '';
    if ($role !== 'admin' && $role !== 'superadmin') {
        page('403 Forbidden', '<p>You do not have permission to view this page.</p>');
    }
}

function makeSlug(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s === '' ? 'subject' : $s;
}

function page(string $title, string $mainHtml): void
{
    $route = $GLOBALS['route'] ?? 'home';

    echo "<!doctype html>";
    echo "<html><head>";
    echo "<meta charset='utf-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>";
    echo "<link rel='stylesheet' href='/uon.css' />";

    $bodyClass = 'route-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string)$route);
    echo "</head><body class='" . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . "'>";

    $subjectItems = '';
    try {
        $rows = db()->pdo()->query("SELECT name FROM subject_areas ORDER BY name ASC")->fetchAll();
        foreach ($rows as $r) {
            $n = $r['name'];
            $subjectItems .= "<li><a href='/?route=subject-areas&name=" . urlencode($n) . "'>" . htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . "</a></li>";
        }
    } catch (Throwable $e) {
        $subjectItems = "<li><a href='/?route=subject-areas&name=Computing'>Computing</a></li>";
    }

    $authLinks = '';
    if (isset($_SESSION['user'])) {
        $authLinks = "
            <li><a href='/?route=admin-dashboard'>Admin dashboard</a></li>
            <li><a href='/?route=logout'>Logout</a></li>
        ";
    } else {
        $authLinks = "<li><a href='/?route=admin-login'>Admin login</a></li>";
    }

    echo "<header>
            <img src='/logo.jpg' alt='University of Northampton logo' />
            <ul>
                <li><a href='/?route=home'>Home</a></li>

                <li>
                    <a href='/?route=subject-areas'>Subject Areas</a>
                    <ul>
                        $subjectItems
                    </ul>
                </li>

                <li><a href='/?route=course-search'>Course search</a></li>
                <li><a href='/?route=enquiry'>Make an enquiry</a></li>
                $authLinks
            </ul>
        </header>";

    echo "<section></section>";

    echo "<main>";
    echo $mainHtml;
    echo "</main>";

    echo "<aside>
            <p>We'll support you every step of the way whichever course you choose – providing you with first–class teaching, modern facilities, impressive accommodation and great learning. We have increased focus on seminars or tutorials that allow closer interaction between students and a member of staff in the form of discussion in small groups or one-to-one that mimic practice in the professional world, allowing for experimentation, ideas, and teamwork.</p>
            Applications for 2026 are now open. <a href=''>Find your course now.</a>
        </aside>";


    echo "<footer>&copy; 2025 University of Northampton</footer>";
    echo "</body></html>";
    exit;
}
