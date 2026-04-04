# Notification & Announcement System Guide

## Overview

The Learning Management System now includes a comprehensive notification system that automatically sends messages to students and instructors for key learning events. All notifications appear in students' inboxes rather than relying on email.

## Automatic Notifications (Built-in)

### 1. Quiz Result Notifications
**When:** Automatically sent when a student completes and is graded on a quiz.
**Who receives:** The student who took the quiz
**What includes:** Quiz title, score, max score, percentage, pass/fail status

**Example:**
```
Your quiz result for "Unit 1 Quiz" in Biology 101
Score: 85 / 100 (85%)
Status: ✅ Passed

Visit your grades page to view more details.
```

### 2. Certificate Awards
**When:** Automatically sent when a student earns a certificate (usually on course completion)
**Who receives:** The student
**Example:** "🎓 Congratulations! You earned a certificate for [Course Name]"

### 3. Badge Awards
**When:** Automatically sent when a student earns an achievement badge
**Who receives:** The student
**Example:** "🏆 You unlocked a badge!"

## Manual Notifications (Admin Interface)

### Accessing the Admin Announcements Panel

1. Login as an admin user
2. Go to Admin Dashboard → **Announcements & Notifications**
3. Three tabbed options appear:

---

## Feature 1: Course Announcements

**Purpose:** Send a message to all students enrolled in a specific course

**How to use:**
1. Select "📚 Course Announcement" tab
2. Choose the course from dropdown
3. Enter announcement title (e.g., "Important Update")
4. Write your message in the content box
5. Click "Send Announcement"

**Who receives:** All students enrolled in the course (based on lesson progress)

**Message format:**
```
📢 Course Announcement: [Your Title]
New announcement in [Course Name]:
[Your content]
```

**Example use cases:**
- "Exam has been rescheduled to next Thursday"
- "Please review Chapter 5 before next week's quiz"
- "Congratulations on completing Unit 2!"

---

## Feature 2: Platform-Wide Announcements

**Purpose:** Send messages to specific user groups across the entire platform

**How to use:**
1. Select "🌐 Platform Announcement" tab
2. Choose your target audience:
   - **All Users** - Every user on the platform
   - **All Students** - All student accounts
   - **All Instructors** - All instructor accounts
3. Enter the subject line
4. Write your message
5. Click "Send Platform Announcement"

**Examples:**
- System maintenance notification
- New feature announcement
- Platform-wide policy update
- Achievement recognition

**Target audience use cases:**
- "All Students": "Updated quiz requirements for spring semester"
- "All Instructors": "New grading rubric guidelines"
- "All Users": "Scheduled maintenance tonight 11 PM - 1 AM"

---

## Feature 3: Grade Notifications

**Purpose:** Notify a student when you've manually posted or reviewed their grade

**How to use:**
1. Select "📝 Post Grade" tab
2. Select the student from dropdown
3. Select the lesson they completed
4. Enter their score (e.g., 45)
5. Enter max possible score (default 100)
6. Click "Send Grade Notification"

**What the student sees:**
```
📝 Grade Posted: [Lesson Name]
Your grade for "[Lesson Name]" in [Course Name] has been posted.

Score: 85 / 100 (85%)

Visit your grades page to view all your grades.
```

**If you include feedback:**
When posting grades through the admin gradebook interface with feedback, students receive:
```
Instructor Feedback:
[Your feedback text]
```

---

## Additional Notification Types (Function Reference)

These notifications are sent automatically in specific scenarios:

### Assignment Posted
```
notifyAssignmentPosted($course_id, $title, $description, $due_date, $instructor_id)
```
- Notifies all course students of new assignments
- Shows due date

### Assignment Feedback
```
notifyAssignmentFeedback($student_id, $title, $feedback, $score, $max_score)
```
- Sent when instructor provides feedback on assignment
- Shows score if provided

### Course Enrollment
```
notifyCourseEnrollment($user_id, $course_id, 'enrolled')
```
- Sent when student enrolls in course
- Includes course description

