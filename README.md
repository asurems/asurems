# ASU-REMS
#222
**Aklan State University – Research and Extension Management System**

ASU-REMS is a web-based capstone project developed for **Aklan State University – Kalibo Campus (ASU-KC)**. It supports the submission, review, approval, monitoring, and reporting of faculty research and extension activities through centralized, role-based workflows.

> **Project status:** Academic/capstone system. Review security, data privacy, configuration, and institutional requirements before any production deployment.

## Overview

ASU-REMS brings research and extension processes into one platform. Authorized users can manage proposals, track their progress, respond to revision requests, coordinate reviews, and produce reports based on their assigned responsibilities.

### Main features

- **Role-based access:** Separate interfaces and permissions for faculty/researchers, Research Processing Staff (RPS), extension coordinators, Extension Service Office (ESO), and administrators.
- **Research management:** Research proposal submission, URRC and in-house reviews, approval stages, implementation tracking, permits, and completion records.
- **Extension management:** Calls for proposals, UERC and in-house reviews, endorsement, approval, certification, and project monitoring.
- **Training and technical services:** Service requests, acknowledgement, personnel assignment, scheduling, and completion confirmation.
- **Revision handling:** Submit revised documents and track the current review stage and remarks.
- **Link-based documents:** Submit secure HTTPS document links and open documents through the system's viewing controls.
- **Notifications and dashboards:** View updates, tasks, proposal statuses, and relevant summaries.
- **Reports:** Generate PDF reports of completed records, subject to role-based access.
- **Account management:** User profiles, account status checks, and administrative account management.
- **Activity logs:** Role-scoped records of sign-in activity.
- **Light and dark themes:** A consistent interface across system modules.

## Technology stack

| Layer | Technology |
| --- | --- |
| Backend | PHP |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript, Bootstrap |
| Local development | XAMPP (Apache and MySQL) |
| Local environment | Windows / web browser |

## User roles

| Role | Primary responsibility |
| --- | --- |
| **Administrator** | Manage users and administrative functions. |
| **Faculty / Researcher** | Submit and monitor research proposals, revisions, and required research documents. |
| **Research Processing Staff (RPS)** | Process and review research submissions, record decisions, and manage research workflow stages. |
| **Extension Coordinator** | Submit and monitor extension proposals and training/technical service requests. |
| **Extension Service Office (ESO)** | Manage extension proposal processing, service requests, schedules, and completion records. |

The system is designed around these authorized roles; it does not require a separate student account for its core workflows.

## Workflow summary

### Research management

**Regular research pathway**

```text
Proposal Submission
    ↓
URRC Technical Review
    ↓
In-House Review
    ↓
Endorsement
    ↓
LADCO / ADCO Approval
    ↓
BOR Notation
    ↓
Notice of Approval / Certification
    ↓
Notice to Proceed (NTP)
    ↓
Completed Research Manuscript
    ↓
Terminal Report
    ↓
Publication / Presentation Permit
    ↓
Proof of Publication / Presentation
    ↓
Completed
```

**Self-initiated research pathway**

```text
Self-Initiated Pathway Selection
    ↓
UPE Certificates 1 and 2
    ↓
UREC / IACUC Review Completion
    ↓
Notice to Proceed (NTP)
    ↓
Completed Research Manuscript
    ↓
Terminal Report
    ↓
Self-Initiated Permit
    ↓
Publication Link
    ↓
Completed
```

Review outcomes may require revisions and resubmission. Rejected submissions and expired revision periods are handled according to the configured research workflow rules.

### Extension management

**Extension proposal pathway**

```text
Call for Proposals
    ↓
Proposal Submission
    ↓
UERC Technical Review
    ↓
University In-House Review
    ↓
Endorsement (Chairperson / Dean / CD)
    ↓
LADCO / ADCO Approval
    ↓
BOR Notation
    ↓
Notice of Approval / Certification
    ↓
Approved Proposal
    ↓
Monitoring and Evaluation
```

**Training and technical services**

