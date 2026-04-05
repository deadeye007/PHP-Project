# Comprehensive Assignments System Guide

## Overview

The Learning Management System now includes a powerful, flexible assignments system that goes far beyond quizzes. Instructors can create various types of assignments with different submission and grading methods, while students can view, submit, and track their work across all courses.

## Features

### Assignment Types Supported

1. **📄 Essay/Text Submissions** - Students write answers directly in the platform
2. **📁 File Submissions** - Students upload documents, media, or project files
3. **🎯 Projects** - Larger scope assignments with files, descriptions, and detailed grading
4. **💬 Discussion Assignments** - Collaborative discussion-based assessments
5. **👥 Peer Review Assignments** - Students review and provide feedback on peers' work
6. **❓ Quizzes** - Multiple choice quizzes (legacy support)

### Grading Options

- **Points-Based** - Traditional numeric scoring
- **Pass/Fail** - Binary completion marking
- **Rubric-Based** - Multi-criteria evaluation with descriptors (Excellent, Good, Fair, Poor)

### Submission Features

- **Multiple Submissions** - Students can resubmit with version tracking
- **Configurable Deadlines** - Set submission deadlines with late submission penalties
- **File Upload Constraints** - Control file types, sizes, and uploads
- **Late Submission Handling** - Optional penalty points for late work

---

## Admin Interface

### Creating Assignments

1. Navigate to **Course Manager** → Select Course → Select Lesson → **Manage Assignments**
2. Fill in assignment details:
   - **Title**: Assignment name (e.g., "Midterm Essay")
   - **Description**: Full instructions and requirements
   - **Type**: Choose from 6 assignment types
   - **Grading Type**: Points, Pass/Fail, or Rubric-based
   - **Points Possible**: Maximum points for assignment
   - **Submission Deadline**: Optional date/time
3. Configure file upload settings if applicable
4. Click **Create Assignment**

### Editing Assignments

1. Go to **Admin Dashboard** → **Courses** → Select Course → Select Lesson
2. Find assignment in the list, click **Edit**
3. Modify any details including:
   - Assignment type
   - Grading method
   - Points possible
   - File upload settings
   - Publish/unpublish status
4. Save changes

### Managing Submissions

1. From assignment edit page, click **View All Submissions**
2. Dashboard shows:
   - Total submissions received
   - Number graded
   - Number pending
3. **Grade Individual Submissions**:
   - Click **Grade** button on student row
   - Modal opens with student info
   - Enter points earned (0-max possible)
   - Add feedback/comments
   - Click **Save Grade**
4. Student is automatically notified when grade is published

### Creating Rubrics (Future Enhancement)

Rubrics allow detailed criterion-based grading:
- Define multiple criteria (e.g., "Organization", "Clarity", "Accuracy")
- Set point values for each criterion
- Create performance levels within each criterion
- Instructors select level during grading

---

## Student Interface

### Viewing Assignments

1. Navigate to **Course** → **Assignments**
2. Assignments organized by status:
   - **📌 Due Soon** - Not yet submitted
   - **⏰ Overdue** - Missed deadline
   - **✅ Submitted** - Awaiting grades
   - **🎓 Graded** - Completed with feedback

### Submitting Assignments

1. Click on assignment for details
2. Choose submission method based on type:
   - **Essay/Discussion**: Type directly in text area
   - **File Submission**: Upload file with constraints shown
   - **Project**: Can include both text and files
3. Previously submitted content saved (can edit before final submit)
4. Click **Submit Assignment**
5. Confirmation and notification sent to instructor

### Viewing Feedback

1. From **Assignments** page, click graded assignment
2. View:
   - Points earned / Total points
   - Percentage grade
   - Instructor feedback
   - Any rubric scoring details

### Resubmitting Work

If allowed by instructor:
1. Click assignment again
2. Modify content/file
3. Click **Submit Assignment** (creates version 2, etc.)
4. Previous submissions remain visible
5. Instructor grades latest submission

---

## Database Schema

### Core Tables

**assignments** - Assignment definitions
- lesson_id, course_id, title, description
- assignment_type (enum: quiz, essay, file_submission, project, discussion, peer_review)
- points_possible, grading_type
- submission_deadline, allow_resubmission
- is_published, show_to_students

**submissions** - Student submissions
- assignment_id, user_id, submission_number
- text_content, file_path, file_name
- submitted_at, is_graded, days_late

**submission_grades** - Grades and feedback
- submission_id, grader_id (instructor)
- points_earned, final_points (after penalties)
- feedback_text, rubric_scores (JSON)
- is_published (draft vs. final)

**grading_rubrics** - Rubric templates
- assignment_id, title, total_points
- is_active

