# KISMEC Intern Task & Project Monitoring System

A production-ready **Intern Task & Project Monitoring System** engineered with a modern **Glassmorphism UI/UX**, strict timestamped workflow state machine, media evidence auditing, real-time SLA timeline monitoring, and print-ready A4 PDF report generation via **Dompdf**.

---

## 1. Tech Stack & Compatibility

- **Backend:** PHP 8.x using **PDO** (`ATTR_EMULATE_PREPARES => false`, strict session role authentication).
- **Database:** Dual-compatible SQL schema & queries for **XAMPP MariaDB 10.4.32** and **MySQL 5.7+ / 8.0 (cPanel)**:
  - `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
  - Zero `CHECK` constraints, CTEs (`WITH`), or window functions for legacy MySQL 5.7 compatibility.
  - Enforced `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'` on PDO initialization.
  - System Timezone: `Asia/Kuala_Lumpur` (MYT GMT+08:00).
- **Frontend:** Semantic HTML5, custom **Glassmorphism CSS3** (`backdrop-filter: blur(12px)`, translucent cards `rgba(255,255,255,0.08)`, `1px solid rgba(255,255,255,0.18)` borders over deep slate/indigo mesh), and Vanilla JavaScript **Fetch API (AJAX)** for zero-reload operations.
- **Responsive Mobile UX:** Viewport meta-tags, responsive glass tables with horizontal scroll wrappers, and fluid grid layouts optimized for mobile devices and tablets.
- **PDF Generation:** **Dompdf 3.x** for A4 print-ready reports with in-line 3-images-per-line photo grids, clickable video URLs, in-memory WebP image support, and formal Supervisor Sign-off blocks.
- **Multibyte Compatibility:** Integrated `mbstring` polyfill (`includes/mbstring_polyfill.php`) ensuring complete cross-platform stability on Linux/cPanel servers without requiring manual shell packages.
- **Date Picker:** **Flatpickr (Dark Glass theme)** with custom **"Apply" action buttons** adhering to the Mandatory Date Input Standard.

---

## 2. Directory Structure

```text
Intern/
├── api/
│   ├── create_task.php          # AJAX: Create new task (Supervisor/Admin)
│   ├── delete_task.php          # AJAX: Permanently delete task & attached media (Admin)
│   ├── delete_task_log.php      # AJAX: Delete specific audit log entry & attached files (Admin)
│   ├── get_task_report.php      # AJAX: Fetch full audit data & SLA metrics
│   ├── manage_user.php          # AJAX: User CRUD & active/inactive toggle
│   ├── review_budget.php        # AJAX: Approve / reject budget requests
│   ├── update_task_log.php      # AJAX: Edit audit trail remarks & references (Admin)
│   └── update_task_stage.php    # AJAX: State transitions & multiple media upload
├── assets/
│   ├── css/
│   │   └── glass.css            # Complete Glassmorphism Design System & responsive styles
│   └── js/
│       └── app.js               # Zero-reload AJAX logic, modals, Flatpickr binding, delete handlers
├── config/
│   ├── db.php                   # Simple manual PDO database configuration (Local/Production)
│   └── setting.php              # Centralized environment URLs, upload paths & app constants
├── database/
│   └── schema.sql               # 5 normalized tables + seeded users & sample data
├── includes/
│   ├── auth.php                 # Session security, role guards, CSRF & sanitation
│   ├── footer.php               # Global footer & script tags
│   ├── header.php               # Global Glassmorphic navbar & stylesheets
│   ├── mbstring_polyfill.php    # Pure PHP fallback polyfill for mbstring functions & constants
│   └── report_helper.php        # SLA calculations, UI badges & task aggregator
├── uploads/                     # Subfolder for all uploaded media evidence
│   └── temp/                    # Writable font cache & temporary files for Dompdf
├── vendor/                      # Composer dependencies (Dompdf & FontLib)
├── budgets.php                  # Budget requests & financial governance page
├── composer.json                # Composer configuration for Dompdf
├── dashboard.php                # Operations Command Dashboard & KPI metrics (with Task Deletion)
├── export_report_pdf.php        # Dompdf A4 report generator (In-line 3-images per line layout)
├── index.php                    # Glassmorphism login portal with demo autofill
├── logout.php                   # Secure session termination
├── reports.php                  # Interactive Task Report Viewer & Live Preview (with Log Edit/Delete)
├── tasks.php                    # Task portfolio, filters, multi-file upload & assignment
└── users.php                    # User & Intern directory (Admin)
```