```text
Service Request Submission
    ↓
ESO Acknowledgement / Request for Information (if needed)
    ↓
Personnel or Unit Assignment
    ↓
Schedule and Notification
    ↓
Service Delivery / Monitoring
    ↓
Completion and Confirmation
```

## Local installation

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) with Apache, PHP, and MySQL/MariaDB support.
- A modern web browser.
- A copy of the ASU-REMS source code and its matching database SQL export.

> The database backup/export is required but is **not included or verified by this README**. Use the SQL file provided with the project; do not assume a particular SQL filename.

### Setup steps

1. Install and open **XAMPP Control Panel**.
2. Start the **Apache** and **MySQL** services.
3. Copy the project folder to:

   ```text
   C:\xampp\htdocs\asu-rems
   ```

4. Open phpMyAdmin at `http://localhost/phpmyadmin`.
5. Create a database named `asu_rems` (unless your actual project configuration uses another name).
6. Import the SQL export that belongs to the same version of the application.
7. Check **`config/database.php`** and set the correct database host, database name, username, and password for your local environment. Follow the configuration format already present in that file.
8. Check **`config/app.php`** for the correct application base URL and session-related settings. For the standard XAMPP folder name above, the project uses `BASE_URL` of `/asu-rems`.
9. Open the application in your browser:

   ```text
   http://localhost/asu-rems/
   ```

10. Sign in using an authorized account from your local database, or follow the application's supported initial administrator setup process.

**Important:** The repository should not publish live database credentials, real user information, or production database exports.

## Project structure

The project contains modules and supporting files similar to the following:

```text
asu-rems/
├── actions/                 # Form submissions and workflow actions
├── admin/                   # Administrative functions
├── assets/                  # CSS, JavaScript, images, and UI assets
├── config/                  # Application and database configuration
├── extension/               # Extension proposals and service workflows
├── includes/                # Shared PHP helpers and authentication
├── notifications/           # User notifications
├── research/                # Research proposal workflows
├── reports/                 # Report generation
├── activity_logs/           # Sign-in activity records
├── dashboard.php            # Role-based dashboard
├── login.php                # Landing, sign-in, and registration interface
└── README.md
```

The exact files and folders may differ by source-code version.

## Deployment notes

ASU-REMS runs on **PHP and MySQL**, so it requires hosting that supports both a PHP web server and a compatible database. A static hosting service alone cannot run the PHP backend.

Before putting the system online:

1. Configure the production database and application URL.
2. Enable **HTTPS** with a valid TLS certificate.
3. Restrict direct public access to sensitive configuration, backups, and private files.
4. Disable development error displays and enable controlled server-side logging.
5. Review account permissions, session security, file/link validation, and database input handling.
6. Back up the database securely and verify that restoration works.
7. Test all research and extension workflows using non-sensitive sample records.
8. If clean URLs (without `.php`) are required, configure and test Apache URL rewriting or equivalent server rules; hiding extensions alone is not a security control.

> **Do not commit:** Passwords, production configuration secrets, database dumps containing personal information, or private uploaded documents. Consider using an appropriate `.gitignore` and server-level protections.

## Testing checklist

- [ ] User registration, sign-in, sign-out, and inactive-account restrictions.
- [ ] Role-based access to dashboards, records, and processing actions.
- [ ] Research proposal submission and URRC / in-house decisions.
- [ ] Research revision cycles, deadlines, and archive behavior.
- [ ] Regular and self-initiated research pathways.
- [ ] Extension proposal submission and approval workflow.
- [ ] ESO service scheduling, assignment, and completion confirmation.
- [ ] HTTPS document links and document-view controls.
- [ ] Notifications, profile updates, and theme behavior.
- [ ] Completed-record PDF reports and role-scoped activity logs.

## Intended use

This repository documents an academic system developed to support research and extension management processes. Actual institutional implementation, deployment, and data handling should be approved by the appropriate Aklan State University offices.

## Credits

**Project:** ASU-REMS — Aklan State University Research and Extension Management System  
**Institution:** Aklan State University – Kalibo Campus  
**Program:** Bachelor of Science in Information Technology  
**Project type:** Capstone Project

---

*For project-specific installation issues, consult the development team and the configuration supplied with the source code.*
