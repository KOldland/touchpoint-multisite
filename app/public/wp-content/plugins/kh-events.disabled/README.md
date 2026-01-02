# KH Events Plugin

This is the KH Events plugin for 1927MSuite, providing comprehensive event management functionality with advanced features like recurring events, bookings, and filtering.

## Features

### Core Functionality
- **Event Management**: Create and manage events with rich metadata
- **Location Management**: Associate events with physical or virtual locations
- **Calendar Views**: Multiple display options (month calendar, list, day view)
- **Booking System**: Ticket-based event registration and management
- **Recurring Events**: Support for daily, weekly, monthly recurring events

### Advanced Features
- **Event Categories & Tags**: Hierarchical categorization and flexible tagging
- **Filtering & Search**: Filter events by category, tag, date, and location
- **AJAX Navigation**: Smooth calendar navigation without page reloads
- **Shortcode Integration**: Easy embedding in posts and pages
- **Widget Support**: Event filters widget for sidebars
- **Admin Interface**: Comprehensive event management dashboard

### Technical Features
- **Custom Post Types**: kh_event, kh_location, kh_booking
- **Custom Taxonomies**: kh_event_category, kh_event_tag
- **REST API Ready**: Full REST API support for headless implementations
- **Security**: Nonce verification, data sanitization, capability checks
- **Performance**: Optimized queries and AJAX loading

## Installation

1. Upload the plugin files to the `/wp-content/plugins/kh-events` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings as needed.

## Usage

### Shortcodes

#### Calendar View
```
[kh_events_calendar month="12" year="2025" category="music" tag="featured"]
```
Parameters:
- `month`: Month number (1-12)
- `year`: Year (4 digits)
- `category`: Event category slug
- `tag`: Event tag slug

#### List View
```
[kh_events_list limit="10" category="workshops"]
```
Parameters:
- `limit`: Number of events to display
- `category`: Event category slug
- `tag`: Event tag slug

#### Day View
```
[kh_events_day date="2025-12-25" category="holidays"]
```
Parameters:
- `date`: Date in YYYY-MM-DD format
- `category`: Event category slug
- `tag`: Event tag slug

### Widgets

The plugin includes the **KH Event Filters** widget for filtering events by category and tag.

### Admin Features
- **Settings Page**: Comprehensive configuration with tabbed interface
  - General settings (currency, date/time formats)
  - Google Maps API configuration
  - Email settings and notifications
  - Booking and registration options
  - Display preferences
- **Enhanced Admin Interface**: 
  - Custom columns for event date, location, and booking counts
  - Advanced filtering by category, tag, date, and status
  - Sortable columns and bulk actions
  - Event duplication functionality
- **Dashboard Widget**: Quick overview with statistics and recent events
- **Admin Validation**: Real-time form validation and error checking

## Event Metadata

Events support the following metadata:
- Start Date & Time
- End Date & Time
- Recurring Options (daily, weekly, monthly)
- Location (linked to location post type)
- Ticket Information
- Categories & Tags

## Booking System

- **Ticket Management**: Define ticket types, prices, and availability
- **Registration Forms**: AJAX-powered booking forms
- **Admin Management**: View and manage all bookings
- **Capacity Limits**: Set maximum attendees per event

## Development

### File Structure
```
kh-events/
├── kh-events.php                 # Main plugin file
├── includes/
│   ├── class-kh-events.php       # Main plugin class
│   ├── class-kh-event-meta.php   # Event metadata handling
│   ├── class-kh-location-meta.php # Location metadata
│   ├── class-kh-events-views.php # Shortcodes and display logic
│   ├── class-kh-event-tickets.php # Ticket management
│   ├── class-kh-event-bookings.php # Booking system
│   ├── class-kh-recurring-events.php # Recurring event logic
│   └── class-kh-event-filters-widget.php # Filter widget
├── assets/
│   ├── css/kh-events.css         # Plugin styles
│   └── js/kh-events.js           # JavaScript functionality
├── languages/                    # Translation files
├── test-kh-events.php           # Test suite
├── validate-plugin.php          # Validation script
└── README.md                    # This file
```

### Hooks & Filters

The plugin provides several WordPress hooks for customization:

- `kh_get_events_for_month`: Filter events for calendar display
- `kh_event_booking_data`: Filter booking data before processing
- `kh_recurring_occurrences`: Filter generated recurring event dates

### AJAX Endpoints

- `kh_load_calendar`: Load calendar for different months
- `kh_submit_booking`: Process event bookings

## Testing

Run the validation script:
```bash
php validate-plugin.php
```

Run the comprehensive test suite:
```bash
php test-kh-events.php
```

## Requirements

- WordPress 5.6+
- PHP 7.1+
- MySQL 5.6+

## Changelog

### 1.1.0 (Admin Experience Enhancement)
- Added comprehensive admin settings page with tabbed interface
- Enhanced event management with custom columns and advanced filtering
- Added dashboard widget with event statistics and recent events overview
- Implemented event duplication functionality
- Added admin CSS and JavaScript for improved user experience
- Integrated Google Maps API key management
- Added email and booking configuration options
- Enhanced admin interface with sortable columns and bulk actions

### 1.0.0
- Initial release with basic event and location post types
- Calendar, list, and day view shortcodes
- Basic booking system
- Event categories and tags
- Recurring events support
- AJAX calendar navigation
- Filter widget
- Calendar, list, and day view shortcodes
- Basic booking system
- Event categories and tags
- Recurring events support
- AJAX calendar navigation
- Filter widget

## Future Enhancements

- Admin settings page
- Payment gateway integration
- Email notifications
- GDPR compliance features
- Multilingual support
- Integration with other 1927MSuite plugins
- Advanced reporting and analytics

## License

This plugin is part of the 1927MSuite and follows the same licensing terms.