**rubric_criteria** - Individual rubric criteria
- rubric_id, title, points_possible
- order_num for sequencing

**rubric_levels** - Performance levels within criteria
- criteria_id, level_name (Excellent/Good/Fair/Poor)
- points value for each level

**peer_reviews** - Peer feedback
- assignment_id, submission_id, reviewer_id
- review_text, helpful_rating
- is_anonymous option

**submission_files** - File attachments
- submission_id, file_path, file_name
- uploaded_at, mime_type, file_size

---

## Workflows

### Typical Assignment Flow

**Instructor:**
1. Creates assignment in lesson
2. Configures submission settings, deadlines
3. Publishes assignment
4. Students receive notification
5. Monitor submissions dashboard
6. Grade each submission with feedback
7. Publish grades (or keep as draft)
8. Student notified of grade

**Student:**
1. Sees assignment in course
2. Reads instructions and requirements
3. Prepares work (essay, file, presentation)
4. Uploads/enters submission before deadline
5. Can resubmit if allowed
6. Receives message when graded
7. Views feedback and grade
8. Reflects on performance

### Late Submission Example

Assignment due: Friday 5 PM
- Student submits Monday 2 PM (3 days late)
- Assignment settings: 5% penalty per day late
- Points earned: 85/100
- Late penalty: 85 × (5% × 3 days) = 12.75 points
- Final grade: 85 - 12.75 = 72.25 points

---

## Features by Assignment Type

### 📄 Essay/Text Submission
- Direct text entry in browser
- Can include formatting/markup
- Character count display
- Plagiarism detection (future feature)

### 📁 File Submission
- Upload single or multiple files
- Configurable file type restrictions
- File size limits
- Version history tracking

### 🎯 Project
- Combines essay + files
- Longer deadlines typical
- Detailed grading rubrics common
- Supports peer review

### 💬 Discussion
- Text-based response
- Can have minimum word count
- Thread-based replies
- Peer engagement tracking

### 👥 Peer Review
- Student reviews others' work
- Anonymous review option
- Student provides structured feedback
- Helpful rating system
- Points awarded for reviewing

### ❓ Quiz (Legacy)
- Auto-graded multiple choice
- Immediate feedback
- Instant score calculation
- Attempt limiting

---

## Notification System Integration

When assignments are graded, students receive notification messages:

```
📝 Grade Posted: [Assignment Name]

Your assignment "[Title]" has been graded.

Points: 85 / 100 (85%)

Feedback:
[Instructor's feedback text here]
```

Notifications appear in student inbox and unread badge system.

---

## Best Practices

### For Instructors

✅ **DO:**
- Write clear, detailed instructions
- Set reasonable deadlines
- Provide rubrics for complex assignments
- Give specific, actionable feedback
- Grade promptly (within 48 hours preferred)

❌ **DON'T:**
- Create too many assignments (quality over quantity)
- Leave vague instructions
- Set very short deadlines
- Make submissions overly restrictive (file types)
- Forget to publish grades

### For Students

✅ **DO:**
- Read all assignment instructions carefully
- Start early, don't procrastinate
- Check file type/size restrictions before uploading
- Submit before deadline when possible
- Review feedback to improve

❌ **DON'T:**
- Submit at last second (risk of technical issues)
- Ignore file constraints
- Resubmit carelessly
- Plagiarize or submit others' work
- Ask for deadline extensions without reason

---

## Admin Controls

### Publishing Status

- **Draft**: Only instructor sees, not shown to students
- **Published**: Visible to students, can receive submissions
- **Published + Show to Students**: Full visibility and submission enabled

### Resubmission Settings

Per assignment, configure:
- Allow resubmissions: Yes/No
- Max resubmissions: Number or unlimited
- Each resubmission tracked as version

### Late Submission Policy

Configure for each assignment:
- Allow late submissions: Yes/No
- Penalty percentage: 0-100% per day
- Calculated automatically on submission

---

## Troubleshooting

**Q: Students can't see the assignment?**
A: Check that "Show to Students" is enabled AND assignment is published.

**Q: File upload failing?**
A: Check file type and size limits. Verify uploads/ directory is writable.

**Q: Grade appearing but student not notified?**
A: Grade must be published (not just saved as draft) to trigger notification.

**Q: Can't modify assignment after students submitted?**
A: Submitted assignments are locked. Create a new version if major changes needed.

**Q: Student wants to resubmit but option unavailable?**
A: Instructor setting "Allow Resubmission" is disabled, or max resubmissions reached.

---

## Future Enhancements

- Plagiarism detection integration
- Anonymous grading mode
- Peer review automatic assignment
- Rubric templates library
- Grade import/export
- Group assignments
- Collaborative editing
- Video submission support
- Gradebook integration with dashboard
