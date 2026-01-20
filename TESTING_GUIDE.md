# Testing Guide: Equipment Tracking & Accountability System

## Prerequisites

1. **Run the Migration First**
   ```bash
   cd backend-laravel
   php artisan migrate
   ```
   This will add `serial_number` and `model` fields to the items table.

2. **Ensure Backend is Running**
   ```bash
   cd backend-laravel
   php artisan serve
   ```
   Backend should be running on `http://127.0.0.1:8000`

3. **Ensure Frontend is Running**
   ```bash
   cd frontend-vue
   npm run dev
   ```
   Frontend should be running on `http://localhost:5173` (or similar)

4. **Login as Admin**
   - You need admin access to test all features
   - Login with an admin account

---

## Test 1: Add New Items with Serial Numbers and Models

### Steps:

1. **Navigate to Add Item Page**
   - Go to Inventory → Add New Item
   - Or navigate to `/add-item` route

2. **Fill Required Fields**
   - **Article**: Enter item name (e.g., "Laptop Dell")
   - **Description**: Enter description
   - **Serial Number**: Enter unique serial (e.g., "SN123456789") ⭐ **REQUIRED**
   - **Model**: Enter model (e.g., "XPS 15 9520") ⭐ **REQUIRED**
   - **Category**: Select category
   - **PAC**: Enter Property Account Code
   - **Unit Value**: Enter value (e.g., 50000)
   - **Date Acquired**: Select date
   - **PO Number**: Enter PO number
   - **Location**: Select location
   - **Issued To**: Select personnel
   - **Condition**: Select condition

3. **Submit the Form**
   - Click "Create Item"
   - Verify success message

4. **Verify Item Created**
   - Go to Inventory list
   - Find the item you just created
   - Verify Serial Number and Model are displayed

