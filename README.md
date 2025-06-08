# 📅 Buukly – Smart Booking Plugin with Outlook Integration

**Buukly** is a lightweight and extendable booking plugin for WordPress with real-time **Outlook Calendar** integration. It’s ideal for professionals, law firms, clinics, or teams offering appointment-based services.

---

## 🚀 Features

- ✅ **Frontend Booking Form** – User-friendly booking flow with date, location, employee, and time slot selection.
- ✅ **Availability Management** – Set weekly availability per employee.
- ✅ **Location Support** – Create and manage multiple office locations.
- ✅ **📆 Outlook Integration** – 
  - Syncs with Outlook Calendar via Microsoft Graph API.
  - Prevents double-bookings by checking real-time calendar events.
  - WP Cron-based automatic sync every 10 minutes.
- ✅ **Admin Dashboard** – 
  - Clean UI using Bootstrap 5.
  - Booking insights via Chart.js (bookings per day, employee, location).
  - Manual sync trigger.
- ✅ **Email Notifications** – 
  - Booking confirmation to customer and staff.
  - Custom email layout in HTML.

---

## 📷 Screenshots

Coming soon!

---

## 🔧 Installation

1. Upload the plugin folder to `/wp-content/plugins/buukly/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Buukly → Outlook Connection** to connect your Microsoft account.
4. Set employee availability and locations.
5. Embed the booking form using a shortcode or custom theme integration.

---

## 🧠 Usage

You can place the booking form on any page using a shortcode like:

```php
[buukly_booking_form]
