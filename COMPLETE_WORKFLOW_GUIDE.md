# Complete Energy Assessment to Project Workflow
## Installation, Project, Quotation, Inventory, Tasks & Checklist

---

## 📋 What Was Just Built

### 1. **Inventory Auto-Deduction** ✓
When you approve a recommendation, the system automatically:
- Deducts inventory stock for each recommended item
- Logs all transactions with reason "Quotation #XXX"
- Can be audited in `inventory_transactions` table

### 2. **Task Checklist System** ✓
Employees can now:
- Add checklist items to each task
- Mark items as complete (with timestamp)
- Track progress visually (0-100%)
- Delete completed items

### 3. **Progress Tracking** ✓
- Real-time progress % based on completed checklists
- Task progress automatically updates when checklists change
- Project-level progress aggregates task progress

### 4. **Combined Approval Workflow** ✓
Single "Approve" creates:
- ✓ Quotation (with line items and cost)
- ✓ Project (with scope & dates)
- ✓ Installation (with schedule)
- ✓ Tasks (7 standard steps)
- ✓ Recommendation Items (equipment list)
- ✓ Inventory Deductions (auto-updated)

---

## 🗄️ Database Changes Required

**First:** Run this in Supabase SQL Editor:

```sql
-- Copy entire contents from:
-- sunder-solar-mis/config/task-checklist.sql
-- Paste into Supabase and Execute
```

This creates:
- `task_checklists` table (checklist items per task)
- `inventory_transactions` table (audit log)
- Adds progress tracking columns to tasks table

---

## 🚀 Complete Workflow

### Step 1: Upload & Scan Bills
```
Energy Assessments Module
→ Select Customer
→ Upload 3 bill images (JPG/PNG)
→ Tesseract OCR extracts kWh
→ OCR results displayed
```

### Step 2: Review Recommendation
```
Click "Review" on assessment
→ Modal shows:
  - Extracted consumption (kWh/month, daily)
  - Recommended system size (kW)
  - Panel count (calculated)
  - Equipment specifications
```

### Step 3: Approve (One Click)
```
Click "Approve & Create Project"
→ System creates all at once:
  ✓ Recommendation items
  ✓ Quotation (with pricing)
  ✓ Project (with dates)
  ✓ Installation (with schedule)
  ✓ 7 Installation tasks
  ✓ Inventory deducted
  ✓ Transaction logged
```

### Step 4: Employee Works on Tasks
```
Tasks Module
→ Employee clicks "View" on task
→ Task detail modal opens with:
  - Task info (status, assigned, priority)
  - Checklist items (create/complete/delete)
  - Progress bar (0-100%)
→ Employee adds checklist items
→ Employee checks off items as complete
→ Progress updates in real-time
→ Task automatically tracks completion %
```

### Step 5: Admin Monitors Progress
```
Projects Module
→ See project status (planning → in progress → complete)
→ See task progress per installation
→ See checklist completion rate
→ Inventory transactions audited
```

---

## 📁 Files Created/Modified

### New Files:
1. **`config/task-checklist.sql`** - Database migrations
2. **`api/task-checklist-api.php`** - Checklist CRUD operations
3. **`api/bill-upload-ocr.php`** - Bill image OCR (already created)
4. **`api/create-approved-project.php`** - Approval workflow + inventory (updated)

### Updated Files:
1. **`modules/tasks.php`** - Added checklist modal UI
2. **`modules/energy-assessments.php`** - Already has bill upload UI
3. **`api/energy-assessments-api.php`** - Already supports approval

---

## 🔧 How Each Part Works

### Inventory Deduction Flow
```
Approve Recommendation
  ↓
Get recommendation_items for this assessment
  ↓
For each item with inventory_id:
  - Get current quantity_available
  - Subtract item quantity
  - Update inventory table
  - Create inventory_transaction log
  ↓
Inventory reduced by deduction amount
```

**Audit Trail**: Every deduction logged with:
- Inventory ID
- Quantity change (negative)
- Reference type: "quotation"
- Reference ID: quotation_id
- Reason: "Deducted for project quotation #XXX"
- Created by: user_id
- Timestamp

### Task Checklist Flow
```
Employee Views Task
  ↓
Opens task detail modal
  ↓
Sees checklist items + progress bar
  ↓
Adds new checklist item
  ↓
Marks items complete (timestamp saved)
  ↓
Progress % updates automatically
  ↓
Task table updated with:
    - progress_percent (0-100)
    - checklist_count (total items)
    - checklist_completed (done items)
```

### Auto-Progress Calculation
```
Checklist items: [Item1, Item2, Item3]
Item1: ✓ Done
Item2: ○ Pending
Item3: ✓ Done

Progress = (2/3) * 100 = 66%

When Item2 checked:
Progress = (3/3) * 100 = 100%
Task automatically marked as "completed" if all items done
```

