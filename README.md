# Enhancement of CCDI's Learning Management System (`ccdi_lms`)

A web-based Learning Management System for CCDI featuring real-time student activity tracking, instant notifications and announcements, class schedules/live sessions, and role-based portals for **Admin**, **Teacher**, and **Student**.

## Capstone Members
- Aldrin Baria
- Jonnel Cereza
- Neil Lovedioro
- Qvy Brayan Botero

## Features
- Role-Based Portals: Separate dashboards and workflows for Admin, Teacher, and Student.
- Activity & Progress Tracking: Teachers can post activities with deadlines and attachments; students can open files, track reading/scroll progress (`1%–100%`), mark activities as done, or submit answers/files through the system.
- Instant Notifications & Announcements: Real-time role-badged announcements (`Teacher` / `Admin`) and activity alerts filtered by program and block.
- Live Sessions & Room Schedules: Supports both Face-to-Face and Online session schedules.

## Tech Stack
- Frontend: HTML5, CSS3, JavaScript (Vanilla JS / Fetch API)
- Backend: PHP 8+
- Database: MySQL (`ccdi_lms`)
- Local Server: XAMPP (Apache & MySQL)

## Installation & Setup Instructions (Local XAMPP)
1. Copy Project Files:
   - Place the project folder inside `C:\xampp\htdocs\` (e.g., `C:\xampp\htdocs\ccdi_lms\`).
2. Start XAMPP Services:
   - Open XAMPP Control Panel and start Apache and MySQL.
3. Import the Database:
   - Open `http://localhost/phpmyadmin` in your browser.
   - Create a new database named `ccdi_lms`.
   - Select `ccdi_lms`, go to the Import tab, choose the included SQL file (`ccdi-lms.sql` / `ccdi_lms.sql`), and click Import / Go.
4. Configure Database Connection (if needed):
   - Check `db.php` and ensure the credentials match your local MySQL setup (`host: localhost`, `user: root`, `password: ""`, `database: ccdi_lms`).
5. Run the System:
   - Open your browser and go to `http://localhost/ccdi_lms/login.php`.
