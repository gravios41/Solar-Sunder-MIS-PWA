# AJAX System - Quick Reference Card

## File Locations

| File | Purpose |
|------|---------|
| `api/ajax-content.php` | AJAX endpoint - loads module content |
| `assets/js/ajax-loader.js` | Core AJAX engine & ModuleLoader class |
| `assets/js/dashboard-mobile.js` | Dashboard special handling |
| `config/ajax-config.php` | AJAX configuration & settings |
| `assets/css/style.css` | Mobile styles (end of file) |
| `includes/footer.php` | Script includes (modified) |

## Quick Commands

### Test if AJAX is working
```javascript
// Press F12, go to Console, type:
moduleLoader.loadModule('customers')
```

### Check current module
```javascript
console.log(moduleLoader.currentModule)
// Should return module name
```

### Check if mobile view
```javascript
console.log(moduleLoader.isMobile())
// true = mobile, false = desktop
```

### Clear cache
```javascript
moduleLoader.moduleCache = {}
```

### Manual reload
```javascript
moduleLoader.reloadCurrentModule()
```

## Common Tasks

### Add AJAX to a Form

```html
<!-- Add data-ajax attribute to form -->
<form action="api/customers-api.php" method="POST" data-ajax>
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" required>
    </div>
    <button type="submit">Save</button>
</form>
```

### Return JSON from API
```php
// In your API file (e.g., api/customers-api.php)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    // Your logic here
    $success = true;
    
    echo json_encode([
        'success' => $success,
        'message' => 'Operation completed',
        'data' => ['id' => 123] // optional
    ]);
    exit;
}
```

### Respond to Module Load Event
```javascript
// In your module-specific JavaScript

document.addEventListener('moduleLoaded', (e) => {
    if (e.detail.module === 'customers') {
        // Initialize your module
        console.log('Customers module loaded');
        // Call your init functions
        initializeCustomersUI();
    }
});
```

### Disable AJAX for a Module
```php
// In config/ajax-config.php
$AJAX_ENABLED_MODULES = [
    'my-module' => false,  // Will use full page load
];
```

## Browser Detection

### JavaScript
```javascript
const isMobile = window.innerWidth <= 768;
// or use moduleLoader.isMobile()
```

### CSS Media Query
```css
@media (max-width: 768px) {
    /* Mobile styles here */
}

@media (min-width: 769px) {
    /* Desktop styles here */
}
```

## URL Patterns

### AJAX Content Request
```
GET /api/ajax-content.php?module=customers&action=view
```

### Direct Module Access
```
GET /modules/customers.php
```

### API Endpoint (from AJAX form)
```
POST /api/customers-api.php
Headers: X-Requested-With: XMLHttpRequest
```

## Response Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 404 | Module not found |
| 403 | Permission denied |
| 500 | Server error |

## Toast Notifications

### Success Message
```javascript
showToast('Saved successfully!', 'success');
```

### Error Message
```javascript
showToast('Something went wrong', 'error');
```

### Warning Message
```javascript
showToast('Please review', 'warning');
```

### Info Message
```javascript
showToast('New update available', 'info');
```

## CSS Classes

### For Loading States
```css
.module-loader { /* Container */ }
.spinner { /* Loading animation */ }
```

### For Mobile Tables
```html
<table>
    <td data-label="Name">John Doe</td>
    <td data-label="Email">john@example.com</td>
</table>
```

### Page Content
```css
.page-body { /* Main content area */ }
```

## Debugging

### Show Module Info
```javascript
console.log(moduleLoader)
// See: currentModule, isLoading, isMobile, moduleCache
```

### Monitor Network
1. Open DevTools (F12)
2. Go to Network tab
3. Filter by XHR
4. Click navigation
5. Watch requests to api/ajax-content.php

### Check Errors
```javascript
// In Console, look for errors:
// ✅ None = good
// ❌ Any red = problem
```

### Performance Timing
```javascript
// Measure load time
console.time('load');
moduleLoader.loadModule('customers');
console.timeEnd('load');
```

## Troubleshooting Quick Fixes

### Modules not loading?
```javascript
// Check if loader exists
window.moduleLoader ? 'OK' : 'ERROR'

// Reload scripts
location.reload()
```

### Forms not submitting?
```javascript
// Check form has data-ajax
document.querySelector('form').hasAttribute('data-ajax')

// Check API endpoint exists
// Visit api/customers-api.php
```

### Styles not applying?
```javascript
// Clear cache
Ctrl + Shift + Del
// Or Cmd + Shift + Del on Mac

// Force refresh
Ctrl + F5
// Or Cmd + Shift + R on Mac
```

### Performance issues?
```javascript
// Check cache size
Object.keys(moduleLoader.moduleCache).length

// Clear cache if too large
moduleLoader.moduleCache = {}
```

## Browser DevTools Shortcuts

| Action | Shortcut |
|--------|----------|
| Open DevTools | F12 |
| Console | F12 → Console |
| Network | F12 → Network |
| Device Mode | Ctrl+Shift+M |
| Responsive Mode | Ctrl+Shift+K |
| Clear Cache | Ctrl+Shift+Del |
| Hard Refresh | Ctrl+F5 |

## API Response Examples

### Successful AJAX Form
```json
{
    "success": true,
    "message": "Customer saved successfully",
    "data": {
        "id": "123",
        "name": "John Doe"
    }
}
```

### Failed AJAX Form
```json
{
    "success": false,
    "message": "Name field is required",
    "data": null
}
```

### Module Content (HTML)
```html
<!-- Returned from api/ajax-content.php -->
<div class="card">
    <div class="card-header">
        <h3>Customers</h3>
    </div>
    <!-- ... module content ... -->
</div>
```

## Common Errors & Fixes

### "ModuleLoader is not defined"
- Check: `ajax-loader.js` loaded
- Fix: Verify file path in footer.php
- Test: `window.moduleLoader` in console

### "404 Not Found"
- Check: Module file exists
- Fix: Verify module name spelling
- Test: Visit `/modules/modulename.php` directly

### "Form data empty"
- Check: Form has fields with `name` attributes
- Fix: Add `name="fieldname"` to inputs
- Test: Check FormData in console

### "Styles not loading"
- Check: CSS file loaded (Network tab)
- Fix: Clear browser cache
- Test: Hard refresh (Ctrl+F5)

### "Cached content stale"
- Check: Module modified since last visit
- Fix: Clear cache: `moduleLoader.moduleCache = {}`
- Test: Reload module: `moduleLoader.reloadCurrentModule()`

## Configuration Options

### Breakpoint for Mobile/Desktop
```php
// In ajax-config.php or check in ajax-loader.js
const breakpoint = 768; // pixels
// ≤768 = mobile (AJAX)
// ≥769 = desktop (full page)
```

### Cache Strategy
- Content cached in `moduleLoader.moduleCache`
- Cleared on page refresh
- Cleared on new session
- Manually clear: `delete moduleLoader.moduleCache[moduleName]`

### Loading Timeout
- Default: No timeout (waits for response)
- Can add timeout if needed in ajax-loader.js

## Useful Resources

- **Full Docs**: AJAX_SYSTEM_README.md
- **Testing Guide**: AJAX_TESTING_GUIDE.md
- **Implementation**: IMPLEMENTATION_SUMMARY.md
- **Browser Console**: F12 in any modern browser

## Version Info

- **Version**: 1.0.0
- **Created**: 2026-08-17
- **Status**: Production Ready
- **Last Updated**: 2026-08-17

---

**For Complete Documentation**: See AJAX_SYSTEM_README.md  
**For Testing Procedures**: See AJAX_TESTING_GUIDE.md
