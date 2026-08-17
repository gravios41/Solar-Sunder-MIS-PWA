# AJAX System - Implementation & Testing Guide

## Quick Start Checklist

### ✅ Initial Setup
- [x] `ajax-content.php` - AJAX endpoint created
- [x] `ajax-loader.js` - Module loader script created
- [x] `dashboard-mobile.js` - Dashboard configuration created
- [x] `ajax-config.php` - Configuration file created
- [x] CSS updates - Mobile styles and animations added
- [x] Footer update - Scripts included in all pages

### 🔍 Verification Steps

#### 1. Check File Creation
Verify these files exist:
```
✓ api/ajax-content.php
✓ assets/js/ajax-loader.js
✓ assets/js/dashboard-mobile.js
✓ config/ajax-config.php
```

#### 2. Verify Script Inclusion
In `includes/footer.php`, ensure these scripts are loaded:
```html
<script src="assets/js/ajax-loader.js"></script>
<script src="assets/js/dashboard-mobile.js"></script>
```

#### 3. Check CSS Updates
In `assets/css/style.css`, verify these sections exist:
- Mobile media queries (≤768px)
- `.module-loader` styles
- `.spinner` animation
- Responsive table styles

## Testing Procedures

### Desktop Testing (Full Page Loads)

#### Test Environment
- Use Chrome DevTools Desktop view or full-screen browser
- Minimum width: 1200px
- Recommended: 1920x1080

#### Test Cases
1. **Navigation**
   - [ ] Click Dashboard → Full page loads
   - [ ] Click Customers → Full page loads
   - [ ] Browser URL updates correctly
   - [ ] Back button works normally

2. **Module Functionality**
   - [ ] All modules load correctly
   - [ ] Forms work properly
   - [ ] Filters and search work
   - [ ] Add/Edit/Delete operations work

3. **Performance**
   - [ ] Page loads smoothly
   - [ ] No lag or delays
   - [ ] All styling correct

### Mobile Testing (AJAX Loads)

#### Test Environment
- Chrome DevTools Mobile Emulation
- iPhone 12/13 (390x844)
- Android devices (360x640)
- Or actual mobile device

#### Test Cases
1. **AJAX Navigation**
   - [ ] Click Customers → Shows loading spinner
   - [ ] Content loads without full page refresh
   - [ ] URL updates via history API
   - [ ] Mobile menu closes after navigation

2. **Content Display**
   - [ ] Module content displays correctly
   - [ ] Content is scrollable
   - [ ] No cut-off content or overflow
   - [ ] Font size is readable (≥16px)

3. **Form Interactions**
   - [ ] Click "Add" button → Opens modal/form
   - [ ] Fill form fields
   - [ ] Submit form → Shows success message
   - [ ] Content refreshes after save
   - [ ] No page reload occurs

4. **Navigation Back/Forward**
   - [ ] Browser back button works
   - [ ] Previous module content loads from cache
   - [ ] Back button doesn't reload page
   - [ ] Forward button works correctly

5. **Dashboard Special Case**
   - [ ] Click Dashboard → Full page loads (not AJAX)
   - [ ] All dashboard features work
   - [ ] PWA banner displays
   - [ ] Stats and charts render correctly

6. **Scrolling & Performance**
   - [ ] Module content scrollable
   - [ ] No scroll stuck on dialogs
   - [ ] Smooth scrolling transitions
   - [ ] Touch scrolling works (mobile)

### Network Testing

#### Network Throttling (Chrome DevTools)
1. Open DevTools → Network tab
2. Set throttling to "Fast 3G"

#### Test Cases
- [ ] AJAX requests complete within 2 seconds
- [ ] Loading indicator shows during load
- [ ] Content displays when complete
- [ ] No errors in console
- [ ] Cached content loads instantly on second visit

### Error Handling

#### Simulate Network Issues
1. Go to Network tab
2. Check "Offline"

#### Test Cases
- [ ] Show appropriate error message
- [ ] Allow retry action
- [ ] Don't crash the application

#### Simulate Missing Module
Edit URL manually: `ajax-content.php?module=nonexistent`
- [ ] Return 404 error
- [ ] Show error message to user

## Console Debugging

### Open Browser Console
Press `F12` or right-click → Inspect → Console tab

### Check for Errors
Look for any red error messages:
```javascript
// Good - No errors
// Common error - "ModuleLoader is not defined"
// This means ajax-loader.js didn't load properly
```

