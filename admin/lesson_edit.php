<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$lesson = null;
$course_id = $_GET['course_id'] ?? null;

if (isset($_GET['id'])) {
    $lesson = getLesson($_GET['id']);
    $course_id = $lesson ? $lesson['course_id'] : $course_id; // If editing, course_id from lesson
}

$course = null;
if ($course_id) {
    $course = getCourse($course_id);
}

$default_editor_mode = $course['editor_mode'] ?? EDITOR_DEFAULT_MODE;
$lesson_editor_mode = $lesson['editor_mode'] ?? 'inherit';

if ($lesson_editor_mode === 'inherit') {
    $lesson_editor_mode = $default_editor_mode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $editor_mode = in_array($_POST['editor_mode'] ?? 'rich', ['rich', 'markdown']) ? $_POST['editor_mode'] : 'rich';
    $raw_content = $_POST['content'];
    if ($editor_mode === 'markdown' && defined('ENABLE_MARKDOWN') && ENABLE_MARKDOWN) {
        $content_text = markdownToHtml($raw_content);
    } else {
        $content_text = sanitizeHTML($raw_content);
    }
    $order_num = (int)$_POST['order_num'];
    $target_course_id = $_POST['course_id'];

    global $pdo;
    if ($lesson) {
        // Update
        $stmt = $pdo->prepare("UPDATE lessons SET title = ?, content = ?, order_num = ?, editor_mode = ? WHERE id = ?");
        $stmt->execute([$title, $content_text, $order_num, $editor_mode, $lesson['id']]);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, order_num, editor_mode) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$target_course_id, $title, $content_text, $order_num, $editor_mode]);
    }
    header('Location: lessons.php?course_id=' . $target_course_id);
    exit;
}

$title = $lesson ? 'Edit Lesson' : 'Add Lesson';
$content = '<h2>' . $title . '</h2>';
$content .= '<form method="post" id="lesson_form">';

if (!$lesson) {
    $courses = getCourses();
    $content .= '<div class="mb-3"><label for="course_id" class="form-label">Course</label><select class="form-control" id="course_id" name="course_id" required>';
    foreach ($courses as $c) {
        $selected = ($c['id'] == $course_id) ? 'selected' : '';
        $content .= '<option value="' . $c['id'] . '" ' . $selected . '>' . htmlspecialchars($c['title']) . '</option>';
    }
    $content .= '</select></div>';
} else {
    $content .= '<input type="hidden" name="course_id" value="' . $course_id . '">';
}

$content .= '<div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($lesson['title'] ?? '') . '" required></div>';
$content .= '<div class="mb-3"><label for="order_num" class="form-label">Order</label><input type="number" class="form-control" id="order_num" name="order_num" value="' . ($lesson['order_num'] ?? 1) . '" required></div>';
$content .= '<div class="mb-3"><label for="editor_mode" class="form-label">Editor Mode</label><select class="form-control" id="editor_mode" name="editor_mode">';
$content .= '<option value="rich"' . ($lesson_editor_mode === 'rich' ? ' selected' : '') . '>Rich text (WYSIWYG)</option>';
$content .= '<option value="markdown"' . ($lesson_editor_mode === 'markdown' ? ' selected' : '') . '>Markdown</option>';
$content .= '</select></div>';
$content .= '<div class="mb-3"><label for="content" class="form-label">Content</label><textarea class="form-control" id="content" name="content" rows="10" required>' . htmlspecialchars($lesson['content'] ?? '') . '</textarea></div>';
$content .= '<p><small>Use the editor toolbar to switch to HTML source editing if desired.</small></p>';
$content .= '<button type="submit" class="btn btn-primary">Save</button> <a href="lessons.php?course_id=' . $course_id . '" class="btn btn-secondary">Cancel</a>';
$content .= '</form>';

// Add assignments link if lesson exists
if ($lesson) {
    $content .= '<div class="mt-4"><div class="card"><div class="card-header"><h5 class="mb-0">📝 Assignments</h5></div><div class="card-body">';
    $lesson_assignments = getLessonAssignments($lesson['id']);
    $content .= '<p>This lesson has ' . count($lesson_assignments) . ' assignment(s).</p>';
    $content .= '<a href="assignments.php?lesson_id=' . $lesson['id'] . '" class="btn btn-primary">Manage Assignments</a>';
    $content .= '</div></div></div>';
}

$tinyMceApiKey = defined('TINYMCE_API_KEY') && TINYMCE_API_KEY !== '' ? TINYMCE_API_KEY : 'no-api-key';
$content .= '<script src="https://cdn.tiny.cloud/1/' . rawurlencode($tinyMceApiKey) . '/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>';
$content .= '<script>
function maybeInitTinyMceLesson() {
    var editorMode = document.getElementById("editor_mode").value;
    var existingEditor = typeof tinymce !== "undefined" ? tinymce.get("content") : null;

    if (editorMode === "rich") {
        if (existingEditor) {
            return;
        }

        tinymce.init({
            selector: "#content",
            menubar: false,
            plugins: "link image lists code help",
            toolbar: "undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code | help",
            height: 400,
            setup: function (editor) {
                editor.on("change keyup", function () {
                    editor.save();
                });
            }
        });
    } else if (existingEditor) {
        existingEditor.save();
        existingEditor.remove();
    }
}

document.getElementById("editor_mode").addEventListener("change", function () {
    maybeInitTinyMceLesson();
});

document.getElementById("lesson_form").addEventListener("submit", function () {
    if (typeof tinymce !== "undefined") {
        tinymce.triggerSave();
    }
});

maybeInitTinyMceLesson();
</script>';


include '../includes/header.php';
?>
