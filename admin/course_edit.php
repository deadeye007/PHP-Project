<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$course = null;
if (isset($_GET['id'])) {
    $course = getCourse($_GET['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeHTML($_POST['description']); // allow rich text HTML for course description
    $editor_mode = in_array($_POST['editor_mode'] ?? 'rich', ['rich', 'markdown']) ? $_POST['editor_mode'] : 'rich';

    global $pdo;
    if ($course) {
        // Update
        $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, editor_mode = ? WHERE id = ?");
        $stmt->execute([$title, $description, $editor_mode, $course['id']]);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO courses (title, description, instructor_id, editor_mode) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $description, $_SESSION['user_id'], $editor_mode]);
    }
    header('Location: courses.php');
    exit;
}

$title = $course ? 'Edit Course' : 'Add Course';
$content = '<h2>' . $title . '</h2>';
$content .= '<form method="post" id="course_form">';
$content .= '<div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($course['title'] ?? '') . '" required></div>';
$selected_mode = $course['editor_mode'] ?? EDITOR_DEFAULT_MODE;
$content .= '<div class="mb-3"><label for="editor_mode" class="form-label">Editor Mode</label><select class="form-control" id="editor_mode" name="editor_mode">';
$content .= '<option value="rich"' . ($selected_mode === 'rich' ? ' selected' : '') . '>Rich text (WYSIWYG)</option>';
$content .= '<option value="markdown"' . ($selected_mode === 'markdown' ? ' selected' : '') . '>Markdown</option>';
$content .= '</select></div>';
$content .= '<div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="5" required>' . htmlspecialchars($course['description'] ?? '') . '</textarea></div>';
$content .= '<p><small>Use the editor toolbar to switch to HTML source editing if desired.</small></p>';
$content .= '<button type="submit" class="btn btn-primary">Save</button> <a href="courses.php" class="btn btn-secondary">Cancel</a>';
$content .= '</form>';

$tinyMceApiKey = defined('TINYMCE_API_KEY') && TINYMCE_API_KEY !== '' ? TINYMCE_API_KEY : 'no-api-key';
$content .= '<script src="https://cdn.tiny.cloud/1/' . rawurlencode($tinyMceApiKey) . '/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>';
$content .= '<script>
function maybeInitTinyMce() {
    var editorMode = document.getElementById("editor_mode").value;
    var existingEditor = typeof tinymce !== "undefined" ? tinymce.get("description") : null;

    if (editorMode === "rich") {
        if (existingEditor) {
            return;
        }

        tinymce.init({
            selector: "#description",
            menubar: false,
            plugins: "link image lists code help",
            toolbar: "undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code | help",
            height: 300,
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
    maybeInitTinyMce();
});

document.getElementById("course_form").addEventListener("submit", function () {
    if (typeof tinymce !== "undefined") {
        tinymce.triggerSave();
    }
});

maybeInitTinyMce();
</script>';


include '../includes/header.php';
?>