---

## 3. Configuration & Database Setup

### A. Database Settings (`config/db.php`)
Database credentials can be adjusted manually right at the top of `config/db.php`:

```php
$dbHost = 'localhost';
$dbName = 'intern';                  // Local: 'intern' | cPanel: 'kismec_project_management'
$dbUser = 'root';                    // Local: 'root'   | cPanel: 'kismec_project_management'
$dbPass = '';                        // Local: ''       | cPanel: 'kismec_project_management'
$dbPort = '3306';
```

### B. System Settings & Upload Paths (`config/setting.php`)
- **Base URL:** Automatically detects your local host or live production domain (`https://jupiter.kismec.org.my/project_management`). Can also be manually defined.
- **Upload Directory:** Configured directly to the `uploads/` subfolder in the project root:
  - Disk Path: `ROOT_PATH . '/uploads'`
  - Web URL: `BASE_URL . '/uploads'`
- **Currency Constant:** `APP_CURRENCY = 'RM'` (avoids conflicts with Linux PHP's reserved POSIX `CURRENCY_SYMBOL` integer constant `262145`).

---

## 4. Key Functional Features

### 1. Operations Command Dashboard (`dashboard.php`)
- Real-time SLA compliance tracking (Overdue, Due Soon, On Track).
- Lifecycle state pipeline counters.
- Budget expenditure and disbursement KPI card.
- **Active Task Portfolio:** Includes **Update Stage**, **View Report**, and **Delete Task** (Admin-only with confirmation modal and smooth row deletion animation).

### 2. Multi-Evidence Media Dropzone (`tasks.php` & `dashboard.php`)
- Interns and supervisors can attach **multiple images (JPG, PNG, WEBP)** and **videos (MP4, MOV)** simultaneously during stage transitions.
- Displays interactive image previews and removable file pills prior to upload.

### 3. Print-Ready A4 PDF Audit Report (`export_report_pdf.php`)
- **In-Line 3 Images Per Row Layout:** Formats milestone evidence into a 3-column table (`33.33%` per column across the printable width of A4).
- **WebP & Image Transcoding:** Automatically converts `.webp` uploads to standard JPEG base64 strings in memory to ensure 100% native rendering in Dompdf without requiring external server libraries.
- **Zero-Network Execution:** Operates completely via local disk lookups (`isRemoteEnabled = false`) to prevent firewall loopback freezes on cPanel.
- **Built-in Diagnostics:** Features error-trapping (`try/catch`) and on-screen diagnostic reporting.

### 4. Audit Trail Integrity & Administration (`reports.php`)
- Chronological timeline tracking every milestone transition, actor, remarks, and reference links.
- **Admin Control:** Administrators can edit remarks/reference URLs or permanently delete individual audit trail entries and their associated disk files.

---

## 5. Seeded Default Accounts & Multi-Level Hierarchy

### Multi-Level Organizational Structure
The system implements a flexible hierarchical reporting and supervision model:
`Manager` &rarr; `Head of Department (HOD)` &rarr; `Supervisor` &rarr; `Staff / Intern`

- **Dual-Perspective Task Portfolio:** Users in managerial or supervisory positions (such as Supervisors or HODs) experience a dual perspective:
  - **Assigned to Me (My Tasks):** Tasks assigned to them by their superiors to execute.
  - **Supervised by Me:** Tasks they assigned to their subordinates to monitor, review, approve budgets, and sign off.
- **Segregation of Duties:** An assignee can submit plans, log milestone updates, and request budgets, but cannot approve their own budgets or sign off their own tasks.

| Role | Email | Password | Full Name | Department | Reports To |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin@kismec.org.my` | `Admin@123` | System Administrator | Centre Management & IT | — |
| **Manager** | `manager@kismec.org.my` | `Manager@123` | Ts. Mohd Fairuz Bin Ariffin | Technical & Engineering Division | — |
| **HOD** | `hod_td9@kismec.org.my` | `Hod@123` | Dr. Norazlina Binti Hashim | TD9 | Manager |
| **Supervisor** | `azwin@kismec.org.my` | `Supervisor@123` | Azwin Bin Mohd | TD9 | HOD |
| **Supervisor** | `supervisor@kismec.org.my` | `Supervisor@123` | Ir. Ahmad Zulkifli Bin Othman | Smart Automation & IoT | — |
| **Staff** | `aminah@kismec.org.my` | `Staff@123` | Siti Aminah Binti Yusof | Training & Technical Operations | — |
| **Intern 1** | `intern@kismec.org.my` | `Intern@123` | Nurul Aisyah Binti Razak | Smart Automation & IoT | Ir. Ahmad |
| **Intern 2** | `danial@kismec.org.my` | `Intern@123` | Muhammad Danial Bin Hafiz | Smart Automation & IoT | Ir. Ahmad |
| **Intern 3** | `kamal@kismec.org.my` | `Intern@123` | Kamal Hidayat | TD1 | Azwin |

*Quick-click demo autofill buttons are available directly on the login page (`index.php`).*

---

## 6. Task Lifecycle State Machine

```text
[ASSIGNED] (Supervisor creates task)
    │
    ▼ (Intern submits execution plan)
[PLANNING]
    ├─────────────────────────────────────────┐
    │ (Optional: Budget required)              │ (No funds required)
    ▼                                         │
[BUDGET_PENDING]                              │
    ├─────────────┬─────────────┐             │
    ▼             ▼             ▼             │
[BUDGET_APPROVED]  [PLANNING]                  │
    │             (Rejected)                  │
    └─────────────────────────────────────────┼────────────────┐
                                              ▼                ▼
                                        [IN_EXECUTION] ◄───────┘
                                              │ (Upload multiple evidence files)
                                              ▼ (Intern submits work)
                                    [COMPLETED_PENDING_REVIEW]
                                              ├────────────────────────┐
                                              ▼                        ▼
                                     [REVISION_REQUIRED]          [APPROVED]
                                     (Supervisor feedback)      (Supervisor sign-off)
```

---

## 7. Mandatory Date Input Standard

Every `<input>` for dates across the application adheres to:
1. Wrapped in a `.glass-date-group` container with an inline SVG calendar icon button (`.glass-date-toggle` with `data-toggle`).
2. Flatpickr features an **"Apply"** button allowing users to browse and explicitly confirm their selection.
3. Automatically defaults to today (`data-default-today="true"`) for new entries while preserving existing database values when editing.
4. Uses `altInput` for human-friendly display (`d M Y` or `d M Y, h:i K`), while submitting standard MySQL formats (`Y-m-d` or `Y-m-d H:i:s`).

```html
<div class="glass-date-group">
  <input type="text" name="due_date" id="due_date" class="glass-input flatpickr-input" placeholder="Select due date" data-enable-time="true" data-default-today="true" required>
  <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </button>
</div>
```

---

## 8. Production Server Deployment Checklist (cPanel)

When uploading to your live cPanel server (`https://jupiter.kismec.org.my/project_management`):

1. **Upload Files:**
   - Upload the project files into your cPanel directory (e.g. `public_html/project_management/`).
   - Ensure the `uploads/` and `vendor/` folders are included.
2. **Directory Permissions:**
   - Set the `uploads/` folder and `uploads/temp/` folder permissions to **`0755`** (or `0777` if required by your web host) so that media uploads and PDF font cache can be written.
3. **Database Configuration:**
   - Open `config/db.php` and set `$dbName`, `$dbUser`, and `$dbPass` to match your cPanel MySQL database.
   - In **cPanel > MySQL Databases**, ensure the user is added to the database with **ALL PRIVILEGES** ticked.
4. **PHP Extensions:**
   - In **cPanel > Select PHP Version > Extensions**, ensure the following extensions are enabled:
     - `pdo_mysql`
     - `mbstring` *(recommended; fallback polyfill is included in `includes/mbstring_polyfill.php`)*
     - `gd` *(for image rendering)*
     - `fileinfo`
5. **Access Application:**
   - Navigate to `https://jupiter.kismec.org.my/project_management/` and log in with your administrative credentials.
