# Quote Club New User SOP

## Purpose
This SOP explains exactly where a brand-new member should go immediately after login to access the Quote Club dashboard and complete first actions.

## Audience
- New member users
- Support team walking users through first login

## Prerequisites
- User can sign in at the site login page
- User has an active member account

## Primary Entry Point
Use this URL after login:

http://touchpoint.local/member-dashboard-preview/?tab=quoteclub

This is the current validated Quote Club dashboard surface.

## Admin Setup Path (For Site Operators)
Use this when you are setting up the experience in wp-admin, not acting as an end user.

### Start Here
1. Log in to wp-admin.
2. Open Membership admin:
   - http://touchpoint.local/wp-admin/admin.php?page=khm-membership
3. Confirm the Members Portal page content uses:
   - [khm_member_portal]

### Quote Club Admin Screens
1. Sponsor Commentary queue:
   - http://touchpoint.local/wp-admin/admin.php?page=khm-qc-commentary
2. Press Releases queue:
   - http://touchpoint.local/wp-admin/admin.php?page=khm-qc-press-releases
3. Quote Club credit bundles:
   - http://touchpoint.local/wp-admin/admin.php?page=khm-qc-bundles

### Page Builder / Layout Working Surface
Use this frontend page for layout and UX iteration of the new dashboard surface:

http://touchpoint.local/member-dashboard-preview/?tab=quoteclub

If legacy layout appears on members-portal, continue using the preview page above while redesign work is in progress.

## First-Time User Path (Step-by-Step)
1. Log in to the site.
2. Open the Quote Club dashboard URL above.
3. Confirm you see the heading "Quote Club Dashboard".
4. In the quick actions row, use one of these:
   - Article Search: jump to workspace search section.
   - New Press Release: open press release submission area.
   - Buy Credits: open credits/purchase area.
5. Check the KPI cards:
   - Editorial Credits
   - Press Release Credits
   - Drafts In Progress
   - Awaiting Review
   - Live To Date
6. In Recent Activity:
   - Start with default 10 rows.
   - Change Rows per page to 20, 50, or 100 as needed.
   - Use pagination controls when available.

## Empty-State Expectations
If this is a brand-new account with no activity, users should see:

"No activity yet. Get started by submitting your first quote!"

## Common User Actions
### Submit first press release
1. Click New Press Release.
2. Complete the form.
3. Submit.
4. Return to Quote Club dashboard and verify activity appears.

### Find content opportunities
1. Click Article Search.
2. Set filters (date, topics, portfolio, keywords, operator).
3. Save search if useful.

### Add credits
1. Click Buy Credits.
2. Complete purchase flow.
3. Return to dashboard and verify updated credit totals.

## Troubleshooting
### User sees old portal layout instead of Quote Club dashboard
- Send user directly to:
  - http://touchpoint.local/member-dashboard-preview/?tab=quoteclub

### User sees no activity table rows
- Expected for new users.
- Confirm empty-state message appears.

### Status wording reference
- pending_editorial -> Awaiting Review
- submitted -> Submitted
- approved -> Scheduled
- published -> Live
- rejected -> Needs Revision

## Support Handoff Checklist
- Confirm user can open Quote Club dashboard URL.
- Confirm user can see at least one quick action.
- Confirm user can change Rows per page.
- Confirm user can navigate to New Press Release.
- Capture screenshot if any step fails.

## Revision Notes
- v0.1: Initial onboarding SOP draft created during dashboard validation and rollout.
