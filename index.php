<?php
require_once 'includes/functions.php';

$title = 'Home';
$content = '
    <h2>Welcome to the Learning Platform</h2>
    <p>Explore courses, track your progress, and learn at your own pace.</p>
    <div class="row">
        <div class="col-md-6">
            <h3>Featured Courses</h3>
            <ul class="list-group">
                <li class="list-group-item">Introduction to PHP</li>
                <li class="list-group-item">Web Development Basics</li>
                <li class="list-group-item">Database Design</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h3>Why Choose Us?</h3>
            <ul>
                <li>Interactive lessons</li>
                <li>Progress tracking</li>
                <li>Accessible design</li>
                <li>Light/Dark mode support</li>
            </ul>
        </div>
    </div>
';

include 'includes/header.php';
?>