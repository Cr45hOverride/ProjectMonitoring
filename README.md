# KISMEC Intern Task & Project Monitoring System

A production-ready **Intern Task & Project Monitoring System** engineered with a modern **Glassmorphism UI/UX**, strict timestamped workflow state machine, media evidence auditing, real-time SLA timeline monitoring, and print-ready A4 PDF report generation via **Dompdf**.

---

## 1. Tech Stack & Compatibility
- **Backend:** PHP 8.x using **PDO** (`ATTR_EMULATE_PREPARES => false`, strict session role authentication).
- **Database:** Dual-compatible SQL schema & queries for **XAMPP MariaDB 10.4.32** and **MySQL 5.7.44-cll-lve (cPanel)**:
  - `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
  - Zero `CHECK` constraints, CTEs (`WITH`), or window functions for legacy MySQL 5.7 compatibility.
  - Enforced `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'` on PDO initialization.
  - Timezone: `Asia/Kuala_Lumpur` (MYT +08:00).
- **Frontend:** Semantic HTML5, custom **Glassmorphism CSS3** (`backdrop-filter: blur(12px)`, translucent cards `rgba(255,255,255,0.08)`, `1px solid rgba(255,255,255,0.18)` borders over deep slate/indigo mesh), and Vanilla JavaScript **Fetch API (AJAX)** for zero-reload operations.
- **PDF Generation:** **Dompdf 3.x** for A4 print-ready reports with embedded images, clickable video URLs, and formal Supervisor Sign-off blocks.
- **Date Picker:** **Flatpickr (Dark Glass theme)** adhering to the **Mandatory Date Input Standard**.

---

## 2. Directory Structure

```text
c:\xampp\htdocs\Intern\
├── api/
│   ├── create_task.php          # AJAX: Create new task (Supervisor/Admin)
│   ├── get_task_report.php      # AJAX: Fetch full audit data & SLA metrics
│   ├── manage_user.php          # AJAX: User CRUD & active/inactive toggle
│   ├── review_budget.php        # AJAX: Approve / reject budget requests
│   └── update_task_stage.php    # AJAX: State transitions & media evidence upload
├── assets/
│   ├── css/
│   │   └── glass.css            # Complete Glassmorphism Design System
│   └── js/
│       └── app.js               # Zero-reload AJAX logic, modals, Flatpickr binding
├── config/
│   ├── db.php                   # Dual-compatible PDO database connection
│   └── setting.php              # Centralized environment URLs, paths & constants
├── database/
│   └── schema.sql               # 5 normalized tables + seeded users & sample data
├── includes/
│   ├── auth.php                 # Session security, role guards, CSRF & sanitation
│   ├── footer.php               # Global footer & script tags
│   ├── header.php               # Global Glassmorphic navbar & stylesheets
│   └── report_helper.php        # SLA calculations, UI badges & task aggregator
├── uploads/
│   └── evidence/                # Structured media storage: /YYYY/MM/
├── vendor/                      # Composer dependencies (Dompdf)
├── budgets.php                  # Budget requests & financial governance page
├── composer.json                # Composer configuration for Dompdf
├── dashboard.php                # Operations Command Dashboard & KPI metrics
├── export_report_pdf.php        # Dompdf A4 report generator
├── index.php                    # Glassmorphism login portal with demo autofill
├── logout.php                   # Secure session termination
├── reports.php                  # Interactive Task Report Viewer & Live Preview
├── tasks.php                    # Task portfolio, filters, and assignment
└── users.php                    # User & Intern directory (Admin)
```

---

## 3. Seeded Default Accounts

| Role | Email | Password | Full Name | Department |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin@kismec.org.my` | `Admin@123` | System Administrator | Centre Management & IT |
| **Supervisor** | `supervisor@kismec.org.my` | `Supervisor@123` | Ir. Ahmad Zulkifli Bin Othman | Smart Automation & IoT |
| **Intern 1** | `intern@kismec.org.my` | `Intern@123` | Nurul Aisyah Binti Razak | Smart Automation & IoT |
| **Intern 2** | `danial@kismec.org.my` | `Intern@123` | Muhammad Danial Bin Hafiz | Smart Automation & IoT |

*Quick-click demo autofill buttons are available directly on the login page (`index.php`).*

---

## 4. Task Lifecycle State Machine

```
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
                                              │ (Upload progress evidence)
                                              ▼ (Intern submits work)
                                    [COMPLETED_PENDING_REVIEW]
                                              ├────────────────────────┐
                                              ▼                        ▼
                                     [REVISION_REQUIRED]          [APPROVED]
                                     (Supervisor feedback)      (Supervisor sign-off)
```

---

## 5. Mandatory Date Input Standard

Every `<input>` for dates or datetimes across the application adheres to:
1. Wrapped in a `.glass-date-group` container with an inline SVG calendar icon button (`.glass-date-toggle` with `data-toggle`) on the right.
2. Clicking either the input or calendar icon triggers the Flatpickr Glassmorphism popup calendar.
3. Defaults to `new Date()` automatically for new records (`data-default-today="true"`), while preserving existing database values when editing.
4. Displays user-friendly dates (`d M Y` or `d M Y, h:i K`) via `altInput`, while submitting strict MySQL formats (`Y-m-d` or `Y-m-d H:i:s`).

```html
<div class="glass-date-group">
  <input type="text" name="due_date" id="due_date" class="glass-input flatpickr-input" placeholder="Select due date" data-enable-time="true" data-default-today="true" required>
  <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </button>
</div>
```
