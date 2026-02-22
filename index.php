<?php

session_start();
require_once __DIR__ . '/inc/bootstrap.php';

$route = $_GET['route'] ?? 'home';
$GLOBALS['route'] = $route;

switch ($route) {
    case 'home':
        page('University of Northampton', "
            <h1>University of Northampton</h1>
            <em>Supporting aspiration, creating opportunities, delivering impact</em>

            <p>We offer an extensive support throughout your studies. We have a range of support and services available to help ensure that your time at the University is as enjoyable and rewarding as possible. Our services offer support in many areas including academic, mental and physical health, disabilities, and general student support.</p>
        ");
        break;



    case 'db-test':
        $configFile = __DIR__ . '/../config/config.php';
        if (!file_exists($configFile)) {
            page('DB Test – Missing config', "
                <div class='card'>
                    <p><strong>config/config.php not found.</strong></p>
                    <p>Create it at: <code>websites/default/config/config.php</code></p>
                </div>
                <p><a href='/?route=home'>Back home</a></p>
            ");
        }

        $config = require $configFile;

        try {
            if (!class_exists('App\\Database\\Connection')) {
                page('DB Test – Missing Connection class', "
                    <div class='card'>
                        <p><strong>Class not found:</strong> <code>App\\\\Database\\\\Connection</code></p>
                        <p>Check this file exists:</p>
                        <p><code>websites/default/src/Database/Connection.php</code></p>
                    </div>
                    <p><a href='/?route=home'>Back home</a></p>
                ");
            }

            $conn = new App\Database\Connection($config);
            $stmt = $conn->pdo()->query("SELECT COUNT(*) AS total FROM users");
            $row = $stmt->fetch();
            $total = isset($row['total']) ? (string)$row['total'] : 'unknown';

            page('DB connected OK', "
                <div class='card'>
                    <p><strong>Total users:</strong> " . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . "</p>
                    <p>If this shows <code>1</code>, your superadmin row is seeded correctly.</p>
                </div>
                <p><a href='/?route=home'>Back home</a></p>
            ");
        } catch (Throwable $e) {
            page('DB Test – Error', "
                <div class='card'>
                    <p><strong>Something failed:</strong></p>
                    <pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>
                </div>
                <p><a href='/?route=home'>Back home</a></p>
            ");
        }
        break;

    case 'enquiry':
        require __DIR__ . '/inc/enquiry.php';
        handle_enquiry_route();
        break;


    case 'subject-areas':
    case 'subject-area':
        require_once __DIR__ . '/inc/subject_areas.php';
        handle_subject_areas_route();
        break;

    case 'course-search':
        require_once __DIR__ . '/inc/course_search.php';
        handle_course_search_route();
        break;

    case 'admin-login':
        require_once __DIR__ . '/inc/admin.php';
        handle_admin_login_route();
        break;

    case 'admin-dashboard':
        require_once __DIR__ . '/inc/admin.php';
        handle_admin_dashboard_route();
        break;

    case 'logout':
        require_once __DIR__ . '/inc/admin.php';
        handle_logout_route();
        break;

    case 'admin-users':
        require_once __DIR__ . '/inc/admin.php';
        handle_admin_users_route();
        break;

    case 'admin-enquiries':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_enquiries_route();
        break;
    
    case 'admin-subject-areas':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_subject_areas_route();
        break;

    case 'admin-course-modules':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_course_modules_route();
        break;

    case 'admin-course-edit':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_course_edit_route();
        break;

    case 'admin-course-delete':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_course_delete_route();
        break;

    case 'admin-module-edit':
        require_once __DIR__ . '/inc/admin_acess.php';
        handle_admin_module_edit_route();
        break;


    default:
        page('404 – Not found', "
            <div class='card'>
                <p>Unknown route: <code>" . htmlspecialchars((string)$route, ENT_QUOTES, 'UTF-8') . "</code></p>
                <p><a href='/?route=home'>Go home</a></p>
            </div>
        ");
}
