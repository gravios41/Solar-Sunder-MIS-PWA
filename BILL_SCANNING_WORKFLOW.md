# Complete Bill Scanning to Project Creation Workflow

## Implementation Summary

Your system now supports the complete workflow: **Scan Bills → Approve → Auto-Create Everything**

### Files Created/Modified

1. **`config/energy-assessments.sql`** (Updated)
   - Added approval fields: `approval_status`, `approved_by`, `approved_at`, `approval_notes`
   - New table: `recommendation_items` (tracks recommended equipment)

2. **`api/bill-upload-ocr.php`** (New)
   - Accepts bill image upload (JPG, PNG, WEBP, PDF)
   - Runs Tesseract OCR automatically
   - Extracts kWh consumption and billing period
   - Stores verified readings in database

3. **`api/energy-assessments-api.php`** (Updated)
   - GET by ID: Fetch single assessment
   - PUT: Update approval status
   - POST: Returns `assessment_id` for frontend bill uploads

4. **`modules/energy-assessments.php`** (Enhanced)
   - Bill image upload UI (3 upload zones)
   - OCR results preview table
   - Recommendation approval modal
   - Saved assessments table with approval status + actions

5. **`api/create-approved-project.php`** (New)
   - Creates quotation with line items
   - Creates project with scope
   - Creates installation schedule
   - Creates 7 standard installation tasks
   - All linked together automatically

## User Workflow

### Step 1: Prepare Database
```sql
-- Run this in Supabase SQL Editor first:
-- Copy entire contents of sunder-solar-mis/config/energy-assessments.sql
-- Paste into Supabase SQL Editor → Execute
```

### Step 2: Upload & Scan Bills
1. Go to "Energy Assessments" module
2. Select a customer
3. Click on one of 3 bill upload zones
4. Upload JPG/PNG of electric bill
5. Click "Upload" button
6. Tesseract automatically extracts kWh + billing period
7. Repeat for all 3 bills

### Step 3: Review & Approve
1. OCR results appear in "OCR Extracted Results" table
2. Review extracted consumption values
3. Click "Review" button on the assessment row
4. Recommendation modal shows:
   - Extracted consumption (kWh/month and daily)
   - Recommended system size (kW)
   - Panel count and specifications
   - Peak sun hours and efficiency
5. Click "Approve & Create Project" to trigger automation

### Step 4: Automatic Generation
When you click Approve, the system creates:
- **Quotation** with line items:
  - Solar panels (calculated qty)
  - Inverter (sized for system)
  - Mounting system
  - Labor cost estimate (15%)
  - Valid for 30 days
- **Project** with:
  - Customer & location info
  - System specifications
  - Start date (7 days out)
  - Estimated end date (3 weeks)
  - Status: "planning"
- **Installation** with:
  - System capacity details
  - Installation type & scheduled date
  - Estimated 3-day duration
- **7 Standard Tasks**:
  1. Site Survey & Roof Assessment (2 hrs)
  2. Obtain Permits & Approvals (8 hrs)
  3. Equipment Procurement
  4. Electrical Wiring & Panel Installation (8 hrs)
  5. Inverter & Battery Installation (4 hrs)
  6. Grid Connection & Testing (2 hrs)
  7. Customer Training & Handover (1 hr)

## Key Features

### OCR Text Extraction
Tesseract looks for patterns like:
- "Consumption: 1234 kWh"
- "1234 KWH TOTAL"
- "ENERGY: 1234 kWh"
- Handles comma/period decimals

### Solar Calculation
```
Average Daily (kWh) = Monthly Total / 30
System Size (kW) = Daily / Peak Sun Hours / Efficiency
Panel Count = ceiling(System Size * 1000 / Panel Wattage)
```
Defaults: 5 peak sun hours, 80% efficiency, 550W panels

### Cost Estimation
```
Material Cost = Sum of line items
Labor Cost = Material Cost * 15%
Total = Material + Labor
```

### Permissions Required
All roles (super_admin, admin, owner, employee) can:
- View assessments
- Create assessments
- Approve recommendations (admin/owner roles)

## Testing Checklist

- [ ] Run updated `energy-assessments.sql` in Supabase
- [ ] Upload a bill image (JPG/PNG) in Energy Assessments module
- [ ] Verify OCR extraction works (shows kWh and period)
- [ ] Complete 3 bill uploads
- [ ] Click "Review" on an assessment
- [ ] Modal shows recommendation details
- [ ] Click "Approve & Create Project"
- [ ] Verify quotation created in Quotations module
- [ ] Verify project created in Projects module
- [ ] Verify installation created in Installations module
- [ ] Verify 7 tasks created in Tasks module
- [ ] Verify all items linked correctly

## Next Steps (Optional Enhancements)

1. **Bill Verification UI**
   - Allow manual edits to OCR extraction before saving
   - Show confidence scores per extraction

2. **Recommendation Items Display**
   - Show detailed recommendation items before approval
   - Allow customization of equipment choices

3. **Technician Auto-Assignment**
   - Automatically assign tasks to available technician
   - Send notifications to technician

4. **Mobile Bill Capture**
   - Integrate camera API for direct photo capture
   - Add image quality detection

5. **Document Generation**
   - Auto-generate PDF quotation for customer
   - Email quotation and project summary

## Troubleshooting

**Tesseract not found:**
- Verify: `C:\Program Files\Tesseract-OCR\tesseract.exe` exists
- Or: `C:\Program Files (x86)\Tesseract-OCR\tesseract.exe`

**OCR Not Extracting kWh:**
- Ensure bill image is clear and readable
- Check bill format (meter number, consumption line clearly visible)
- kWh value must appear in numerical form

**Assessment not created:**
- Select customer before uploading bills
- All 3 bills must have non-zero kWh values

**Approval not working:**
- Verify your role allows approval (admin/owner/super_admin)
- Check that all 3 bills were uploaded successfully

**Project not appearing:**
- Check Supabase logs for errors
- Verify quotations, projects, installations tables exist
- Verify recommendation_items table was created

## File Locations

```
sunder-solar-mis/
├── config/
│   └── energy-assessments.sql          (Updated with new tables)
├── api/
│   ├── bill-upload-ocr.php              (New - OCR handler)
│   ├── create-approved-project.php     (New - Combined creation)
│   └── energy-assessments-api.php      (Updated for approval)
├── modules/
│   └── energy-assessments.php          (Enhanced with OCR UI)
└── assets/
    └── bills/                          (Bill images stored here)
```
