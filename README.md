# TCM Vendor UI Plugin

Custom UI components for TCM vendor portal including category navigation and user-based styling.

## Features

### Category Navigator
- **Shortcode**: `[category_navigator]`
- Multi-level product navigation (Product Type → Category → Parts)
- Dynamic filtering based on product availability
- Mobile responsive design
- Integration with WooCommerce product categories

### User-Based CSS Classes
- Automatically adds CSS classes to `<body>` based on:
  - WordPress user roles (e.g., `tcm-administrator`, `tcm-customer`)
  - B2BKing customer groups (e.g., `tcm-vendor-group-name`)
  - Login status (`tcm-logged-in` or `tcm-guest`)

## Installation

### Via WordPress Admin
1. Build the plugin: `npm run build`
2. Navigate to `builds/` folder
3. Upload `tcm-vendor-ui-vX.X.X.zip` to WordPress
4. Activate the plugin

### Manual Installation
1. Upload the `tcm-vendor-ui` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin

## Development

### Setup
```bash
cd plugins/tcm-vendor-ui
npm install
```

### Build Commands
```bash
npm run build          # Patch: 1.0.0 → 1.0.1
npm run build:minor    # Minor: 1.0.0 → 1.1.0
npm run build:major    # Major: 1.0.0 → 2.0.0
```

### Directory Structure
```
tcm-vendor-ui/
├── tcm-vendor-ui.php              # Main plugin file
├── assets/
│   ├── css/
│   │   └── frontend.css           # Category navigator styles
│   └── js/
│       └── frontend.js            # Category navigator logic
├── includes/
│   ├── class-tcm-user-css.php     # User-based CSS classes
│   └── class-tcm-category-navigator.php  # Category navigation component
├── build.js                       # Build script
├── package.json                   # NPM configuration
└── README.md                      # This file
```

## Usage

### Category Navigator Shortcode
Add to any page or post:
```
[category_navigator]
```

### User CSS Classes
Classes are automatically added to the body tag:
- `tcm-logged-in` or `tcm-guest`
- `tcm-{role}` (e.g., `tcm-administrator`, `tcm-customer`)
- `tcm-vendor-{group-name}` (B2BKing groups)

Use these classes in your theme's CSS to customize styling per user type.

## Requirements
- WordPress 5.0+
- PHP 7.4+
- WooCommerce (for category navigator)
- B2BKing (optional, for vendor group classes)

## License
GPL v2 or later

## Author
Marcus & Claude
