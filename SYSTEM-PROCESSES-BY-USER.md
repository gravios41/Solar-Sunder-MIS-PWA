# Sunder Solar MIS — System Processes by User/Actor

This lists every process currently in the system, grouped by who performs it, with
inputs, outputs, and the data stores each process touches. Use it as the source
material for a DFD (Level 1 or Level 0 context diagram).

Actors (external entities / user roles):
- **Client** — external entity, not a system login. Provides info/bill, receives quotation PDF, approves/rejects.
- **Owner** — full access role.
- **Super Admin** — full access role, only one who can edit another admin-level account's protected fields.
- **Admin** — restricted, view-only role (Customers, Quotations, Installations, Projects, Tasks, Reports).
- **Employee** — view-only across most modules; only edits Task Checklists.
- **All Authenticated Users** — processes any logged-in role can do regardless of role (login, profile, notifications).

---

## 1. Client (external entity)

| # | Process | Input | Output | Data Store(s) |
|---|---|---|---|---|
| 1.1 | Give last month's electric bill + basic info to Owner | Paper/photo bill, name, contact | — | — |
| 1.2 | Pick a recommendation tier (Budget-Friendly / Actual / Luxury) | Verbal/decision to Owner | Selected tier | — |
| 1.3 | Receive quotation PDF | — | Quotation PDF (emailed/handed over) | — |
| 1.4 | Approve or reject the quotation | Decision to Owner | Approval/rejection | — |
| 1.5 | Request an equipment upgrade on an existing project | Verbal/decision to Owner | New item request | — |

---

## 2. Owner (primary system operator — also covers Super Admin unless noted)

### 2.1 Energy Assessment
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| Create energy assessment | Client name, peak sun hours, one electric bill (OCR upload **or** manual kWh/rate entry) | kWh/day consumption, computed system size | `energy_assessments` |
| System computes recommendation preview | Bill data + fixed assumptions (550W panel, 80% efficiency) | 3 sizing tiers (Budget/Actual/Luxury) with items & cost | `inventory` (read), `energy_assessments` (draft) |
| Select a recommendation tier & save assessment | Chosen tier | Draft **Quotation** created automatically (assessment linked via `quotation_id`) | `energy_assessments`, `quotations`, `recommendation_items` |
| View saved assessment | — | Read-only view of the tier actually saved (no re-picking) | `energy_assessments`, `quotations` |

### 2.2 Quotations
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View/edit auto-generated quotation (from assessment) | Edits to line items, prices, notes | Updated quotation | `quotations`, `recommendation_items` |
| Create **manual quotation** (no bill / walk-in client) | Client info typed directly (name, contact, email, phone, address, city, province, postal code, GSTIN, type, status), Solar System Type (Hybrid/Grid-Tied/Off-Grid), manually chosen items | New draft quotation | `quotations`, `recommendation_items` |
| Download quotation as PDF | Click "Download PDF" on a quotation row | Client-side generated PDF (jsPDF) | `quotations` (read) |
| Send quotation PDF to client | Generated PDF | — (external, email/hand-off) | — |
| **Approve quotation** (commitment point) | Client's verbal/written approval | 1) Customer record created (if new) 2) Project record created 3) Inventory deducted per line item 4) Installation + initial Tasks created 5) linked Energy Assessment marked `approved` with `project_id`/`customer_id` | `customers`, `projects`, `inventory`, `installations`, `tasks`, `energy_assessments`, `quotations` |
| Reject quotation | Reason | Quotation marked rejected | `quotations` |

### 2.3 Projects
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View project list/details | — | Project records (auto-created from approved quotations) | `projects` |
| Edit project details | Field edits | Updated project | `projects` |
| Trigger **Upgrade** on an existing project (entry point is on Installations page) | Click "Upgrade" button on an installation's project card | Redirects to Projects with the project pre-selected in Upgrade mode | `projects` (read) |
| Complete upgrade — add compatible new equipment to an existing project | Selected new items (e.g., add a battery) | New recommendation items appended to the existing project; inventory deducted | `projects`, `recommendation_items`, `inventory` |

### 2.4 Customers
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View/search customer list | — | Customer records (created automatically on quotation approval) | `customers` |
| Edit customer details | Field edits | Updated customer | `customers` |

### 2.5 Installations
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View installation cards (one per approved project) | — | Installation status/progress | `installations`, `projects` |
| Update installation status | Status change | Updated installation | `installations` |
| Click "Upgrade" button on a project's installation card | — | Navigates to Projects module with `?upgrade_project=` to open the Upgrade flow (see 2.3) | `installations`, `projects` |

### 2.6 Tasks
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View/create/edit tasks tied to a project/installation | Task details, assignee | Task record | `tasks` |
| View task checklist progress | — | Checklist completion % | `tasks`, `task_checklist` |

