# AJAX Module Loading System - Documentation

## Overview

This document explains how the new AJAX module loading system works in the Sunder Solar MIS application. The system enables dynamic loading of modules on mobile devices while maintaining full page loads on desktop for optimal performance and user experience.

## Key Features

### 1. **Mobile AJAX Loading**
- On mobile devices (≤768px), clicking module links triggers AJAX loading
- Content loads dynamically without full page refresh
- Smooth transitions and loading indicators
- Better scrolling experience - no more stuck dialogs

### 2. **Desktop Full Page Load**
- On desktop, full page loads are maintained for better performance
- Unaffected by mobile optimizations
- Traditional navigation behavior

### 3. **Dashboard Special Handling**
- Dashboard always loads as full page (both mobile and desktop)
- Maintains full PWA experience and animations
- Preserves all interactive elements

### 4. **Content Caching**
- Loaded modules are cached in browser memory
- Subsequent visits to the same module are instant
- Cache is cleared when content needs to be reloaded

## File Structure

### New Files Created

1. **`api/ajax-content.php`**
   - Main AJAX endpoint for loading module content
   - Returns only the module body content without header/footer
   - Usage: `GET /api/ajax-content.php?module=customers&action=view`

2. **`assets/js/ajax-loader.js`**
   - Core AJAX loading engine
   - Class: `ModuleLoader`
   - Handles navigation, caching, and content injection
   - Manages form submissions and AJAX operations within loaded modules

3. **`assets/js/dashboard-mobile.js`**
   - Dashboard-specific configuration
   - Ensures dashboard always uses full page load
   - Mobile optimizations for non-dashboard modules

4. **`config/ajax-config.php`**
   - Configuration file for AJAX behavior
   - Defines which modules support AJAX
   - Utility functions for AJAX response handling

5. **Updated CSS** (`assets/css/style.css`)
   - Mobile responsive styles for AJAX-loaded content
   - Loading spinner animation
   - Table responsive design
   - Form optimizations for mobile

## How It Works

### Desktop Behavior (≥769px)
```
User clicks module link
    ↓
Normal full page navigation
    ↓
Browser loads complete page with header, content, footer
```

### Mobile Behavior (≤768px)
```
User clicks module link
    ↓
AJAX loader intercepts click
    ↓
Fetches content via api/ajax-content.php
    ↓
Content cached in memory
    ↓
Content injected into .page-body
    ↓
Loading animation removed
    ↓
Smooth scroll to content
```

## Usage for Developers

### Adding AJAX Support to a Module

Modules work with AJAX by default. However, to optimize a module for AJAX loading:

1. **Ensure forms have `data-ajax` attribute** (if using AJAX submission):
   ```html
   <form action="api/customers-api.php" method="POST" data-ajax>
       <!-- form fields -->
   </form>
   ```

2. **Use proper response format**:
   ```php
   // In API handlers
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
       header('Content-Type: application/json');
       echo json_encode(['success' => true, 'message' => 'Saved successfully']);
       exit;
   }
   ```

3. **Module-specific initialization**:
   ```javascript
   document.addEventListener('moduleLoaded', (e) => {
       if (e.detail.module === 'customers') {
           // Your initialization code
           initializeCustomersModule();
       }
   });
   ```

### Disabling AJAX for a Module

To disable AJAX for a specific module:

1. Edit `config/ajax-config.php`
2. Set the module to `false`:
   ```php
   $AJAX_ENABLED_MODULES = [
       'my-module' => false,  // Disabled
   ];
   ```

### Form Submission via AJAX

The `ModuleLoader` class automatically handles form submissions for forms with `data-ajax` attribute:

```javascript
// The loader will:
// 1. Intercept form submission
// 2. Send data via AJAX to the form's action endpoint
// 3. Handle JSON response
// 4. Show toast notifications
// 5. Reload module if successful
```

## Mobile-Specific Optimizations

### Responsive Tables
Tables are automatically responsive on mobile:
- Header row hidden
- Each cell labeled with `data-label` attribute
- Cells stack vertically

### Form Layout
- Single column on mobile
- Proper spacing and sizing
- 16px font size for inputs (prevents iOS zoom)

### Content Display
- Touch-friendly scrolling
- Optimized padding and margins
- Better contrast for small screens

## JavaScript API

### ModuleLoader Class

```javascript
// Instance available globally as window.moduleLoader

// Load a module
moduleLoader.loadModule('customers', navElement);

// Get current module
moduleLoader.currentModule;

// Check if mobile
moduleLoader.isMobile();

// Clear cache for a module
delete moduleLoader.moduleCache['customers'];

// Reload current module
moduleLoader.reloadCurrentModule();
```

### Events

Listen for module loading events:
```javascript
document.addEventListener('moduleLoaded', (e) => {
    console.log('Module loaded:', e.detail.module);
});
```

## CSS Classes

### Loading Indicator
```css
.module-loader {
    /* Container for loading spinner */
}

.spinner {
    /* Animated loading spinner */
}
```

### Page Body
```css
.page-body {
    /* Main content area for AJAX-loaded modules */
}
```

## Performance Considerations

### Browser Cache
- Modules are cached in memory during the session
- Cache persists across multiple visits (same session)
- Cache is cleared on page refresh or new session

### Network Usage
- AJAX requests are smaller (no header/footer)
- Reduced bandwidth consumption on mobile
- Faster subsequent loads from cache

### Device Performance
- Less rendering on each navigation
- Smoother transitions and animations
- Better battery life on mobile devices

## Browser Compatibility

- Chrome 70+
- Firefox 60+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

### Module not loading
- Check browser console for errors
- Verify module name in `ajax-config.php`
- Check network tab for failed requests

### Styles not applying
- Ensure CSS file is loaded: `assets/css/style.css`
- Check for CSS conflicts
- Clear browser cache

### Forms not submitting
- Ensure forms have proper `action` attribute
- Check API endpoint exists
- Verify `data-ajax` attribute is present

### Dashboard not loading
- Dashboard always uses full page load - this is intentional
- Clear browser history/cache if needed
- Check if JavaScript is enabled

## Best Practices

1. **Always include proper error handling**
   - Use try-catch in async functions
   - Show user-friendly error messages

2. **Optimize module content**
   - Minimize initial HTML size
   - Lazy load heavy assets
   - Use proper image optimization

3. **Mobile-first design**
   - Design for mobile first
   - Test on actual devices
   - Use DevTools device emulation

4. **Performance monitoring**
   - Monitor load times
   - Check console for errors
   - Use browser Performance API

## Future Enhancements

- Service worker integration for offline support
- Preloading of frequently used modules
- Smart cache invalidation
- Module-specific data syncing
- Real-time updates via WebSocket

## Support

For issues or questions regarding the AJAX system:
1. Check browser console for errors
2. Review this documentation
3. Check module API endpoints
4. Test in both mobile and desktop views

---

**Version**: 1.0.0  
**Last Updated**: 2026-08-17