### Test Module Loader API
```javascript
// In console, test these commands:

// Check if module loader is active
window.moduleLoader

// Check current module
moduleLoader.currentModule

// Check cache
moduleLoader.moduleCache

// Manually load a module
moduleLoader.loadModule('customers')

// Check if mobile
moduleLoader.isMobile()

// Check dashboard config
window.dashboardMobileConfig
```

## Performance Benchmarks

### Target Metrics

| Metric | Desktop | Mobile |
|--------|---------|--------|
| Initial Load | < 1s | < 1s |
| AJAX Load | N/A | < 1.5s |
| Cached Load | < 100ms | < 100ms |
| Form Submit | < 1s | < 2s |

### Measure Performance
```javascript
// In console:
console.time('module-load');
moduleLoader.loadModule('customers');
// Wait for load
console.timeEnd('module-load');
```

## Module-Specific Testing

### Customers Module
- [ ] List displays properly
- [ ] Add customer works
- [ ] Edit customer works
- [ ] Delete customer works
- [ ] Search/filter works

### Quotations Module
- [ ] List displays
- [ ] Create quotation works
- [ ] Quotation details show
- [ ] Print functionality works

### Projects Module
- [ ] Project list loads
- [ ] Project details accessible
- [ ] Status updates work
- [ ] Timeline displays correctly

### Inventory Module
- [ ] Item list displays
- [ ] Quantity updates work
- [ ] Low stock indicators show
- [ ] Orders can be created

### Dashboard Module
- [ ] Always loads with full page (not AJAX)
- [ ] Stats cards display
- [ ] Charts render
- [ ] PWA install banner shows
- [ ] All widgets functional

## Troubleshooting Common Issues

### Issue: "Scripts not loading"
```
Solution:
1. Check Network tab in DevTools
2. Verify file paths are correct
3. Check for 404 errors
4. Clear browser cache (Ctrl+Shift+Del)
5. Restart browser
```

### Issue: "AJAX loads but content wrong"
```
Solution:
1. Check console for JavaScript errors
2. Verify ajax-content.php path is correct
3. Check if module file exists
4. Verify module permissions
5. Check browser console for error details
```

### Issue: "Forms don't submit"
```
Solution:
1. Check if form has data-ajax attribute
2. Verify API endpoint path is correct
3. Check Network tab for request status
4. Verify permissions are set correctly
5. Check API response in Network tab
```

### Issue: "Styles not applying on mobile"
```
Solution:
1. Clear cache (Ctrl+Shift+Del)
2. Verify CSS file loaded (Network tab)
3. Check media queries in style.css
4. Disable browser extensions
5. Test in incognito/private mode
```

### Issue: "Dashboard loads as AJAX instead of full page"
```
Solution:
1. Verify dashboard-mobile.js is loaded
2. Check console for errors
3. Clear cache and reload
4. Verify window.moduleLoader exists
5. Check if mobile detection works: moduleLoader.isMobile()
```

## Browser-Specific Testing

### Chrome/Chromium
- [ ] DevTools Mobile Emulation works
- [ ] Network throttling works
- [ ] Console shows no errors
- [ ] Performance metrics accurate

### Firefox
- [ ] Responsive Design Mode works
- [ ] DevTools shows requests correctly
- [ ] Scrolling smooth
- [ ] Forms submit properly

### Safari (iOS)
- [ ] Touch scrolling smooth
- [ ] Buttons responsive
- [ ] Forms keyboard appears correctly
- [ ] No auto-zoom on inputs

### Edge
- [ ] Similar to Chrome
- [ ] DevTools functionality
- [ ] Performance metrics

## Final Verification

### Before Deployment
- [ ] All modules load correctly on mobile
- [ ] All modules load correctly on desktop
- [ ] No console errors
- [ ] Forms work properly
- [ ] Dashboard loads as full page
- [ ] Scrolling works on all devices
- [ ] Performance acceptable
- [ ] Error handling works

### Performance Checklist
- [ ] Initial page load < 2 seconds
- [ ] AJAX module load < 1.5 seconds
- [ ] Cached loads instant (< 200ms)
- [ ] No memory leaks in console
- [ ] Network requests minimal

## Rollback Plan

If issues arise:
1. Disable AJAX in `config/ajax-config.php`
2. All modules will use full page loads
3. Application continues to function normally
4. Debug and fix before re-enabling

## Additional Resources

- [AJAX_SYSTEM_README.md](AJAX_SYSTEM_README.md) - Full documentation
- [MDN Web Docs - Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)
- [MDN Web Docs - History API](https://developer.mozilla.org/en-US/docs/Web/API/History_API)
- Chrome DevTools - F12 key

---

**Last Updated**: 2026-08-17  
**Status**: Ready for Testing
