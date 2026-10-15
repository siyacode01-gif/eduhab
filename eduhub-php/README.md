# EduHub – Slides, Quizzes, Tests & Assignments
Built with **HTML + CSS + JavaScript** (front end), **PHP** (API) and **MySQL** (database).
Footer on every page: © 2026 @EduHub_Mpondo. All rights reserved.

## 1. Project structure
```
eduhub/
├── index.html      Login + Register page
├── teacher.html    Teacher dashboard (courses, slides, tests, marking)
├── learner.html    Learner dashboard (courses, slide viewer, AI, tests, grades)
├── admin.html      Administrator dashboard (users, unlock, reset password)
├── css/style.css   All styling
├── js/common.js    Shared helpers: api() fetch wrapper, toast, modal, escaping
├── js/auth.js      Login/register behaviour
├── js/teacher.js   Teacher dashboard behaviour
├── js/learner.js   Learner dashboard + AI summary + check quiz
├── js/admin.js     Admin dashboard behaviour
├── api.php         PHP JSON API (all database work happens here)
├── config.php      DB settings, lockout settings, AI key, helpers
└── schema.sql      MySQL database + tables
```
**How the layers talk:** HTML page → JavaScript `api('action',{data})` → `api.php?action=...` → MySQL (prepared statements) → JSON → JavaScript redraws the page.

## 2. Setup (XAMPP, beginner steps)
1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\eduhub`.
3. Open `http://localhost/phpmyadmin` → **Import** → select `schema.sql` → Go.
4. Check the DB settings at the top of `config.php` (XAMPP default: user `root`, empty password).
5. For AI: paste your Anthropic API key into `ANTHROPIC_KEY` in `config.php` (keep it private; never push it to GitHub). PHP `curl` must be enabled.
6. Open **http://localhost/eduhub/index.html** (do not double-click the file: PHP needs Apache).
7. Register, then make the first admin in phpMyAdmin → SQL:  
   `UPDATE users SET role='admin' WHERE email='you@example.com';`

## 3. Database (MySQL)
```
users ─┬─< courses ─┬─< slides ─< slide_progress >─ users
       │            ├─< enrollments >─ users
       │            └─< assessments ─< questions
       └─< submissions >─ assessments     (UNIQUE assessment_id + user_id = one attempt each)
```
`ON DELETE CASCADE` removes everything that belongs to a deleted course/user.

## 4. Add / edit / remove / act – who can do what
| Role | Add | Edit | Remove | Act |
|---|---|---|---|---|
| Teacher | courses, slides, quizzes/tests/assignments, questions | courses, slides, assessment title/due date | courses, slides, assessments, questions | mark submissions + feedback |
| Learner | enrolment, submissions | – | leave course | read slides, AI summary, check quiz, take tests |
| Admin | – | user role | users | unlock accounts, reset passwords |

## 5. API actions (`api.php?action=NAME`, POST JSON, header `X-CSRF`)
Public: `me, register, login, logout` · Teacher: `t_courses, course_save, course_del, t_course, slide_save, slide_del, as_save, as_del, q_add, q_del, t_subs, grade` ·
Learner: `l_courses, l_browse, enroll, unenroll, l_course, read, ai_summary, ai_quiz, l_tests, l_take, submit, l_grades` · Admin: `a_stats, a_users, u_role, u_del, u_unlock, u_reset`

## 6. Key features
* **3-attempt lockout:** `users.failed_attempts`; the 3rd wrong password sets `locked_until` (60 s, change `LOCK_SECONDS`). Admin can unlock early.
* **Admin password reset:** random temporary password, stored bcrypt-hashed, shown once.
* **Small test after two slides:** *Mark read* saves progress; on every 2nd newly-read slide the server replies `quiz:slideId` and the browser asks the AI for 2 questions (practice only).
* **AI summary:** button on each slide. AI calls are limited to one every 3 seconds per user.
* **Online tests + marking:** quizzes/tests are multiple choice and auto-marked (correct answers are never sent to the browser); assignments are written and marked by the teacher with feedback. Due dates enforced.

## 7. Security
bcrypt passwords, prepared statements (SQL injection), escaping on output (XSS), CSRF token on every POST, session regenerated on login, role + ownership checks on every action.
Add before real use: HTTPS, a non-root DB user, email-based password reset.

## 8. About "6,000,000 simultaneous users"
No single PHP app or XAMPP server can do this. It needs infrastructure: many app servers behind a load balancer, MySQL primary + read replicas (or sharding), Redis for sessions/cache, a CDN, queued AI calls, monitoring. The API is stateless apart from the PHP session, so it can be moved to that setup later.

## 9. Test checklist
Register teacher + learner → teacher: course, 4 slides, a quiz, an assignment → learner: enrol, read 2 slides (quiz pops up), submit both → teacher marks the assignment → learner sees grades → wrong password 3× (locked) → admin unlocks / resets.
