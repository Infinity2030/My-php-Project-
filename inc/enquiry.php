<?php

function handle_enquiry_route(): void
{
    $errors = [];
    $name = '';
    $email = '';
    $phone = '';
    $course = '';
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '') $errors[] = "Name is required.";
        if ($email === '') $errors[] = "Email is required.";
        if ($message === '') $errors[] = "Message is required.";

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO enquiries (name, email, phone, course_name, enquiry_text)
                        VALUES (:name, :email, :phone, :course_name, :enquiry_text)";

                $stmt = db()->pdo()->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => ($phone === '' ? null : $phone),
                    ':course_name' => $course,
                    ':enquiry_text' => $message,
                ]);

                page('Enquiry submitted', "
                    <h1>Thank you!</h1>
                    <p>Your enquiry has been submitted successfully.</p>
                    <p><a href='/?route=home'>Return Home</a></p>
                ");

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

    $subjectOptions = "<option value=''>-- choose a subject area --</option>";

    try {
        $rows = db()->pdo()->query("
            SELECT name
            FROM subject_areas
            ORDER BY name ASC
        ")->fetchAll();

        foreach ($rows as $r) {
            $value = (string)$r['name'];
            $selected = ($course === $value) ? "selected" : "";
            $subjectOptions .= "<option value='" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "' $selected>"
                             . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                             . "</option>";
        }

        if (count($rows) === 0) {
            $subjectOptions .= "<option value=''>No subject areas available yet</option>";
        }

    } catch (Throwable $e) {
        $subjectOptions .= "<option value='Computing'>Computing</option>";
    }


    page('Make an enquiry', "
        <h1>Make an enquiry</h1>
        $errorHtml

        <form action='/?route=enquiry' method='post'>
            <label>Your name</label>
            <input type='text' name='name' value='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' />

            <label>Email address</label>
            <input type='text' name='email' value='" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "' />

            <label>Phone number</label>
            <input type='text' name='phone' value='" . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "' />

            <label>Which course are you enquiring about?</label>
            <select name='course'>
                $subjectOptions
            </select>

            <label>What do you want to ask?</label>
            <textarea name='message'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</textarea>

            <input type='submit' value='Submit' />
        </form>
    ");
}