### Expected Result:
✅ Item created successfully with Serial Number and Model
✅ Serial Number and Model visible in inventory list
✅ Both fields are required (form won't submit without them)

---

## Test 2: Edit Existing Items to Add Serial Numbers and Models

### Steps:

1. **Navigate to Inventory**
   - Go to Inventory page
   - Find an existing item without serial number/model

2. **Edit the Item**
   - Click Edit button on an item
   - Fill in:
     - **Serial Number**: (e.g., "SN987654321")
     - **Model**: (e.g., "ThinkPad X1 Carbon")
   - Save changes

3. **Verify Update**
   - Check inventory list
   - Verify Serial Number and Model are now displayed

### Expected Result:
✅ Item updated with Serial Number and Model
✅ Fields visible in inventory and item details

---

## Test 3: Test Lost/Damaged Tracking

### Steps:

1. **Navigate to Personnel Management**
   - Go to Personnel Management page
   - Find a personnel with issued items
   - Click "Clearance" button

2. **View Pending Items**
   - Clearance modal should open
   - You should see items with:
     - Serial Number
     - Model
     - MR Number
     - Category
     - Value
     - Issued date
     - Issued by

3. **Mark Item as Lost**
   - Click "Lost/Damaged" button on an item
   - In the modal:
     - **Status**: Select "Lost"
     - **Reported By**: Enter name (auto-filled with personnel name)
     - **Incident Date**: Select date
     - **Description**: Enter details (e.g., "Item lost during office relocation")
   - Click "Mark as Lost"

4. **Mark Item as Damaged**
   - Click "Lost/Damaged" on another item
   - In the modal:
     - **Status**: Select "Damaged"
     - **Reported By**: Enter name
     - **Incident Date**: Select date
     - **Estimated Value Loss**: Enter amount (e.g., 5000)
     - **Description**: Enter details (e.g., "Screen cracked, needs repair")
   - Click "Mark as Damaged"

5. **Verify Status**
   - Check the items in clearance modal
   - Status should show "LOST" or "DAMAGED"
   - View item details to see remarks

### Expected Result:
✅ Items can be marked as LOST or DAMAGED
✅ Detailed information is captured (reported by, incident date, description)
✅ Value loss tracked for damaged items
✅ Status properly updated in system
✅ Complete audit trail maintained

---

## Test 4: Generate Accountability Reports

### Steps:

1. **Via API (Using Postman/Browser)**
   ```
   GET http://127.0.0.1:8000/api/v1/memorandum-receipts/accountability-report/user/{userId}
   ```
   Replace `{userId}` with actual user ID
   - Add Authorization header: `Bearer {your_token}`

2. **Check Response Structure**
   The response should include:
   ```json
   {
     "success": true,
     "data": {
       "personnel": {
         "name": "John Doe",
         "user_code": "NIA-USER-0001",
         "status": "ACTIVE"
       },
       "items": [
         {
           "mr_number": 1,
           "item_id": 5,
           "unit": "Laptop Dell",
           "serial_number": "SN123456789",
           "model": "XPS 15 9520",
           "category": "Computer",
           "pac": "PAC001",
           "unit_value": 50000,
           "issued_date": "2024-01-15",
           "issued_by": "ADMIN001",
           "status": "ISSUED",
           ...
         }
       ],
       "summary": {
         "total_items": 5,
         "issued": 3,
         "returned": 1,
         "lost": 1,
         "damaged": 0,
         "total_value": 250000,
         "lost_damaged_value": 50000
       }
     }
   }
   ```

3. **Verify Report Contents**
   - All items listed with Serial Numbers and Models
   - MR numbers included
   - Who issued each item
   - Current status of each item
   - Summary statistics

### Expected Result:
✅ Report generated successfully
✅ All items include Serial Number and Model
✅ Complete MR tracking information
✅ Summary statistics accurate
✅ Lost/Damaged items properly categorized

---

## Test 5: Test Clearance Process

### Scenario: Personnel Change/End of Assignment

### Steps:

1. **Prepare Test Data**
   - Ensure a personnel has multiple issued items
   - Items should have Serial Numbers and Models

2. **Navigate to Personnel Management**
   - Go to Personnel Management
   - Find personnel with pending items
   - Click "Clearance" button

3. **View Items in Clearance Modal**
   - Verify all items show:
     - Serial Number
     - Model
     - MR Number
     - Category
     - Value
     - Issued date
     - Issued by

4. **Test Individual Actions**

   **A. Return Item to Inventory**
   - Click "Return" on an item
   - Verify item is removed from pending list
   - Check inventory - item should be available

   **B. Reassign Item**
   - Click "Reassign" on an item
   - Select new personnel
   - Verify item transferred
   - New personnel should see item in their list

   **C. Mark as Lost**
   - Click "Lost/Damaged"
   - Select "Lost" status
   - Fill required details
   - Verify item marked as lost
   - Check accountability report shows lost item

   **D. Mark as Damaged**
   - Click "Lost/Damaged"
   - Select "Damaged" status
   - Fill required details including value loss
   - Verify item marked as damaged

5. **Test Bulk Operations**

   **A. Bulk Return**
   - Select multiple items (checkboxes)
   - Click "Return All Selected"
   - Verify all selected items returned

   **B. Bulk Reassign**
   - Select multiple items
   - Click "Reassign All Selected"
   - Select new personnel
   - Verify all items transferred

6. **Test Enhanced Clearance (Using API)**
   ```
   POST http://127.0.0.1:8000/api/v1/memorandum-receipts/clearance/user/{userId}
   
   Headers:
   - Authorization: Bearer {token}
   - Content-Type: application/json
   
   Body:
   {
     "items": [
       {
         "mr_id": 1,
         "action": "RETURN"
       },
       {
         "mr_id": 2,
         "action": "TRANSFER",
         "new_personnel_id": 5,
         "new_location_id": null
       },
       {
         "mr_id": 3,
         "action": "LOST",
         "status_details": {
           "reported_by": "John Doe",
           "incident_date": "2024-01-10",
           "description": "Lost during office move"
         }
       },
       {
         "mr_id": 4,
         "action": "DAMAGED",
         "status_details": {
           "reported_by": "John Doe",
           "incident_date": "2024-01-12",
           "description": "Screen damaged",
           "estimated_value_loss": 5000
         }
       }
     ],
     "cleared_by": "Admin Name",
     "clearance_date": "2024-01-15"
   }
   ```

7. **Verify Clearance Complete**
   - All items processed
   - Accountability report shows updated statuses
   - Personnel can now be changed/removed

### Expected Result:
✅ All items can be processed individually
✅ Bulk operations work correctly
✅ Lost/Damaged items properly tracked
✅ Complete audit trail maintained
✅ Personnel change allowed after clearance

---

## Test 6: Verify Personnel Change Protection

### Steps:

1. **Try to Change Personnel with Pending Items**
   - Go to Location Management
   - Try to change personnel in a location
   - If location has items assigned, system should block

2. **Complete Clearance First**
   - Go to Personnel Management
   - Complete clearance for that personnel
   - All items should be returned/transferred/lost/damaged

3. **Try Personnel Change Again**
   - Now try to change personnel
   - Should be allowed

### Expected Result:
✅ System prevents personnel change with pending items
✅ Clearance must be completed first
✅ Personnel change allowed after clearance

---

## Test 7: View Issued Items (User Perspective)

### Steps:

1. **Login as Regular User**
   - Login with a non-admin account
   - Go to Profile page

2. **View "My Issued Items"**
   - Should see section "My Issued Items"
   - Items should display with:
     - Serial Number
     - Model
     - Category
     - Status (ISSUED/RETURNED)
     - Issued date
     - Issued by

3. **Verify Only Current Items Shown**
   - Items should only show if:
     - Currently assigned to user (user_id matches), OR
     - Currently assigned to location where user is personnel
   - Reassigned items should NOT appear

### Expected Result:
✅ User sees their issued items
✅ Serial Numbers and Models displayed
✅ Only currently assigned items shown
✅ Reassigned items don't appear

---

## Test Checklist

### ✅ Database
- [ ] Migration runs successfully
- [ ] Serial Number and Model columns exist in items table

### ✅ Adding Items
- [ ] Can add item with Serial Number (required)
- [ ] Can add item with Model (required)
- [ ] Form validates required fields
- [ ] Item saved successfully

### ✅ Editing Items
- [ ] Can edit Serial Number
- [ ] Can edit Model
- [ ] Changes saved successfully

### ✅ Lost/Damaged Tracking
- [ ] Can mark item as LOST
- [ ] Can mark item as DAMAGED
- [ ] Status details captured
- [ ] Audit trail maintained

### ✅ Accountability Reports
- [ ] Report generates successfully
- [ ] Shows Serial Numbers
- [ ] Shows Models
- [ ] Shows MR details
- [ ] Summary statistics accurate

### ✅ Clearance Process
- [ ] Can return items
- [ ] Can transfer items
- [ ] Can mark as lost/damaged
- [ ] Bulk operations work
- [ ] Personnel change allowed after clearance

### ✅ User View
- [ ] Users see their issued items
- [ ] Serial Numbers and Models displayed
- [ ] Only current items shown

---

## Troubleshooting

### Issue: Migration Fails
**Solution:**
```bash
# Check if columns already exist
php artisan tinker
>>> Schema::hasColumn('items', 'serial_number')
>>> Schema::hasColumn('items', 'model')

# If migration partially ran, manually add columns or rollback
php artisan migrate:rollback --step=1
php artisan migrate
```

### Issue: Serial Number/Model Not Saving
**Solution:**
- Check Item model `$fillable` array includes fields
- Check validation rules in StoreItemRequest/UpdateItemRequest
- Check browser console for errors
- Check Laravel logs: `storage/logs/laravel.log`

### Issue: Lost/Damaged Status Not Working
**Solution:**
- Verify API endpoint: `/memorandum-receipts/{mrId}/lost-damaged`
- Check request includes `status` field (LOST or DAMAGED)
- Verify MR status is "ISSUED" before marking
- Check Laravel logs for errors

### Issue: Accountability Report Empty
**Solution:**
- Verify user has issued items
- Check if items have MR records
- Verify API endpoint and authentication
- Check if items are currently assigned to user

---

## API Endpoints for Testing

### 1. Get Accountability Report
```
GET /api/v1/memorandum-receipts/accountability-report/user/{userId}
Headers: Authorization: Bearer {token}
```

### 2. Process Clearance
```
POST /api/v1/memorandum-receipts/clearance/user/{userId}
Headers: 
  - Authorization: Bearer {token}
  - Content-Type: application/json
Body: (see Test 5 above)
```

### 3. Mark as Lost/Damaged
```
POST /api/v1/memorandum-receipts/{mrId}/lost-damaged
Headers: 
  - Authorization: Bearer {token}
  - Content-Type: application/json
Body: {
  "status": "LOST" or "DAMAGED",
  "remarks": "Description",
  "reported_by": "Name",
  "incident_date": "2024-01-15",
  "estimated_value_loss": 5000 (for DAMAGED)
}
```

### 4. Get My Issued Items (User)
```
GET /api/v1/memorandum-receipts/my-items
Headers: Authorization: Bearer {token}
```

---

## Expected Database State After Testing

### Items Table
- All items should have `serial_number` and `model` fields
- Fields can be NULL for old items (but required for new items)

### Memorandum Receipts Table
- Status can be: ISSUED, RETURNED, LOST, DAMAGED
- `remarks` field contains JSON with detailed information for LOST/DAMAGED items
- `processed_by_user_id` tracks who processed the action

---

## Success Criteria

✅ All items have Serial Numbers and Models (for new items)
✅ Lost/Damaged items properly tracked with details
✅ Accountability reports show complete information
✅ Clearance process handles all scenarios
✅ Personnel change blocked until clearance complete
✅ Users see only their current issued items
✅ Complete audit trail maintained

---

## Notes

- **Serial Numbers** should be unique identifiers for each equipment
- **Models** help identify equipment type and specifications
- **MR Numbers** track who issued and received equipment
- **Lost/Damaged** status prevents items from being unaccounted for
- **Clearance** ensures accountability before personnel changes

