# Frontend Scope Documentation

## Overview

The KH-SMMA plugin provides a **complete frontend admin interface** built with jQuery and vanilla JavaScript. It is NOT a headless API-only plugin. This document clarifies what UI components are included vs. what external applications need to build.

---

## ✅ IN SCOPE (Provided by Plugin)

### 1. Admin UI Components

The plugin includes fully functional jQuery-based UI components:

#### **Generation Modal** (`PromoteModal`)
- **File**: [assets/js/smma-admin.js:173-374](../assets/js/smma-admin.js#L173-L374)
- **Features**:
  - Multi-variant generation configuration
  - Phase/tone/GEO targeting selection
  - Series generation toggle
  - Real-time variant rendering with phase/compliance badges
  - Variant selection interface
  - Direct integration with `/generate` endpoint

#### **Scheduling Modal** (`CalendarModal`)
- **File**: [assets/js/smma-admin.js:376-487](../assets/js/smma-admin.js#L376-L487)
- **Features**:
  - Date/time picker for each variant
  - GEO target configuration per variant
  - LinkedIn Boost toggle
  - Batch scheduling confirmation
  - Direct integration with `/schedule` endpoint

#### **Variant Editor Modal** (`EditVariantModal`)
- **File**: [assets/js/smma-admin.js:489-557](../assets/js/smma-admin.js#L489-L557)
- **Features**:
  - Text editing interface
  - Live compliance re-validation
  - Visual feedback (success/error messages)
  - Direct integration with `/variant-edit` endpoint

#### **Approval Workflow** (Approve/Reject)
- **File**: [assets/js/smma-admin.js:559-605](../assets/js/smma-admin.js#L559-L605), [624-641](../assets/js/smma-admin.js#L624-L641)
- **Features**:
  - One-click approval with confirmation
  - Reject modal with reason field
  - Status update feedback
  - Direct integration with `/approve` and `/reject` endpoints

### 2. API Client Wrapper

**File**: [assets/js/smma-admin.js:10-112](../assets/js/smma-admin.js#L10-L112)

Provides JavaScript wrapper for all REST endpoints:
- `SMMA_API.generate()` - Generate variants
- `SMMA_API.schedule()` - Schedule variants
- `SMMA_API.editVariant()` - Edit variant text
- `SMMA_API.approve()` - Approve variant
- `SMMA_API.reject()` - Reject variant
- `SMMA_API.getSponsor()` - Fetch sponsor metadata

Includes automatic:
- Nonce header injection
- Error handling
- Request/response serialization

### 3. Modal Management System

**File**: [assets/js/smma-admin.js:114-170](../assets/js/smma-admin.js#L114-L170)

Reusable modal framework:
- `ModalManager.create()` - Create modal with custom content
- `ModalManager.open()` - Show modal with fade animation
- `ModalManager.close()` - Hide modal
- `ModalManager.destroy()` - Remove from DOM

### 4. Styling & UI Design

**File**: [assets/css/smma-admin.css](../assets/css/smma-admin.css)

Complete CSS for:
- Phase badges (Attention, Anxiety, Acceptance, Antagonistic)
- Compliance badges (OK, WARN, FAIL)
- Modal layouts with animations
- Variant cards with hover effects
- Form elements and buttons
- Responsive design for mobile/tablet
- Loading spinners
- Success/error message styling

### 5. Event Handlers

**File**: [assets/js/smma-admin.js:608-648](../assets/js/smma-admin.js#L608-L648)

Document-level event delegation for:
- `.khm-smma-promote-btn` - Open generation modal
- `.khm-smma-edit-variant-btn` - Open edit modal
- `.khm-smma-approve-btn` - Approve variant with confirmation
- `.khm-smma-reject-btn` - Open reject modal
- `.khm-smma-boost-btn` - Quick boost (placeholder)

---

## ❌ OUT OF SCOPE (Not Provided)

### 1. Modern Framework Components
- ❌ React components
- ❌ Vue components
- ❌ Angular modules
- ❌ Svelte components

**Reason**: Plugin uses traditional WordPress admin patterns (jQuery + PHP templates). Consuming applications that need modern framework integration must build their own components using the REST API.

### 2. Headless/Standalone UI Library
- ❌ NPM package for standalone UI
- ❌ Embeddable widgets for external sites
- ❌ Iframe-based integration

**Reason**: UI is tightly coupled to WordPress admin environment (WP nonce, admin styles, WordPress-specific HTML structures).

### 3. Mobile App UI
- ❌ iOS/Android native components
- ❌ React Native components
- ❌ Flutter widgets

**Reason**: Plugin targets WordPress admin dashboard. Mobile apps must consume the REST API directly and build native UI.

### 4. Advanced Dashboards
- ❌ Analytics/reporting dashboards
- ❌ Campaign performance metrics UI
- ❌ A/B testing result visualization
- ❌ Multi-sponsor comparison views

**Reason**: Plugin provides operational UI (generate, schedule, approve). Business intelligence and analytics are external concerns.

### 5. Third-Party Platform Integration UI
- ❌ LinkedIn Campaign Manager UI
- ❌ Google Ads dashboard integration
- ❌ Multi-platform publishing interfaces

**Reason**: Plugin provides export functionality and programmatic posting. Direct platform UI is handled by respective platforms (LinkedIn Ads Manager, Google Ads Console).

---

## Integration Patterns

### For WordPress Sites (Recommended)
✅ **Use the provided jQuery UI directly**
- Enqueue `assets/js/smma-admin.js` and `assets/css/smma-admin.css`
- Add trigger buttons with appropriate data attributes:
  ```html
  <button class="khm-smma-promote-btn button"
          data-post-id="123"
          data-post-title="My Post"
          data-phase="Attention">
      Promote
  </button>
  ```
- Plugin handles all modal rendering and API integration

### For Headless/Decoupled Applications
✅ **Build custom UI consuming REST API**

**Available Endpoints**:
- `POST /wp-json/kh-smma/v1/generate` - Generate variants
- `POST /wp-json/kh-smma/v1/schedule` - Schedule variants
- `POST /wp-json/kh-smma/v1/variant-edit` - Edit variant
- `POST /wp-json/kh-smma/v1/approve` - Approve variant
- `POST /wp-json/kh-smma/v1/reject` - Reject variant
- `GET /wp-json/kh-smma/v1/sponsor/{id}` - Get sponsor details
- `POST /wp-json/kh-smma/v1/boost-prepare` - Prepare manual export

**Authentication**: WordPress nonce (`X-WP-Nonce` header) or application passwords

**Example React Hook**:
```javascript
import { useEffect, useState } from 'react';

function useSmmaApi(baseUrl, nonce) {
  return {
    generate: (postId, options) =>
      fetch(`${baseUrl}/generate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ post_id: postId, ...options }),
      }),
    // ... other methods
  };
}
```

### For Mobile Apps
✅ **Use WordPress Application Passwords + REST API**

1. Enable Application Passwords in WordPress (WP 5.6+)
2. Generate credentials for mobile app
3. Use Basic Auth with REST API
4. Build native UI components

**Example iOS (Swift)**:
```swift
struct SmmaService {
    let baseURL: URL
    let credentials: String // Base64(username:app_password)

    func generateVariants(postId: Int, options: [String: Any]) async throws -> [Variant] {
        var request = URLRequest(url: baseURL.appendingPathComponent("/wp-json/kh-smma/v1/generate"))
        request.httpMethod = "POST"
        request.setValue("Basic \(credentials)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: options)
        // ... handle response
    }
}
```

---

## FAQ

### Q: Can I use the plugin with Next.js/Gatsby/Nuxt?
**A**: Yes, but you must build your own UI components. The plugin's jQuery UI is designed for WordPress admin only. Use the REST API directly from your static site generator.

### Q: Does the plugin work with Gutenberg blocks?
**A**: The plugin provides REST API endpoints and jQuery admin UI. To integrate with Gutenberg, you would need to create custom block components that call the API (not provided).

### Q: Can I customize the modal styling?
**A**: Yes. Override CSS classes in `assets/css/smma-admin.css` or enqueue your own stylesheet with higher specificity. Modal structure uses standard classes (`khm-smma-modal`, `khm-variant-card`, etc.).

### Q: Is there a REST API documentation?
**A**: See [API_ENDPOINTS.md](./API_ENDPOINTS.md) for complete endpoint documentation with request/response schemas.

### Q: Can I replace jQuery with vanilla JS?
**A**: The plugin's UI uses jQuery for WordPress compatibility. If you're building custom UI, you can use any framework/library you prefer and just consume the REST API.

---

## Summary

| Component | Status | Notes |
|-----------|--------|-------|
| WordPress Admin UI (jQuery) | ✅ Included | Full modal-based interface |
| REST API Endpoints | ✅ Included | Complete backend services |
| API Client Wrapper (jQuery) | ✅ Included | JavaScript helpers for endpoints |
| CSS Styling | ✅ Included | Complete admin theme |
| React/Vue Components | ❌ Not Included | Build custom with REST API |
| Mobile Native UI | ❌ Not Included | Use REST API + App Passwords |
| Analytics Dashboards | ❌ Not Included | External concern |
| Platform Integration UI | ❌ Not Included | Use platform-native tools |

---

**Last Updated**: 2026-02-05
**Plugin Version**: 1.0.0
**Maintained By**: KH-SMMA Core Team
