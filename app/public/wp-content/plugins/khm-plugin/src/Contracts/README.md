# KHM Contracts (Interfaces)

This directory contains the core interfaces that define KHM's architecture. All implementations must conform to these contracts to ensure consistency, testability, and extensibility.

---

## Available Contracts

### 1. **GatewayInterface** (`GatewayInterface.php`)
Payment gateway abstraction for processing charges, subscriptions, and refunds.

**Implementing Gateways**:
- Stripe (recommended for new integrations)
- PayPal Express
- Braintree
- Authorize.net
- 2Checkout

**Key Methods**:
- `authorize()`, `charge()`, `void()`, `refund()`
- `createSubscription()`, `updateSubscription()`, `cancelSubscription()`
- `createCustomer()`, `getCustomer()`

**Example**:
```php
$gateway = new StripeGateway($credentials);
$result = $gateway->charge($order);

if ($result->isSuccess()) {
    $txnId = $result->get('transaction_id');
    // Update order...
} else {
    error_log($result->getMessage());
}
```

---

### 2. **WebhookVerifierInterface** (`WebhookVerifierInterface.php`)
Webhook signature verification and event parsing.

**Purpose**:
- Verify HMAC-SHA256, MD5, or other signatures
- Parse JSON/form-encoded payloads into normalized Event objects
- Extract event ID and type for routing

**Key Methods**:
- `verify(string $payload, array $headers, string $secret): bool`
- `parseEvent(string $payload): object`
- `getEventId()`, `getEventType()`

**Example**:
```php
$verifier = new StripeWebhookVerifier();
if ($verifier->verify($payload, $headers, $secret)) {
    $event = $verifier->parseEvent($payload);
    // Process event...
}
```

---

### 3. **IdempotencyStoreInterface** (`IdempotencyStoreInterface.php`)
Tracks processed webhook events to prevent duplicate execution.

**Implementations**:
- `DatabaseIdempotencyStore` (WordPress DB)
- `RedisIdempotencyStore` (optional for high-traffic sites)

**Key Methods**:
- `hasProcessed(string $eventId): bool`
- `markProcessed(string $eventId, string $gateway, array $metadata): void`
- `cleanup(int $daysOld): int`

**Example**:
```php
$store = new DatabaseIdempotencyStore();
if ($store->hasProcessed($eventId)) {
    // Already processed, return 200
    return;
}
$store->markProcessed($eventId, 'stripe', ['type' => 'charge.succeeded']);
```

---

### 4. **OrderRepositoryInterface** (`OrderRepositoryInterface.php`)
CRUD operations for membership orders.

**Key Methods**:
- `create()`, `update()`, `find()`, `delete()`
- `findByCode()`, `findByPaymentTransactionId()`, `findByUser()`
- `updateStatus()`, `calculateTax()`, `generateCode()`

**Example**:
```php
$repo = new OrderRepository();
$order = $repo->create([
    'user_id' => 123,
    'membership_id' => 1,
    'subtotal' => 49.99,
    'gateway' => 'stripe',
]);
$repo->updateStatus($order->id, 'success');
```

---

### 5. **MembershipRepositoryInterface** (`MembershipRepositoryInterface.php`)
User membership lifecycle management.

**Key Methods**:
- `assign()`, `cancel()`, `expire()`
- `findActive()`, `findByLevel()`, `findExpiring()`
- `hasAccess()`, `updateEndDate()`

**Example**:
```php
$repo = new MembershipRepository();
$repo->assign(userId: 123, levelId: 1, options: [
    'start_date' => new DateTime(),
    'end_date' => new DateTime('+1 year'),
]);

if ($repo->hasAccess(123, 1)) {
    // Grant access...
}
```

---

### 6. **EmailServiceInterface** (`EmailServiceInterface.php`)
Email rendering and delivery with template support.

**Key Methods**:
- `send(string $templateKey, string $recipient, array $data): bool`
- `render(string $templateKey, array $data): string`
- `setFrom()`, `setSubject()`, `setHeaders()`, `addAttachment()`

**Template Hierarchy** (same as PMPro):
1. `wp-content/themes/child/khm/email/{locale}/{template}.html`
2. `wp-content/themes/child/khm/email/{template}.html`
3. `wp-content/themes/parent/khm/email/{locale}/{template}.html`
4. `wp-content/themes/parent/khm/email/{template}.html`
5. `wp-content/plugins/khm-plugin/email/{template}.html`

**Example**:
```php
$email = new EmailService();
$email->send('checkout_paid', 'user@example.com', [
    'name' => 'John Doe',
    'membership_level' => 'Premium',
    'sitename' => get_bloginfo('name'),
]);
```

---

### 7. **AccessControlInterface** (`AccessControlInterface.php`)
Content protection and membership access checks.

**Key Methods**:
- `hasAccess(int $userId, int $postId): bool`
- `getRequiredLevels(int $postId): array`
- `filterContent()`, `getAccessDeniedMessage()`
- `setAccessRules()`, `removeAccessRules()`

**Example**:
```php
$access = new AccessControl();
if (!$access->hasAccess($userId, $postId)) {
    echo $access->getAccessDeniedMessage($postId, $userId);
    return;
}
```

---

## Result Value Object

The `Result` class (`Result.php`) provides a standard return type for operations that can succeed or fail.

**Usage**:
```php
// Success
return Result::success('Order created', ['order_id' => 123]);

// Failure
return Result::failure('Payment declined', 'card_declined', ['code' => '4001']);

// Check result
if ($result->isSuccess()) {
    $orderId = $result->get('order_id');
}
```

**Properties**:
- `isSuccess()` / `isFailure()`
- `getMessage()`
- `getData()` / `get($key, $default)`
- `getErrorCode()`
- `toArray()`

---

## Implementation Guidelines

### 1. **Type Hinting**
All contracts use strict types. Implementations must respect:
- Return types (e.g., `Result`, `?object`, `bool`)
- Parameter types (e.g., `int`, `string`, `array`)

### 2. **Error Handling**
- Throw exceptions for **programmer errors** (invalid config, missing dependencies)
- Return `Result::failure()` for **runtime failures** (network errors, declined cards)
- Log errors but don't expose sensitive details to end users

### 3. **Extension Hooks**
Implementations should fire WordPress actions/filters at key points:
```php
do_action('khm_before_charge', $order);
$result = $gateway->charge($order);
do_action('khm_after_charge', $order, $result);
```

### 4. **Testing**
Every implementation should have:
- Unit tests with mocked dependencies
- Integration tests against sandbox/test gateways
- Idempotency tests for webhook handlers

---

## Next Steps

1. **Implement Services**: Create concrete classes under `src/Services/`
2. **Write Tests**: Add PHPUnit tests under `tests/`
3. **Document Hooks**: Catalog all `do_action` / `apply_filters` in `docs/hooks.md`
4. **Add Examples**: Provide usage examples in plugin documentation

---

## Related Documentation

- [Reusable Components Analysis](../docs/reusable_components.md)
- [Extension Points](../docs/extension_points.md)
- [Admin Flows](../docs/admin_flows.md)
