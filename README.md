# Sayeon ERP

A modular, multi-company Enterprise Resource Planning (ERP) system built with **CodeIgniter 4, PHP, MySQL/MariaDB, Bootstrap, and JavaScript**.

Sayeon ERP provides centralized management for multiple companies with **role-based access control (RBAC)** and modules covering HR, accounting, compliance, purchasing, tasks, documents, onboarding, offboarding, reporting, and more.

## 🚀 Features

### 🔐 Authentication & Access Control
- Secure user authentication
- Role-based access control
- Permission-based module access
- Company-specific access
- Company switching for authorized users
- Super Admin management

### 🏢 Multi-Company Management
- Manage multiple companies from a single ERP
- Company-specific users and data
- Company-level access restrictions
- Centralized administration

### 👥 Human Resources
- Employee management
- Employee profiles
- Attendance management
- Payroll management
- Onboarding
- Offboarding
- Department management

### 💰 Accounting & Finance
- Expense management
- Invoice management
- Payroll records
- Financial reporting

### 📋 Task & Project Management
- Project management
- Task management
- Task status tracking
- Task checklists
- Task history

### 🛒 Purchase Management
- Purchase orders
- Vendor-related information
- Purchase tracking

### 🛡️ Compliance
- Compliance item management
- Compliance tracking
- Compliance officer workflows

### 📄 Document Management
- Company documents
- Document uploads
- Document categorization
- Secure document access

### 📊 Reports & Dashboard
- Role-based dashboards
- Business reports
- Activity tracking
- Notifications

### 🔔 Notifications & Activity Logs
- User notifications
- System activity logs
- Task and workflow notifications

### 📅 Additional Modules
- Calendar
- Meetings
- Policies
- Marketing
- Support
- Todo
- Search
- Website management

---

## 🏗️ Technology Stack

| Technology | Purpose |
|------------|---------|
| PHP 8.1+ | Backend |
| CodeIgniter 4 | PHP Framework |
| MySQL / MariaDB | Database |
| Bootstrap | UI Framework |
| JavaScript | Frontend functionality |
| Composer | PHP dependency management |
| Git | Version control |
| GitHub | Source code repository |
| Hostinger | Production hosting |

---

## 📁 Project Structure

```text
Sayeon-ERP/
│
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Database/
│   │   ├── Migrations/
│   │   └── Seeds/
│   ├── Filters/
│   ├── Models/
│   ├── Modules/
│   └── Views/
│
├── public/
│   ├── assets/
│   └── index.php
│
├── writable/
│
├── .env.example
├── .gitignore
├── composer.json
├── composer.lock
├── spark
├── start-dev.bat
└── stop-dev.bat
