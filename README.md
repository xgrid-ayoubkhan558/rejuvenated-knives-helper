# Rejuvenated Knives Helper

A powerful WordPress plugin designed to enhance the WooCommerce checkout experience, implement complex shipping/pickup logic, and provide deep integration for local service areas.

## 🚀 Key Features

### 🛒 Advanced Cart Logic
- **[WooAjaxCartCount]**: A flexible shortcode to display an AJAX-powered cart count and total anywhere on your site.
- **Minimum Order Value**: Enforce minimum purchase requirements (based on subtotal or total) with customizable formulas.
- **Quantity Overwrite**: Option to overwrite cart quantities instead of adding them, ensuring only the most recently selected amount is in the cart.
- **Bricks Builder Sync**: Specialized sync logic to keep quantity inputs in the Bricks Builder frontend perfectly aligned with the WooCommerce cart state.

### 🏁 Powerful Dynamic Checkout
- **Location Intelligence**: Custom fields for Region and City Search linked to a specialized 'Locations' taxonomy.
- **Service Type Switching**: Automatically detects and switches between **Door-to-Door** and **Mail-in** service types based on the customer's city.
- **Intelligent Date Picker**: Integrated Flatpickr that reads regional pickup schedules (Days of the week, start/end times) to provide accurate selection.
- **Auto-Payment Mapping**: Automatically select specific payment gateways (like COD) when a customer is within a valid door-to-door service area.

### 🎨 Checkout UI & UX Tweaks
- **Semantic Renaming**: Option to rename "Billing" to "Delivery" across the entire checkout flow for service-based businesses.
- **Field Reordering**: Move the email field after name fields to follow a more natural data entry flow.
- **Phone Formatting**: Automatic country code prefixing and strict USA phone number validation/formatting (555-123-4567).
- **Cleanup Toggles**: Easily hide unnecessary checkout fields like Zipcode, Country, or State to reduce friction.
- **Dynamic Body Classes**: Adds classes like `rk-city-found`, `rk-city-not-found`, and `rk-city-selected` to the body, allowing for deep CSS customization based on user state.

### 🛡️ Admin & Control
- **Consolidated Dashboard**: All settings are unified under **WooCommerce > RK Helper** with a clean, tabbed interface.
- **Custom Messaging**: Tailor the messages shown to customers when their city is found, not found (Mail-in offer), or selected.
- **Role Restrictions**: Built-in security to restrict the admin menu for Shop Managers, keeping the backend clean and secure.
- **Pickup Column**: Enhance the Locations admin screen with styled pickup schedule columns for easy management.

## 🛠 Installation

1. **Upload**: Place the `rejuvenated-knives-helper` folder into your `/wp-content/plugins/` directory.
2. **Activate**: Enable the plugin through the 'Plugins' menu in WordPress.
3. **Configure**: Navigate to **WooCommerce > RK Helper** to set up your rules and preferences.
4. **Setup Locations**: Go to **Products > Locations** (taxonomy) to define your service areas and their specific pickup schedules.

## 📋 Requirements

- **WordPress**: 5.0+
- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **ACF PRO** (Recommended): To unlock advanced per-region pickup schedule overrides.

## 💻 Developer Info

### Shortcodes
- `[WooAjaxCartCount]`: Renders the AJAX cart fragment.

### JavaScript Events/Data
The plugin exposes several data objects to the frontend for custom integrations:
- `rk_check_fields_data`: Full regions and cities JSON.
- `rk_check_fields_options`: Plugin settings and date formats.

---
*Created by the Rejuvenated Knives Team.*
