# Tracking Script Instructions

## Overview
We have a centralized tracking file located at `js/tracking.js`. This file handles the insertion of various tracking codes (like Google Tag Manager, Facebook Pixel, etc.) into the website's pages.

This approach allows us to:
1.  Manage all tracking IDs in one place.
2.  Add/Remove pixels without editing every single HTML file.
3.  Ensure consistent implementation across the funnel.

## How to Install
To enable tracking on a new page, simply add this script reference in the `<head>` section of your HTML file, as close to the top as possible (or wherever common scripts are loaded).

```html
<script src="../js/tracking.js"></script>
```
*Note: Adjust the path (`../js/`) relative to where your HTML file is located.*

## Adding New Tracking Codes
To add a new pixel (e.g., Facebook, TikTok):
1.  Open `js/tracking.js`.
2.  Inject the script using the helper functions:
    *   `injectScript(codeString, 'head')`
    *   `injectHTML(htmlString, 'body')`

### Example: Adding a generic pixel
```javascript
const myPixelCode = `
  console.log('My Pixel Loaded');
`;
injectScript(myPixelCode, 'head');
```

### Example: Adding Facebook Pixel
1.  Open `js/tracking.js`.
2.  Uncomment the Facebook Pixel section at the bottom.
3.  Replace `YOUR_PIXEL_ID_HERE` with your actual Pixel ID (e.g., `1234567890`).

```javascript
// In js/tracking.js
fbq('init', '1234567890'); // Your Real ID
```
