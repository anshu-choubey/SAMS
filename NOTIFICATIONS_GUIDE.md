# SAMS Notification System Implementation Guide

## Overview
The SAMS notification system provides a unified way to show dialogs and notifications for CRUD operations (Create, Read, Update, Delete) across the admin panel.

## Features
- ✅ Success dialogs with success icon
- ✅ Error dialogs with error icon
- ✅ Warning dialogs with warning icon
- ✅ Info dialogs with info icon
- ✅ Confirmation dialogs
- ✅ Loading dialogs with spinner
- ✅ Toast notifications
- ✅ Callback support for chaining operations
- ✅ Fully responsive and animated

## Files Created
1. **public/assets/js/notifications.js** - Main notification system library
2. **public/admin/notifications-demo.html** - Demo page showing all notification types
3. **public/admin/departments.php** - Updated with new notification system

## Integration Steps

### Step 1: Include the Script
Add this to your HTML `<head>` or before your scripts:
```html
<script src="../assets/js/notifications.js"></script>
```

### Step 2: Use Notification Functions

#### Success Dialog
```javascript
showSuccessDialog('Success!', 'Department created successfully.', function() {
    // Callback after user clicks OK
    location.reload();
});
```

#### Error Dialog
```javascript
showErrorDialog('Error!', 'Failed to create department. Please try again.');
```

#### Confirmation Dialog
```javascript
showConfirmDialog(
    'Delete Department?',
    'Are you sure? This cannot be undone.',
    function() {
        // User confirmed
        deleteItem();
    },
    function() {
        // User cancelled
        console.log('Operation cancelled');
    }
);
```

#### Loading Dialog
```javascript
const loader = showLoadingDialog('Processing your request...');

// Do something async
fetch('/api/endpoint')
    .then(response => response.json())
    .then(data => {
        loader.hide();
        showSuccessDialog('Success!', 'Operation completed.');
    });
```

#### Toast Notification
```javascript
showToast('success', 'Success!', 'Department added successfully.');
```

## Implementation in Existing Admin Pages

### For Subjects Page
```javascript
// Add success notification after creating subject
fetch('/api/admin/subjects.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
    credentials: 'include'
})
.then(response => response.json())
.then(result => {
    if (result.success) {
        showSuccessDialog('Success!', 'Subject created successfully.', function() {
            location.reload();
        });
    } else {
        showErrorDialog('Error!', result.message);
    }
});
```

### For Users Page
```javascript
// Confirmation before deleting user
function deleteUser(userId, userName) {
    showConfirmDialog(
        'Delete User?',
        'Deleting "' + userName + '" cannot be undone.',
        function() {
            const loader = showLoadingDialog('Deleting user...');
            fetch('/api/admin/users.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId }),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                loader.hide();
                if (result.success) {
                    showSuccessDialog('Deleted!', 'User deleted successfully.', function() {
                        location.reload();
                    });
                } else {
                    showErrorDialog('Error!', result.message);
                }
            });
        }
    );
}
```

### For Teacher Assignments Page
```javascript
// Show loading while processing
const loader = showLoadingDialog('Assigning subjects to teacher...');

fetch('/api/admin/teacher-assignments.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(assignmentData),
    credentials: 'include'
})
.then(response => response.json())
.then(result => {
    loader.hide();
    if (result.success) {
        showSuccessDialog('Assigned!', 'Teacher has been assigned successfully.');
        setTimeout(() => location.reload(), 2000);
    } else {
        showErrorDialog('Assignment Failed', result.message);
    }
});
```

## Function Reference

### showSuccessDialog(title, message, callback)
- **Parameters:**
  - `title` (string): Dialog title
  - `message` (string): Dialog message
  - `callback` (function, optional): Function to execute after user clicks OK

### showErrorDialog(title, message, callback)
- **Parameters:** Same as success dialog

### showWarningDialog(title, message, callback)
- **Parameters:** Same as success dialog

### showInfoDialog(title, message, callback)
- **Parameters:** Same as success dialog

### showConfirmDialog(title, message, onConfirm, onCancel)
- **Parameters:**
  - `title` (string): Dialog title
  - `message` (string): Dialog message
  - `onConfirm` (function): Execute when user confirms
  - `onCancel` (function, optional): Execute when user cancels

### showLoadingDialog(message)
- **Parameters:**
  - `message` (string, optional): Loading message
- **Returns:** Object with `hide()` method

### showToast(type, title, message)
- **Parameters:**
  - `type` (string): 'success', 'danger', 'warning', or 'info'
  - `title` (string): Toast title
  - `message` (string): Toast message

## Common Use Cases

### 1. After Creating Data
```javascript
showSuccessDialog(
    'Created!',
    'New ' + entityName + ' has been created successfully.',
    function() { location.reload(); }
);
```

### 2. After Updating Data
```javascript
showSuccessDialog(
    'Updated!',
    entityName + ' has been updated successfully.',
    function() { location.reload(); }
);
```

### 3. After Deleting Data
```javascript
showSuccessDialog(
    'Deleted!',
    entityName + ' has been deleted successfully.',
    function() { location.reload(); }
);
```

### 4. Confirmation Before Delete
```javascript
showConfirmDialog(
    'Delete ' + entityName + '?',
    'This action cannot be undone.',
    deleteFunction,
    cancelFunction
);
```

### 5. Long Running Operations
```javascript
const loader = showLoadingDialog('Processing...');
asyncOperation()
    .then(() => {
        loader.hide();
        showSuccessDialog('Success!', 'Operation completed.');
    })
    .catch(error => {
        loader.hide();
        showErrorDialog('Error!', error.message);
    });
```

## Testing
Visit `http://localhost:8000/admin/notifications-demo.html` to test all notification types.

## Pages Already Updated
- ✅ departments.php - Fully updated with success, error, confirm, and loading dialogs

## Pages to Update
- [ ] subjects.php
- [ ] users.php
- [ ] teacher-assignments.php
- [ ] schedules.php
- [ ] settings.php
- [ ] reports.php

## Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
