# KH Events Payment Integration

This document describes the comprehensive payment integration added to the KH Events plugin.

## Features

### ✅ Payment Gateway Support
- **Stripe Integration**: Full PaymentIntent API implementation with 3D Secure support
- **PayPal Placeholder**: Ready for future PayPal implementation
- **Extensible Architecture**: Abstract gateway system for easy addition of new payment providers

### ✅ Booking System Enhancements
- **Dynamic Pricing**: Real-time total calculation based on selected tickets
- **Secure Payment Forms**: Stripe Elements integration for PCI compliance
- **Payment Status Tracking**: Complete transaction lifecycle management
- **Automatic Refunds**: Refund processing when bookings are cancelled

### ✅ Admin Interface
- **Payment Settings**: Dedicated admin tab for gateway configuration
- **Booking Management**: Enhanced booking details with payment information
- **Refund Processing**: Manual refund interface with gateway integration
- **Status Indicators**: Color-coded payment and booking status displays

### ✅ Security & Compliance
- **PCI Compliance**: Secure tokenization prevents card data storage
- **Nonce Verification**: CSRF protection on all payment operations
- **Data Sanitization**: Input validation and sanitization
- **Audit Logging**: Comprehensive transaction logging

## Configuration

### 1. Payment Gateway Setup
1. Navigate to **KH Events → Settings → Payment**
2. Enable desired payment gateways
3. Configure API credentials:
   - **Stripe**: Enter Publishable Key and Secret Key
   - Set Test Mode to "Yes" for development

### 2. Event Configuration
1. Create or edit an event
2. Add ticket types with pricing in the "Tickets" meta box
3. Publish the event

### 3. Booking Form Integration
Use the shortcode on any page or post:
```
[kh_event_booking_form event_id="123"]
```

## Usage

### For Attendees
1. Visit the event page with the booking form
2. Select desired tickets and quantities
3. Enter personal information
4. Choose payment method and complete payment
5. Receive confirmation email

### For Administrators
1. **View Bookings**: Go to **KH Events → Bookings**
2. **Payment Details**: Click on any booking to see payment information
3. **Process Refunds**: Use the refund button in booking details
4. **Monitor Payments**: Check payment logs in `/wp-content/plugins/kh-events/logs/`

## Payment Flow

### Successful Payment
1. User selects tickets → Total calculated
2. Payment form appears → User enters card details
3. Stripe processes payment → Booking created
4. Confirmation email sent → Admin notified

### Failed Payment
1. Payment processing fails → Error displayed
2. User can retry → Booking not created
3. Transaction logged → Admin can investigate

### Refund Process
1. Admin changes booking status to "Cancelled"
2. Automatic refund triggered (if payment completed)
3. Payment status updated to "Refunded"
4. Refund logged and tracked

## Testing

### Test Payment Data
- **Card Number**: 4242 4242 4242 4242
- **Expiry**: Any future date
- **CVC**: Any 3 digits
- **Name**: Any name

### Test Scenarios
1. **Free Booking**: Set ticket price to $0
2. **Paid Booking**: Use test card for successful payment
3. **Failed Payment**: Use card number 4000 0000 0000 0002
4. **Refund**: Cancel a paid booking

## File Structure

```
wp-content/plugins/kh-events/
├── includes/
│   ├── class-kh-payment-gateways.php    # Payment gateway system
│   ├── gateways/
│   │   ├── class-kh-stripe-gateway.php  # Stripe implementation
│   │   └── class-kh-paypal-gateway.php  # PayPal placeholder
│   ├── class-kh-event-bookings.php      # Enhanced booking system
│   └── class-kh-events-admin-settings.php # Payment settings
├── assets/
│   ├── css/admin.css                    # Admin styling
│   └── js/booking.js                    # Frontend booking logic
└── logs/                                # Payment transaction logs
```

## API Reference

### Payment Handler Methods
```php
$payment_handler = KH_Payment_Handler::instance();
$gateways = $payment_handler->get_available_gateways();
$result = $payment_handler->process_payment('stripe', $payment_data);
$result = $payment_handler->refund_payment('stripe', $transaction_id);
```

### Booking Integration
```php
// Check payment status
$payment_status = get_post_meta($booking_id, '_kh_booking_payment_status', true);
$transaction_id = get_post_meta($booking_id, '_kh_booking_transaction_id', true);
$total_amount = get_post_meta($booking_id, '_kh_booking_total_amount', true);
```

## Troubleshooting

### Common Issues
1. **"Payment gateway not enabled"**: Configure gateway settings
2. **"Invalid API key"**: Check Stripe credentials
3. **"Card declined"**: Use test card numbers
4. **"Refund failed"**: Check gateway permissions

### Logs Location
Payment logs are stored in:
```
/wp-content/plugins/kh-events/logs/payment-YYYY-MM-DD.log
```

### Support
For issues with payment processing:
1. Check payment logs for error details
2. Verify gateway credentials
3. Test with Stripe's test mode
4. Contact gateway provider for API issues

## Security Notes

- Never store card details on your server
- Use HTTPS for all payment pages
- Regularly rotate API keys
- Monitor payment logs for suspicious activity
- Keep WordPress and plugins updated

## Future Enhancements

- PayPal Express Checkout integration
- Subscription/recurring payment support
- Multi-currency support
- Payment method restrictions by event
- Advanced reporting and analytics