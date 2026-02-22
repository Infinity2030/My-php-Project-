<?php

function handle_course_search_route(): void
{
    $pdo = db()->pdo();


    $subjectRows = $pdo->query("SELECT id, name FROM subject_areas ORDER BY name ASC")->fetchAll();

    $subjectId = (int)($_GET['subject_area_id'] ?? 0);
    $subjectOptions = "<option value=''>-- any subject area --</option>";
    foreach ($subjectRows as $s) {
        $sid = (int)$s['id'];
        $sel = ($subjectId === $sid) ? "selected" : "";
        $subjectOptions .= "<option value='$sid' $sel>" . htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') . "</option>";
    }

    $typeRows = $pdo->query("
        SELECT DISTINCT type
        FROM courses
        WHERE type IS NOT NULL AND type <> ''
        ORDER BY type ASC
    ")->fetchAll();

    $type = trim($_GET['type'] ?? '');
    $typeOptions = "<option value=''>-- any type --</option>";
    foreach ($typeRows as $trow) {
        $t = (string)$trow['type'];
        $sel = ($type === $t) ? "selected" : "";
        $typeOptions .= "<option value='" . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . "' $sel>"
                      . htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
                      . "</option>";
    }

    $minYearsRaw = trim($_GET['min_years'] ?? '');
    $maxYearsRaw = trim($_GET['max_years'] ?? '');
    $minYears = ($minYearsRaw === '') ? null : (int)$minYearsRaw;
    $maxYears = ($maxYearsRaw === '') ? null : (int)$maxYearsRaw;


    $perPage = 10;
    $pageNum = max(1, (int)($_GET['page'] ?? 1));
    $offset  = ($pageNum - 1) * $perPage;

    $where = " WHERE 1=1 ";
    $params = [];

    if ($subjectId > 0) {
        $where .= " AND c.subject_area_id = :sid";
        $params[':sid'] = $subjectId;
    }

    if ($type !== '') {
        $where .= " AND c.type = :type";
        $params[':type'] = $type;
    }

    if ($minYears !== null) {
        $where .= " AND c.length_years >= :miny";
        $params[':miny'] = $minYears;
    }

    if ($maxYears !== null) {
        $where .= " AND c.length_years <= :maxy";
        $params[':maxy'] = $maxYears;
    }

    $countSql = "
        SELECT COUNT(*)
        FROM courses c
        $where
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCourses = (int)$countStmt->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalCourses / $perPage));

    if ($pageNum > $totalPages) {
        $pageNum = $totalPages;
        $offset  = ($pageNum - 1) * $perPage;
    }

    $sql = "
        SELECT c.id, c.title, c.type, c.length_years, c.description, s.name AS subject_name
        FROM courses c
        JOIN subject_areas s ON s.id = c.subject_area_id
        $where
        ORDER BY s.name ASC, c.title ASC
        LIMIT :lim OFFSET :off
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $courses = $stmt->fetchAll();

    $modulesByCourse = [];
    if ($courses) {
        $ids = array_map(fn($c) => (int)$c['id'], $courses);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $mstmt = $pdo->prepare("
            SELECT cm.course_id, m.id AS module_id, m.code, m.title, m.description
            FROM course_modules cm
            JOIN modules m ON m.id = cm.module_id
            WHERE cm.course_id IN ($placeholders)
            ORDER BY cm.course_id ASC, m.code ASC
        ");
        $mstmt->execute($ids);
        $rows = $mstmt->fetchAll();

        foreach ($rows as $r) {
            $cid = (int)$r['course_id'];
            if (!isset($modulesByCourse[$cid])) $modulesByCourse[$cid] = [];
            $modulesByCourse[$cid][] = $r;
        }
    }

    $resultsHtml = "";
    if (!$courses) {
        $resultsHtml = "<p>No courses match your search.</p>";
    } else {
        foreach ($courses as $c) {
            $cid = (int)$c['id'];
            $mods = $modulesByCourse[$cid] ?? [];

            $modsHtml = "";
            if (!$mods) {
                $modsHtml = "<p>No modules added yet.</p>";
            } else {
                foreach ($mods as $m) {
                    $modsHtml .= "
                        <div class='module-box'>
                            <strong>" . htmlspecialchars($m['code'], ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') . "</strong>
                            <p>" . nl2br(htmlspecialchars($m['description'], ENT_QUOTES, 'UTF-8')) . "</p>
                        </div>
                    ";
                }
            }

            $resultsHtml .= "
                <div class='course-box'>
                    <p style='margin:0 0 6px 0;'><strong>Subject area:</strong> " . htmlspecialchars($c['subject_name'], ENT_QUOTES, 'UTF-8') . "</p>
                    <h2>" . htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') . "</h2>
                    <p><strong>Type:</strong> " . htmlspecialchars($c['type'], ENT_QUOTES, 'UTF-8') . "</p>
                    <p><strong>Duration:</strong> " . (int)$c['length_years'] . " years</p>
                    <p>" . nl2br(htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8')) . "</p>

                    <h3>Modules</h3>
                    <div class='modules-wrap'>
                        $modsHtml
                    </div>
                </div>
            ";
        }
    }

    $links = "";
    if ($totalPages > 1) {
        $base = [
            'route' => 'course-search',
        ];
        if ($subjectId > 0) $base['subject_area_id'] = $subjectId;
        if ($type !== '') $base['type'] = $type;
        if ($minYearsRaw !== '') $base['min_years'] = $minYearsRaw;
        if ($maxYearsRaw !== '') $base['max_years'] = $maxYearsRaw;

        $links .= "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $qs = http_build_query($base + ['page' => $i]);
            $active = ($i === $pageNum) ? " class='active'" : "";
            $links .= "<a$active href='/?$qs'>$i</a>";
        }
        $links .= "</div>";
    }

    page('Course search', "
        <h1>Course search</h1>

        <div class='card' style='max-width:1100px; margin:0 auto;'>
            <form method='get' action='/'>
                <input type='hidden' name='route' value='course-search' />

                <label>Subject area</label>
                <select name='subject_area_id'>
                    $subjectOptions
                </select>

                <label>Type</label>
                <select name='type'>
                    $typeOptions
                </select>

                <label>Minimum duration (years)</label>
                <input type='text' name='min_years' value='" . htmlspecialchars($minYearsRaw, ENT_QUOTES, 'UTF-8') . "' />

                <label>Maximum duration (years)</label>
                <input type='text' name='max_years' value='" . htmlspecialchars($maxYearsRaw, ENT_QUOTES, 'UTF-8') . "' />

                <input type='submit' value='Search' />
            </form>
        </div>

        <div style='margin-top:20px;'>
            $resultsHtml
            $links
        </div>
    ");
}
