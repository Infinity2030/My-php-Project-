<?php

function handle_admin_login_route(): void
{
    if (isset($_SESSION['user'])) {
        header('Location: /?route=admin-dashboard');
        exit;
    }

    $errors = [];
    $username = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '') $errors[] = "Username is required.";
        if ($password === '') $errors[] = "Password is required.";

        if (empty($errors)) {
            try {
                $stmt = db()->pdo()->prepare("
                    SELECT u.id, u.username, u.password_hash, u.role_id, r.name AS role_name
                    FROM users u
                    JOIN roles r ON r.id = u.role_id
                    WHERE u.username = :username
                    LIMIT 1
                ");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors[] = "Invalid username or password.";
                } else {
                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'role_id' => (int)$user['role_id'],
                        'role_name' => $user['role_name'],
                    ];

                    header('Location: /?route=admin-dashboard');
                    exit;
                }
            } catch (Throwable $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }

    $errorHtml = '';
    if (!empty($errors)) {
        $items = '';
        foreach ($errors as $err) {
            $items .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        $errorHtml = "<div class='card'><strong>Please fix:</strong><ul>$items</ul></div>";
    }

    page('Admin login', "
        <h1>Admin login</h1>
        $errorHtml
        <form action='/?route=admin-login' method='post'>
            <label>Username</label>
            <input type='text' name='username' value='" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "' />

            <label>Password</label>
            <input type='password' name='password' />

            <input type='submit' value='Log in' />
        </form>
    ");
}

function handle_admin_dashboard_route(): void
{
    requireLogin();

    $user = currentUser();

    $links = "<ul>
        <li><a href='/?route=admin-enquiries'>Manage enquiries</a></li>
        <li><a href='/?route=admin-subject-areas'>Manage subject areas & courses</a></li>
        <li><a href='/?route=home'>Back to site</a></li>
        <li><a href='/?route=logout'>Logout</a></li>
    </ul>";

    if (($user['role_name'] ?? '') === 'superadmin') {
        $links = "<ul>
            <li><a href='/?route=admin-users'>Manage users</a></li>
            <li><a href='/?route=admin-enquiries'>Manage enquiries</a></li>
            <li><a href='/?route=admin-subject-areas'>Manage subject areas & courses</a></li>
            <li><a href='/?route=home'>Back to site</a></li>
            <li><a href='/?route=logout'>Logout</a></li>
        </ul>";
    }

    page('Admin dashboard', "
        <h1>Admin dashboard</h1>
        <p>Logged in as <strong>" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "</strong>
        (" . htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8') . ")</p>
        $links
    ");
}

function handle_logout_route(): void
{
    session_destroy();
    $_SESSION = [];
    header('Location: /?route=home');
    exit;
}

function handle_admin_users_route(): void
{
    requireSuperadmin(); 
    $pdo = db()->pdo();
    $msg = '';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete-user') {
        $deleteId = (int)($_POST['id'] ?? 0);

        if ($deleteId === (int)($_SESSION['user']['id'] ?? 0)) {
            $errors[] = 'You cannot delete the account you are currently logged in with.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $deleteId]);
            $msg = 'User deleted.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create-user') {
        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = (string)($_POST['password'] ?? '');
        $newRoleId   = (int)($_POST['role_id'] ?? 0);

        if ($newUsername === '') $errors[] = 'Username is required.';
        if ($newPassword === '') $errors[] = 'Password is required.';
        if ($newRoleId <= 0) $errors[] = 'Role is required.';

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $check->execute([':u' => $newUsername]);
            if ($check->fetch()) {
                $errors[] = 'That username already exists.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                $ins = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role_id)
                    VALUES (:u, :p, :r)
                ");
                $ins->execute([
                    ':u' => $newUsername,
                    ':p' => $hash,
                    ':r' => $newRoleId,
                ]);

                $msg = 'User created.';
            }
        }
    }

    $roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
    $users = $pdo->query("
        SELECT u.id, u.username, u.role_id, r.name AS role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        ORDER BY u.id
    ")->fetchAll();

    $flash = '';
    if ($msg !== '') {
        $flash .= "<div class='card'><strong>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</strong></div>";
    }
    if (!empty($errors)) {
        $lis = '';
        foreach ($errors as $e) $lis .= "<li>" . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . "</li>";
        $flash .= "<div class='card'><strong>Please fix:</strong><ul>$lis</ul></div>";
    }

    $roleOptions = '';
    foreach ($roles as $r) {
        $roleOptions .= "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . "</option>";
    }

    $rows = '';
    foreach ($users as $u) {
        $rows .= "<tr>
            <td>" . (int)$u['id'] . "</td>
            <td>" . htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($u['role_name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>
                <form method='post' action='/?route=admin-users' style='margin:0'>
                    <input type='hidden' name='action' value='delete-user' />
                    <input type='hidden' name='id' value='" . (int)$u['id'] . "' />
                    <input type='submit' value='Delete' />
                </form>
            </td>
        </tr>";
    }

    page('Manage users', "
        <h1>Manage users</h1>
        $flash

        <h2>Create user</h2>
        <form method='post' action='/?route=admin-users'>
            <input type='hidden' name='action' value='create-user' />
            <label>Username</label>
            <input type='text' name='username' />

            <label>Password</label>
            <input type='password' name='password' />

            <label>Role</label>
            <select name='role_id'>
                $roleOptions
            </select>

            <input type='submit' value='Create user' />
        </form>

        <h2>Existing users</h2>
        <table class='card' style='width:100%'>
            <thead>
                <tr><th>ID</th><th>Username</th><th>Role</th><th>Action</th></tr>
            </thead>
            <tbody>
                $rows
            </tbody>
        </table>

        <p><a href='/?route=admin-dashboard'>Back to dashboard</a></p>
    ");
}