### 2.7 Inventory
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View inventory levels | — | Stock levels by category (panel/inverter/battery/mounting/other) | `inventory` |
| Add/edit/delete inventory items | Item details, quantity | Updated inventory | `inventory` |
| (Automatic) Inventory deduction on quotation approval | Approved quotation's line items | Reduced stock quantities | `inventory` |

### 2.8 Reports
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| Generate/view/print reports | Filter/date range | Report output (view/print) | `projects`, `quotations`, `customers`, `inventory` (aggregated, read-only) |

### 2.9 User Management (Owner & Super Admin only)
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View users, categorized by role | — | User list grouped by role | `users` |
| Create new user account | Username, name, role, password | New user (password hashed) | `users` |
| Edit user (Admin/Employee accounts) | Field edits | Updated user | `users` |
| Edit own email/phone directly | New email/phone | Updated user record | `users` |
| **Cannot** edit a Super Admin's protected info if self is only Owner | — | Blocked | `users` |
| Reset/set another user's password | New password | Password re-hashed and stored | `users` |
| Deactivate/reactivate user | Status toggle | Updated `users.status` | `users` |

### 2.10 Archives
| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View archived (soft-deleted) records | — | Archived record list, any entity type | `archives` |
| Restore archived record | Selected archive entry | Original record re-inserted into its source table | `archives`, source table (`customers`/`projects`/`quotations`/etc.) |

---

## 3. Super Admin (all Owner processes above, plus)

| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| Edit/deactivate an Owner's account (Owner cannot edit Super Admin, but Super Admin can manage Owner) | Field edits | Updated user | `users` |
| Full settings control | System settings changes | Updated settings | `settings` |

---

## 4. Admin (view-only role)

| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View customers | — | Customer list (read-only) | `customers` |
| View quotations | — | Quotation list (read-only, can still download PDF) | `quotations` |
| View projects | — | Project list (read-only) | `projects` |
| View installations | — | Installation list (read-only) | `installations` |
| View tasks | — | Task list (read-only) | `tasks` |
| View/print reports | — | Report output | `projects`, `quotations`, `customers` |
| Submit request to change own email/phone | Requested new value | Email sent to all active Owner/Super Admin accounts | `users`, (email, not stored) |

---

## 5. Employee (view-only + checklist)

| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| View dashboard | — | Summary metrics | multiple (read-only) |
| View quotations / energy assessments / projects / installations / tasks / customers / inventory | — | Read-only views | respective tables |
| Edit/complete task checklist items | Checklist item check-off | Updated checklist | `task_checklist` |
| Edit own settings (profile fields allowed to employee) | Field edits | Updated own user record | `users` |
| Submit request to change own email/phone | Requested new value | Email sent to all active Owner/Super Admin accounts | `users`, (email, not stored) |

---

## 6. All Authenticated Users (Owner, Super Admin, Admin, Employee)

| Process | Input | Output | Data Store(s) |
|---|---|---|---|
| Log in | Username/email + password | Session created | `users` |
| Log out | — | Session destroyed | — |
| Forgot password | Email | Reset-password email sent (via Brevo/Resend HTTP API, SMTP fallback) with time-limited token | `users`, `password_resets` (or equivalent token store) |
| Reset password via emailed link | New password + token | Password updated (hashed) | `users` |
| View/edit own profile (name, avatar, etc. — email/phone gated by role) | Field edits | Updated own user record | `users` |
| Change own password (while logged in) | Current + new password | Password re-hashed and stored | `users` |
| Receive notification when email/phone-change request is submitted (Owner/Super Admin only) | Incoming request email | — | — |

---

## Data Stores Referenced

- `users` — accounts, roles, credentials
- `energy_assessments` — client bill data, sizing, tier chosen, links to quotation
- `quotations` — draft/approved quotations, client info (manual or from assessment), line items via `recommendation_items`
- `recommendation_items` — line items per quotation (category: panel/inverter/battery/mounting/other)
- `customers` — created automatically on quotation approval
- `projects` — created automatically on quotation approval
- `installations` — created automatically alongside project
- `tasks` / `task_checklist` — work items tied to installation/project
- `inventory` — stock, deducted automatically on quotation approval
- `archives` — soft-deleted record snapshots (JSON) for any entity, restorable
- `settings` — system-wide configuration

---

## Key Process Flow (for reference when drawing the DFD)

```
Client → (bill + info) → Owner
Owner → P1 Energy Assessment → creates draft Quotation
Owner → P2 Quotation (view/edit/manual-create) → Download PDF → Client
Client → approve/reject → Owner
Owner → P3 Approve Quotation →
    → creates Customer (if new)
    → creates Project
    → deducts Inventory
    → creates Installation + Tasks
Owner/Employee → P4 Installation & Task tracking
Owner → P5 Upgrade (from Installation card → Project, add equipment) → deducts Inventory
Owner/Super Admin → P6 User Management
All Users → P7 Auth (login/forgot-password/profile)
```
