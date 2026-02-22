<?php

function handle_admin_enquiries_route(): void
{
    requireLogin(); 

    $pdo = db()->pdo();
    $msg = '';
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark-responded') {
        $enqId = (int)($_POST['id'] ?? 0);

        if ($enqId > 0) {
            $stmt = $pdo->prepare("
                UPDATE enquiries
                SET responded = 1,
                    responded_by_user_id = :uid,
                    responded_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':uid' => (int)($_SESSION['user']['id'] ?? 0),
                ':id'  => $enqId,
            ]);
            $msg = 'Enquiry marked as responded.';
        } else {
            $errors[] = 'Invalid enquiry id.';
        }
    }

    $viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($viewId > 0) {
        $stmt = $pdo->prepare("
            SELECT e.*, u.username AS responded_by_username
            FROM enquiries e
            LEFT JOIN users u ON u.id = e.responded_by_user_id
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $viewId]);
        $e = $stmt->fetch();

        if (!$e) {
            page('Enquiry not found', "<p>That enquiry does not exist.</p><p><a href='/?route=admin-enquiries'>Back</a></p>");
        }

        $flash = '';
        if ($msg !== '') $flash .= "<div class='card'><strong>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</strong></div>";
        if (!empty($errors)) {
            $lis = '';
            foreach ($errors as $er) $lis .= "<li>" . htmlspecialchars($er, ENT_QUOTES, 'UTF-8') . "</li>";
            $flash .= "<div class='card'><strong>Please fix:</strong><ul>$lis</ul></div>";
        }

        $status = ((int)$e['responded'] === 1) ? 'Responded' : 'Not responded';

        $respondInfo = '';
        if ((int)$e['responded'] === 1) {
            $respondInfo = "<p><strong>Responded by:</strong> " . htmlspecialchars($e['responded_by_username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</p>
                            <p><strong>Responded at:</strong> " . htmlspecialchars($e['responded_at'] ?? '', ENT_QUOTES, 'UTF-8') . "</p>";
        }

        $button = '';
        if ((int)$e['responded'] === 0) {
            $button = "
                <form method='post' action='/?route=admin-enquiries&id=" . (int)$e['id'] . "'>
                    <input type='hidden' name='action' value='mark-responded' />
                    <input type='hidden' name='id' value='" . (int)$e['id'] . "' />
                    <input type='submit' value='Mark as responded' />
                </form>
            ";
        }

        page('View enquiry', "
            <h1>Enquiry #" . (int)$e['id'] . "</h1>
            $flash

            <div class='card'>
                <p><strong>Status:</strong> " . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "</p>
                $respondInfo
                <p><strong>Name:</strong> " . htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($e['email'], ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Phone:</strong> " . htmlspecialchars((string)($e['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Course:</strong> " . htmlspecialchars($e['course_name'], ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($e['enquiry_text'], ENT_QUOTES, 'UTF-8')) . "</p>
                <p><strong>Created:</strong> " . htmlspecialchars($e['created_at'], ENT_QUOTES, 'UTF-8') . "</p>
            </div>

            $button

            <p><a href='/?route=admin-enquiries'>Back to enquiries</a></p>
            <p><a href='/?route=admin-dashboard'>Back to dashboard</a></p>
        ");
    }

    $enquiries = $pdo->query("
        SELECT id, name, email, course_name, responded, created_at
        FROM enquiries
        ORDER BY created_at DESC, id DESC
    ")->fetchAll();

    $flash = '';
    if ($msg !== '') $flash .= "<div class='card'><strong>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</strong></div>";
    if (!empty($errors)) {
        $lis = '';
        foreach ($errors as $er) $lis .= "<li>" . htmlspecialchars($er, ENT_QUOTES, 'UTF-8') . "</li>";
        $flash .= "<div class='card'><strong>Please fix:</strong><ul>$lis</ul></div>";
    }

    $rows = '';
    foreach ($enquiries as $e) {
        $status = ((int)$e['responded'] === 1) ? 'Responded' : 'Not responded';
        $rows .= "<tr>
            <td>" . (int)$e['id'] . "</td>
            <td>" . htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($e['email'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($e['course_name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($e['created_at'], ENT_QUOTES, 'UTF-8') . "</td>
            <td><a href='/?route=admin-enquiries&id=" . (int)$e['id'] . "'>View</a></td>
        </tr>";
    }

    page('Manage enquiries', "
        <h1>Manage enquiries</h1>
        $flash

        <!-- [AC NMP-0004] Admin can view enquiries + mark responded -->
        <table class='card' style='width:100%'>
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Course</th><th>Status</th><th>Created</th><th></th>
                </tr>
            </thead>
            <tbody>$rows</tbody>
        </table>

        <p><a href='/?route=admin-dashboard'>Back to dashboard</a></p>
    ");
}

function handle_admin_subject_areas_route(): void
{
    requireAdminOrSuperadmin();

    $pdo = db()->pdo();
    $errors = [];
    $success = '';

    $makeSlug = function (string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug === '' ? 'subject' : $slug;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_subject') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $errors[] = 'Subject name is required.';

        if (!$errors) {
            try {
                $slug = $makeSlug($name);

                $base = $slug;
                $i = 2;
                while (true) {
                    $check = $pdo->prepare("SELECT 1 FROM subject_areas WHERE slug = :slug LIMIT 1");
                    $check->execute([':slug' => $slug]);
                    if (!$check->fetchColumn()) break;
                    $slug = $base . '-' . $i;
                    $i++;
                }

                $stmt = $pdo->prepare("INSERT INTO subject_areas (name, slug) VALUES (:name, :slug)");
                $stmt->execute([':name' => $name, ':slug' => $slug]);

                $success = 'Subject area created.';
            } catch (Throwable $e) {
                $errors[] = 'Could not create subject area: ' . $e->getMessage();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_course') {
        $subjectId = (int)($_POST['subject_area_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['course_type'] ?? '');
        $duration = (int)($_POST['duration_years'] ?? 0);
        $desc = trim($_POST['description'] ?? '');

        if ($subjectId <= 0) $errors[] = 'Choose a subject area.';
        if ($title === '') $errors[] = 'Course title is required.';
        if ($type === '') $errors[] = 'Course type is required (BSc/MSc/PhD).';
        if ($duration <= 0) $errors[] = 'Duration must be a number of years.';
        if ($desc === '') $errors[] = 'Description is required.';

        if (!$errors) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (subject_area_id, title, type, length_years, description)
                    VALUES (:sid, :title, :type, :len, :desc)
                ");
                $stmt->execute([
                    ':sid'   => $subjectId,
                    ':title' => $title,
                    ':type'  => $type,
                    ':len'   => $duration,
                    ':desc'  => $desc,
                ]);

                $courseId = (int)$pdo->lastInsertId();
                header('Location: /?route=admin-course-modules&course_id=' . $courseId);
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Could not create course: ' . $e->getMessage();
            }
        }
    }

    $subjects = $pdo->query("SELECT id, name, slug FROM subject_areas ORDER BY name")->fetchAll();

    $courses = $pdo->query("
        SELECT c.id, c.title, c.type, c.length_years, s.name AS subject_name
        FROM courses c
        JOIN subject_areas s ON s.id = c.subject_area_id
        ORDER BY s.name ASC, c.id DESC
    ")->fetchAll();

    $courseRows = '';
    foreach ($courses as $c) {
        $courseRows .= "<tr>
            <td>" . (int)$c['id'] . "</td>
            <td>" . htmlspecialchars($c['subject_name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($c['type'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . (int)$c['length_years'] . "</td>
            <td>
                <a href='/?route=admin-course-modules&course_id=" . (int)$c['id'] . "'>Manage modules</a> |
                <a href='/?route=admin-course-edit&course_id=" . (int)$c['id'] . "'>Edit</a>
                <form method='post' action='/?route=admin-course-delete' style='display:inline; margin-left:8px;'>
                    <input type='hidden' name='course_id' value='" . (int)$c['id'] . "' />
                    <input type='submit' value='Delete' onclick=\"return confirm('Delete this course and its links to modules?');\" />
                </form>
            </td>
        </tr>";
    }

    $courseTable = "
        <h2>Manage courses</h2>
        <table class='card' style='width:100%'>
            <thead>
                <tr>
                    <th>ID</th><th>Subject</th><th>Title</th><th>Type</th><th>Years</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>$courseRows</tbody>
        </table>
    ";

    $errHtml = '';
    if ($errors) {
        $li = '';
        foreach ($errors as $er) $li .= '<li>' . htmlspecialchars($er, ENT_QUOTES, 'UTF-8') . '</li>';
        $errHtml = "<div class='card'><strong>Please fix:</strong><ul>$li</ul></div>";
    }

    $okHtml = $success ? "<div class='card'><strong>" . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . "</strong></div>" : '';

    $subjectList = '<ul>';
    foreach ($subjects as $s) {
        $subjectList .= "<li>" . htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') . " (<code>" . htmlspecialchars($s['slug'], ENT_QUOTES, 'UTF-8') . "</code>)</li>";
    }
    $subjectList .= '</ul>';

    $opts = "<option value=''>-- choose --</option>";
    foreach ($subjects as $s) {
        $opts .= "<option value='" . (int)$s['id'] . "'>" . htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') . "</option>";
    }

    page('Manage subject areas & courses', "
        <h1>Manage subject areas & courses</h1>

        $errHtml
        $okHtml

        <div class='card' style='max-width: 1100px; overflow: hidden; margin: 0 auto;'>
            <h2>Create subject area</h2>
            <form method='post' action='/?route=admin-subject-areas'>
                <input type='hidden' name='action' value='create_subject' />
                <label>Name</label>
                <input type='text' name='name' />
                <input type='submit' value='Create subject area' />
            </form>
        </div>

        <div class='card' style='max-width: 1100px; overflow: hidden; margin: 0 auto;'>
            <h2>Create course</h2>
            <form method='post' action='/?route=admin-subject-areas'>
                <input type='hidden' name='action' value='create_course' />
                <label>Subject area</label>
                <select name='subject_area_id'>$opts</select>

                <label>Course title</label>
                <input type='text' name='title' />

                <label>Type (e.g., BSc/MSc/PhD)</label>
                <input type='text' name='course_type' />

                <label>Duration (years)</label>
                <input type='text' name='duration_years' />

                <label>Description</label>
                <textarea name='description'></textarea>

                <input type='submit' value='Create course & add modules' />
            </form>
        </div>

        <h2>Existing subject areas</h2>
        $subjectList

        $courseTable

        <p><a href='/?route=admin-dashboard'>Back to dashboard</a></p>
    ");
}

function handle_admin_course_modules_route(): void
{
    requireAdminOrSuperadmin();

    $pdo = db()->pdo();
    $courseId = (int)($_GET['course_id'] ?? 0);

    if ($courseId <= 0) {
        page('Missing course', "<p>Missing course_id.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $stmt = $pdo->prepare("
        SELECT c.*, s.name AS subject_name
        FROM courses c
        JOIN subject_areas s ON s.id = c.subject_area_id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        page('Not found', "<p>Course not found.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $errors = [];
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove-module') {
        $moduleId = (int)($_POST['module_id'] ?? 0);
        if ($moduleId > 0) {
            $stmt = $pdo->prepare("DELETE FROM course_modules WHERE course_id = :cid AND module_id = :mid");
            $stmt->execute([':cid' => $courseId, ':mid' => $moduleId]);
            $success = 'Module removed from this course.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add-module') {
        $code = trim($_POST['module_code'] ?? '');
        $title = trim($_POST['module_title'] ?? '');
        $desc = trim($_POST['module_description'] ?? '');

        if ($code === '') $errors[] = 'Module code is required (e.g., CSY2002).';
        if ($title === '') $errors[] = 'Module title is required.';
        if ($desc === '') $errors[] = 'Module description is required.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT id FROM modules WHERE code = :c");
                $stmt->execute([':c' => $code]);
                $mod = $stmt->fetch();

                if (!$mod) {
                    $stmt = $pdo->prepare("
                        INSERT INTO modules (code, title, description)
                        VALUES (:c, :t, :d)
                    ");
                    $stmt->execute([':c' => $code, ':t' => $title, ':d' => $desc]);
                    $moduleId = (int)$pdo->lastInsertId();
                } else {
                    $moduleId = (int)$mod['id'];
                }

                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO course_modules (course_id, module_id)
                    VALUES (:cid, :mid)
                ");
                $stmt->execute([':cid' => $courseId, ':mid' => $moduleId]);

                $pdo->commit();
                $success = 'Module added to course.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Could not add module: ' . $e->getMessage();
            }
        }
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.code, m.title, m.description
        FROM modules m
        JOIN course_modules cm ON cm.module_id = m.id
        WHERE cm.course_id = :cid
        ORDER BY m.code
    ");
    $stmt->execute([':cid' => $courseId]);
    $mods = $stmt->fetchAll();

    $errHtml = '';
    if ($errors) {
        $li = '';
        foreach ($errors as $er) $li .= '<li>' . htmlspecialchars($er, ENT_QUOTES, 'UTF-8') . '</li>';
        $errHtml = "<div class='card'><strong>Please fix:</strong><ul>$li</ul></div>";
    }

    $okHtml = $success ? "<div class='card'><strong>" . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . "</strong></div>" : '';

    $modList = '<div>';
    foreach ($mods as $m) {
        $mid = (int)$m['id'];
        $modList .= "
            <div class='module-box' style='margin-bottom:14px;'>
                <strong>" . htmlspecialchars($m['code'], ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') . "</strong>
                <p>" . nl2br(htmlspecialchars($m['description'], ENT_QUOTES, 'UTF-8')) . "</p>

                <p style='margin-top:10px;'>
                    <a href='/?route=admin-module-edit&course_id=$courseId&module_id=$mid'>Edit</a>

                    <form method='post' action='/?route=admin-course-modules&course_id=$courseId' style='display:inline; margin-left:10px;'>
                        <input type='hidden' name='action' value='remove-module' />
                        <input type='hidden' name='module_id' value='$mid' />
                        <input type='submit' value='Remove from course' onclick=\"return confirm('Remove this module from the course?');\" />
                    </form>
                </p>
            </div>
        ";
    }
    $modList .= '</div>';

    page('Add modules', "
        <h1>Add modules</h1>
        <p><strong>Subject:</strong> " . htmlspecialchars($course['subject_name'], ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Course:</strong> " . htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') . "</p>

        $errHtml
        $okHtml

        <h2>Add a module</h2>
        <form method='post' action='/?route=admin-course-modules&course_id=$courseId'>
            <input type='hidden' name='action' value='add-module' />
            <label>Module code</label>
            <input type='text' name='module_code' />

            <label>Module title</label>
            <input type='text' name='module_title' />

            <label>Module description</label>
            <textarea name='module_description'></textarea>

            <input type='submit' value='Add module' />
        </form>

        <h2>Modules in this course</h2>
        $modList

        <p><a href='/?route=admin-subject-areas'>Back to subject areas</a></p>
        <p><a href='/?route=subject-areas&name=" . urlencode($course['subject_name']) . "'>View on site</a></p>
    ");
}

function handle_admin_course_edit_route(): void
{
    requireAdminOrSuperadmin();

    $pdo = db()->pdo();
    $courseId = (int)($_GET['course_id'] ?? 0);

    if ($courseId <= 0) {
        page('Missing course', "<p>Missing course_id.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        page('Not found', "<p>Course not found.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $errors = [];
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $type  = trim($_POST['type'] ?? '');
        $years = (int)($_POST['length_years'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if ($type === '') $errors[]  = 'Type is required.';
        if ($years <= 0) $errors[]   = 'Years must be a positive number.';
        if ($desc === '') $errors[]  = 'Description is required.';

        if (!$errors) {
            $upd = $pdo->prepare("
                UPDATE courses
                SET title = :t, type = :ty, length_years = :y, description = :d, updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':t'  => $title,
                ':ty' => $type,
                ':y'  => $years,
                ':d'  => $desc,
                ':id' => $courseId,
            ]);
            $success = 'Course updated.';

            $stmt->execute([':id' => $courseId]);
            $course = $stmt->fetch();
        }
    }

    $errHtml = '';
    if ($errors) {
        $li = '';
        foreach ($errors as $e) $li .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        $errHtml = "<div class='card'><strong>Please fix:</strong><ul>$li</ul></div>";
    }
    $okHtml = $success ? "<div class='card'><strong>" . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . "</strong></div>" : '';

    page('Edit course', "
        <h1>Edit course</h1>
        $errHtml
        $okHtml

        <div class='card'>
            <form method='post' action='/?route=admin-course-edit&course_id=$courseId'>
                <label>Course title</label>
                <input type='text' name='title' value='" . htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') . "' />

                <label>Type (e.g., BSc/MSc/PhD)</label>
                <input type='text' name='type' value='" . htmlspecialchars($course['type'], ENT_QUOTES, 'UTF-8') . "' />

                <label>Duration (years)</label>
                <input type='text' name='length_years' value='" . (int)$course['length_years'] . "' />

                <label>Description</label>
                <textarea name='description'>" . htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8') . "</textarea>

                <input type='submit' value='Save changes' />
            </form>
        </div>

        <p><a href='/?route=admin-course-modules&course_id=$courseId'>Manage modules</a></p>
        <p><a href='/?route=admin-subject-areas'>Back</a></p>
    ");
}

function handle_admin_course_delete_route(): void
{
    requireAdminOrSuperadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /?route=admin-subject-areas');
        exit;
    }

    $pdo = db()->pdo();
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($courseId > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM course_modules WHERE course_id = :id");
            $stmt->execute([':id' => $courseId]);

            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
            $stmt->execute([':id' => $courseId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            page('Delete failed', "<p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
        }
    }

    header('Location: /?route=admin-subject-areas');
    exit;
}

function handle_admin_module_edit_route(): void
{
    requireAdminOrSuperadmin();

    $pdo = db()->pdo();
    $courseId = (int)($_GET['course_id'] ?? 0);
    $moduleId = (int)($_GET['module_id'] ?? 0);

    if ($moduleId <= 0) {
        page('Missing module', "<p>Missing module_id.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = :id");
    $stmt->execute([':id' => $moduleId]);
    $mod = $stmt->fetch();

    if (!$mod) {
        page('Not found', "<p>Module not found.</p><p><a href='/?route=admin-subject-areas'>Back</a></p>");
    }

    $errors = [];
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim($_POST['code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($code === '') $errors[] = 'Module code is required.';
        if ($title === '') $errors[] = 'Module title is required.';
        if ($desc === '') $errors[] = 'Module description is required.';

        if (!$errors) {
            try {
                $upd = $pdo->prepare("
                    UPDATE modules
                    SET code = :c, title = :t, description = :d
                    WHERE id = :id
                ");
                $upd->execute([
                    ':c' => $code,
                    ':t' => $title,
                    ':d' => $desc,
                    ':id' => $moduleId,
                ]);
                $success = 'Module updated.';

                $stmt->execute([':id' => $moduleId]);
                $mod = $stmt->fetch();
            } catch (Throwable $e) {
                $errors[] = 'Could not update module: ' . $e->getMessage();
            }
        }
    }

    $errHtml = '';
    if ($errors) {
        $li = '';
        foreach ($errors as $e) $li .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        $errHtml = "<div class='card'><strong>Please fix:</strong><ul>$li</ul></div>";
    }
    $okHtml = $success ? "<div class='card'><strong>" . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . "</strong></div>" : '';

    $back = $courseId > 0
        ? "<p><a href='/?route=admin-course-modules&course_id=$courseId'>Back to course modules</a></p>"
        : "<p><a href='/?route=admin-subject-areas'>Back</a></p>";

    page('Edit module', "
        <h1>Edit module</h1>
        $errHtml
        $okHtml

        <div class='card'>
            <form method='post' action='/?route=admin-module-edit&course_id=$courseId&module_id=$moduleId'>
                <label>Module code</label>
                <input type='text' name='code' value='" . htmlspecialchars($mod['code'], ENT_QUOTES, 'UTF-8') . "' />

                <label>Module title</label>
                <input type='text' name='title' value='" . htmlspecialchars($mod['title'], ENT_QUOTES, 'UTF-8') . "' />

                <label>Module description</label>
                <textarea name='description'>" . htmlspecialchars($mod['description'], ENT_QUOTES, 'UTF-8') . "</textarea>

                <input type='submit' value='Save changes' />
            </form>
        </div>

        $back
    ");
}
