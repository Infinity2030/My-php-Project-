<?php

function handle_subject_areas_route(): void
{
    $name = $_GET['name'] ?? 'Computing';


    if ($name === 'Computing') {

        $file = __DIR__ . '/../as1.2/assignment/subject-area.html';

        if (!file_exists($file)) {
            page('Subject Areas', "<p>subject-area.html not found at: <code>" . htmlspecialchars($file) . "</code></p>");
        }

        $html = file_get_contents($file);


        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $main = $m[0];
        } else {
            $main = "<main><pre>" . htmlspecialchars($html) . "</pre></main>";
        }

        page($name, $main);
    }


    $pdo = db()->pdo();


    $stmt = $pdo->prepare("SELECT id, name FROM subject_areas WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $subject = $stmt->fetch();

    if (!$subject) {
        page('Subject Areas', "
            <h1>" . htmlspecialchars($name) . "</h1>
            <p>Subject area not found.</p>
        ");
    }

    $perPage = 10;
    $pageNum = max(1, (int)($_GET['page'] ?? 1));
    $offset  = ($pageNum - 1) * $perPage;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE subject_area_id = :sid");
    $countStmt->execute([':sid' => (int)$subject['id']]);
    $totalCourses = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalCourses / $perPage));
    if ($pageNum > $totalPages) $pageNum = $totalPages;

    $courseStmt = $pdo->prepare("
        SELECT id, title, type, length_years, description
        FROM courses
        WHERE subject_area_id = :sid
        ORDER BY id DESC
        LIMIT :lim OFFSET :off
    ");
    $courseStmt->bindValue(':sid', (int)$subject['id'], PDO::PARAM_INT);
    $courseStmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $courseStmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $courseStmt->execute();
    $courses = $courseStmt->fetchAll();

    $coursesHtml = "";

    foreach ($courses as $c) {

        $mstmt = $pdo->prepare("
            SELECT m.id, m.code, m.title, m.description
            FROM modules m
            JOIN course_modules cm ON cm.module_id = m.id
            WHERE cm.course_id = :cid
            ORDER BY m.code ASC
        ");
        $mstmt->execute([':cid' => (int)$c['id']]);
        $mods = $mstmt->fetchAll();

        $modsHtml = "";
        if (!$mods) {
            $modsHtml = "<p>No modules added yet.</p>";
        } else {
            foreach ($mods as $m) {
                $modsHtml .= "
                    <div class='module-box'>
                        <strong>" . htmlspecialchars($m['code']) . " - " . htmlspecialchars($m['title']) . "</strong>
                        <p>" . nl2br(htmlspecialchars($m['description'])) . "</p>
                    </div>
                ";
            }
        }

        $coursesHtml .= "
            <div class='course-box'>
                <h2>" . htmlspecialchars($c['title']) . "</h2>
                <p><strong>Type:</strong> " . htmlspecialchars($c['type']) . "</p>
                <p><strong>Duration:</strong> " . (int)$c['length_years'] . " years</p>
                <p>" . nl2br(htmlspecialchars($c['description'])) . "</p>

                <h3>Modules</h3>
                <div class='modules-wrap'>
                    $modsHtml
                </div>
            </div>
        ";
    }

    if ($coursesHtml === "") {
        $coursesHtml = "<p>No courses added yet for this subject area.</p>";
    }

    $links = "";
    if ($totalPages > 1) {
        $links .= "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $pageNum) ? " class='active'" : "";
            $links .= "<a$active href='/?route=subject-areas&name=" . urlencode($subject['name']) . "&page=$i'>$i</a>";
        }
        $links .= "</div>";
    }

    page($subject['name'], "
        <h1>" . htmlspecialchars($subject['name']) . "</h1>
        $coursesHtml
        $links
    ");
}