---

## 🛠️ Implementation Checklist

### Prerequisites:
- [ ] Login as superadmin/admin/owner
- [ ] Run `task-checklist.sql` in Supabase

### Test Inventory Deduction:
- [ ] Energy Assessments module visible
- [ ] Upload/enter 3 bills
- [ ] Approve recommendation
- [ ] Check quotation created
- [ ] Check Supabase: inventory quantities decreased
- [ ] Check Supabase: inventory_transactions has entry

### Test Task Checklist:
- [ ] Project created from approved assessment
- [ ] Tasks module shows 7 tasks
- [ ] Click "View" on any task
- [ ] Task detail modal opens
- [ ] Add checklist item (e.g., "Install wiring")
- [ ] Progress bar appears (0%)
- [ ] Check the item
- [ ] Progress updates to 50% (for 2 items)
- [ ] See timestamp in UI
- [ ] Uncheck to incomplete
- [ ] Delete item with trash icon

### Test Full Workflow:
- [ ] Superadmin uploads 3 bills
- [ ] Superadmin approves
- [ ] Quotation + Project + Installation created
- [ ] 7 Tasks created
- [ ] Inventory deducted
- [ ] Transaction logged
- [ ] Employee logs in
- [ ] Employee views tasks
- [ ] Employee adds checklists
- [ ] Employee tracks progress

---

## 📊 What Progress Shows

### Task Progress:
- **0%** = No items checked
- **50%** = Half items checked
- **100%** = All items checked

### Task Detail Modal:
- Visual progress bar (green fill)
- Percentage number (right side)
- Checked items: strikethrough + grayed out
- Unchecked items: normal text

### Projects Module:
- Shows overall project status
- Task progress aggregated
- Can filter by completion %

---

## 🔐 Permissions

### Super Admin:
- ✓ View/edit/delete assessments
- ✓ Approve recommendations
- ✓ View all tasks
- ✓ Edit all checklists
- ✓ View inventory

### Admin:
- ✓ View/create assessments
- ✓ Approve recommendations
- ✓ View all tasks
- ✓ Edit all checklists
- ✓ View inventory

### Owner:
- ✓ Full access (same as super admin)

### Employee:
- ✓ View assigned tasks only
- ✓ Add checklist items to assigned tasks
- ✓ Mark checklist items complete
- ✓ Cannot create/delete tasks

---

## 🐛 Troubleshooting

**Q: Inventory not deducting**
- A: Check `inventory_transactions` table - if empty, inventory_id might be null
- Verify inventory items exist with matching IDs

**Q: Checklists not showing**
- A: Make sure `task_checklists` table exists (run SQL migration)
- Refresh page (Ctrl+F5)

**Q: Progress not updating**
- A: Progress updates on checklist toggle
- Check browser console for JavaScript errors
- Verify task_id is passed correctly

**Q: Can't see checklist modal**
- A: Click "View" button on task (not "Start")
- Modal opens as overlay - check z-index

**Q: Employee can't add checklists**
- A: Verify employee role has 'tasks' → 'edit' permission
- Check includes/auth.php for role permissions

---

## 📝 Next Steps (Optional)

1. **PDF Generation**
   - Auto-generate PDF quotation
   - Email to customer

2. **Notifications**
   - Notify employee when task assigned
   - Notify admin when checklist completed

3. **Mobile App**
   - Mobile-optimized task checklist
   - Offline checklist editing

4. **Analytics**
   - Dashboard showing completion rates
   - Time tracking per task
   - Cost vs. estimate comparison

5. **Approval Workflow Enhancement**
   - Customer approval before starting
   - Payment status tracking
   - Technician availability calendar

---

## 📞 Quick Reference

**Module URLs** (after login):
- Energy Assessments: `/modules/energy-assessments.php`
- Tasks: `/modules/tasks.php`
- Projects: `/modules/projects.php`
- Quotations: `/modules/quotations.php`

**API Endpoints:**
- `POST /api/bill-upload-ocr.php` - Upload bill for OCR
- `POST /api/create-approved-project.php` - Approve + create all
- `POST /api/task-checklist-api.php` - Add checklist
- `PUT /api/task-checklist-api.php` - Update checklist
- `DELETE /api/task-checklist-api.php` - Delete checklist
- `GET /api/task-checklist-api.php?task_id=X` - Get checklists

**Database Tables:**
- `energy_assessments` - Bill assessment data
- `recommendation_items` - Equipment needed
- `quotations` - Customer quotes
- `projects` - Projects
- `installations` - Installation details
- `tasks` - Tasks (with progress_percent field)
- `task_checklists` - Checklist items
- `inventory_transactions` - Deduction audit log