### Course Deadline Approaching
```
notifyCourseDeadlineApproaching($user_id, $course_id, $days)
```
- Reminder when course completion deadline is near
- Can be sent manually or via scheduled task

### Password Reset
```
notifyPasswordReset($user_id)
```
- Sent after password change
- Security reminder to report unauthorized changes

### Student At Risk Alert
```
notifyStudentAtRisk($instructor_id, $student_id, $course_id, $reason)
```
- Sent to instructors when student shows low grades/progress
- Includes reason for alert (e.g., "Score below 60%")

---

## Notification Icon Guide

| Icon | Meaning | Example |
|------|---------|---------|
| 📢 | Course Announcement | "Course Announcement: Exam rescheduled" |
| 📝 | Grade/Assignment | "Grade Posted: Lesson 1" |
| 🎓 | Certificate | "Congratulations! You earned a certificate" |
| 🏆 | Badge/Achievement | "You unlocked a badge!" |
| 📋 | Assignment Posted | "New Assignment: Research Paper" |
| 📧 | Feedback | "Feedback on Assignment: Report" |
| ✅ | Enrollment | "Welcome to: Biology 101" |
| ⏰ | Reminder | "Reminder: Course deadline approaching" |
| 🔐 | Security | "Your password has been changed" |
| ⚠️ | Warning | "Student At Risk: Low grades" |

---

## Checking Notifications

### Student Inbox
Students can access their messages in several ways:

1. **From Navigation:** Click "Messages" in the header (shows unread count badge)
2. **From Dashboard:** Link to messaging system
3. **From Profile:** View all messages in one central inbox

### Features
- **Tabs:** Switch between "Inbox" (received) and "Sent" (messages the student sent)
- **Unread Badge:** Red badge shows number of unread messages
- **Pagination:** 20 messages per page
- **Quick Actions:** Delete or view messages directly from inbox
- **Thread View:** Click a message to see full conversation history
- **Reply:** Quick reply form on each message

---

## Audit Logging

All announcements sent through the admin panel are logged to the **Audit Log** including:
- Who sent the announcement
- When it was sent
- Target audience/course
- Subject and content summary
- Number of recipients

Access: Admin Dashboard → **Security & Maintenance** → View Audit Log

---

## Best Practices

### ✅ DO:
- Use announcements for time-sensitive information
- Send quiz/grade notifications promptly after grading
- Use appropriate emojis/icons for clarity
- Review audit log to track messaging activity
- Keep messages concise and actionable

### ❌ DON'T:
- Send duplicate announcements (use bulk message feature instead)
- Include sensitive personal information in platform announcements
- Use announcements for routine grade posting (auto-notifications handle this)
- Send too many messages (may cause notification fatigue)

---

## Technical Details

### Database Tables
- `messages` - Stores all system and user messages
- `message_threads` - Optional threaded discussion organization
- `audit_log` - Tracks all notification activity

### Message Status Tracking
- All messages tracked with read/unread status
- Automatically marked as read when student views
- Unread count available in header badge
- Multiple viewing doesn't trigger duplicates

### Performance
- Messages paginated at 20 per page
- Optimized queries for large student populations
- Bulk messaging uses single transaction per recipient
- No email required - instant platform notification

---

## Troubleshooting

**Students not receiving course announcements?**
- Verify students are enrolled in the course (have lesson progress)
- Check inbox - message may be there with unread badge

**Admin page won't load?**
- Must be logged in as admin user
- Check browser console for errors

**No students show in bulk message?**
- Ensure course has students with lesson progress
- Student enrollment tracked through lesson steps

**Messages sending to wrong recipients?**
- Verify course selection matches intended students
- Check audit log to see who was messaged

---

## Integration with Other Systems

**Quiz Grading:**
- Quiz results automatically notify students within seconds
- No additional action needed

**Certificates & Badges:**
- Automatic system message when award given
- Message includes achievement description

**User Management:**
- Password reset triggers security notification
- New enrollment triggers welcome message

**Future Integration Points:**
- Assignment submission system
- Course deadline scheduling
- Performance-based alerts
- Custom notification rules
