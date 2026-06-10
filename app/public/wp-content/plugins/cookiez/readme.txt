=== Cookie Consent - GDPR & CCPA Cookie Banner & Consent Manager ===
Contributors: elemntor
Tags: cookie consent, compliance, GDPR, CCPA
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 0.0.6
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simplify cookie consent with a customizable banner that helps you cover global privacy laws like GDPR and CCPA. Scan your site for cookies, block scripts based on visitor preferences, and keep audit-ready logs of every choice.

== Description ==

https://www.youtube.com/watch?v=-rPbbBeadu8

= 🍪 What Cookie Consent Does =

= Scans your site for cookies. =
Cookie Consent detects cookies by loading your pages in a full headless browser (Chromium), the same way a real visitor experiences your site. This catches cookies set by JavaScript execution, third-party embeds, analytics snippets, and dynamically loaded content that simpler HTTP-based scanners often miss. Detected cookies are automatically categorized into Necessary, Analytics, Marketing, Functional, or Uncategorized. You can scan your homepage, crawl your full site, or specify custom URLs. Results merge into your cookie manager without overwriting any manual edits.

= Blocks scripts until consent is given. =
In GDPR (opt-in) mode, non-essential scripts don't execute until the visitor consents to the relevant category. This applies to analytics scripts, advertising pixels, tracking tags, and any other third-party script that sets non-essential cookies. Cookie Consent intercepts both WordPress-enqueued scripts and inline scripts, modifying them to wait for consent before firing. Essential cookies, like those used for login sessions, shopping carts, and basic site functionality, are never blocked. In CCPA (opt-out) mode, scripts load by default and visitors can choose to opt out of specific categories.

= Displays a customizable consent banner. =
Visitors see a cookie banner with clear options to accept, reject, or manage preferences by category. The banner, preferences dialog, and revisit button are all customizable including layout, position, colors, text, button styles, and border radius. A live preview shows you what visitors will see as you make changes.

= Logs every consent decision. =
Each time a visitor makes a choice, Cookie Consent records the decision type (accept all, reject all, or custom), the specific category preferences, and a timestamp. These records create an audit trail you can reference if compliance questions arise.

= Keeps everything in one place. =
Cookie list, scan results, script rules, consent logs, banner design, and compliance settings, all in one admin area. No separate platform to sign up for, no external app to connect, no additional accounts to manage.

= ⚙️ Setup =
A guided wizard gets your banner live in three steps:
1. **Choose your compliance model** – Select GDPR (opt-in) or CCPA (opt-out) based on your audience and the regulations that apply to your site.
2. **Configure your banner** – Pick a layout, adjust colors and text, and preview what visitors will see.
3. **Scan your site** – Cookie Consent visits your pages with a real browser, finds active cookies, and categorizes them. Your banner goes live with accurate cookie data from the start.

After setup, you can re-scan anytime, refine your design, recategorize cookies, and adjust script rules, all from the same dashboard.

= 🎨 Banner Design & Customization =
**Banner Templates**: Start with a pre-configured template during setup, then customize to fit your site. Templates are tailored to your selected compliance model (GDPR or CCPA) so the default structure, buttons, and consent flow match your requirements from the start.
**Layouts**: Footer bar, slide-in, or center popup.
**Positioning**: Control where and how the banner appears on the page.
**Styling**: Background colors, text colors, button primary and secondary colors, border radius, and button sizing, enough control to match any brand or design.
**Content**: Editable title, description, and button labels for the banner, preferences dialog, and revisit button. Write in your own voice.
**Preferences Dialog**: When a visitor clicks Manage Preferences, they see a breakdown of each cookie category with descriptions and toggles. Necessary cookies are marked as always active. Visitors choose exactly which categories they\'re comfortable with.
**Revisit Consent Button**: A persistent button visitors can click anytime to reopen the preferences dialog and change their choices.
**Responsive**: Automatically adapts to desktop and mobile.
**Live Preview**: Desktop and mobile preview with a device toggle. Updates in real time as you edit — no publishing and refreshing the front end.
**Elementor Editor Integration [Requires Elementor Pro]**: Open the banner and preferences dialog in the Elementor visual editor. Drag-and-drop widgets, styling panels, and your full design system, the same workflow you use for the rest of your site.
**Cloud Templates [Requires Elementor Pro]**: Save a finished banner design to your cloud library and deploy it across other sites in one click. Useful for agencies and freelancers managing multiple client sites with consistent branding.

= 📋 Cookie & Script Management =
**Cookie Manager**: A central dashboard showing every cookie on your site, organized by consent category. Each entry shows the cookie name, domain, category, duration, and a description field. Add cookies manually, edit any field, or delete entries you no longer need. Drag-and-drop cookies to reorder them within a category, the order you set here is the order visitors see in the preferences dialog.

**Category View**: Categories display as expandable accordion sections: Necessary, Analytics, Marketing, Functional, and Uncategorized. Each shows a status badge: \"Always Active\" for Necessary, or an Active/Inactive toggle for other categories. Expand any section to see the individual cookies it contains.

**Scanning**: Run a scan anytime with homepage only, full-site crawl, or custom URLs you specify. Include and exclude lists let you control exactly which pages get scanned. Only one scan runs at a time. New cookies are added automatically; existing manual edits are never overwritten. A progress indicator shows scan status in real time.

**Script Manager**: Control how individual scripts behave, independent of their category\'s default rule. Three options per script:
- *Do Not Block* – Script always loads, bypassing the consent engine. Use for scripts you\'ve verified as essential but that aren\'t automatically detected as Necessary.
- *Block Until Consent* – Script waits until the visitor consents to its assigned category. This is the default for non-essential scripts in GDPR mode.
- *Always Block* – Script never loads under any circumstances. Use for scripts you want permanently disabled.

**Script Input**: Add scripts by URL or paste inline code directly. Cookie Consent matches scripts using URL comparison or normalized snippet hashing.

**Inline Script Blocking**: An optional output buffering method catches inline and hardcoded scripts that aren\'t loaded through the standard enqueue system. Toggle it off from Advanced Settings if you want to manage inline scripts manually or minimize server-side processing.

**Developer Hooks**: Advanced users and agencies can hook into the blocking engine via an MU-plugin snippet to delay dynamically injected scripts, including those created by page builders or loaded after DOM ready.

**Scan History**: A table of past scans showing date (UTC), status (success/failed), URLs scanned, categories found, and total cookies detected.

= 🔒 Compliance Settings=
**GDPR (Opt-in)**: Non-essential scripts are blocked by default. The banner presents three options: Accept All, Reject All, and Manage Preferences. Visitors who open the preferences dialog see each cookie category with a description and a toggle. Necessary cookies are labeled \"Always Active\" with no toggle, they can\'t be disabled. Consent must be given before Analytics, Marketing, or Functional scripts execute.

**CCPA (Opt-out)**: All scripts load by default. The banner includes a Do Not Sell My Data option. Visitors who opt out trigger blocking of Marketing category scripts. Global Privacy Control (GPC) signals sent by the visitor\'s browser are detected and respected automatically.

**Consent Expiration**: Set how many days a visitor\'s choice is remembered (1–365 days, default 180). When the period expires, the banner reappears and the visitor is prompted to choose again. Until expiration, the previous choice is respected and the banner stays hidden.

**Banner Activation**: A global toggle to enable or disable the cookie banner.

= 🎁 Free Features =
Everything you need for working cookie consent.
- Guided 3-step setup wizard
- GDPR (opt-in) and CCPA (opt-out) compliance models
- Cookie scanning with headless browser (Chromium) detection
- Automatic categorization into Necessary, Analytics, Marketing, Functional, and Uncategorized
- Automatic script blocking for non-essential cookies
- Blocks both enqueued scripts and inline scripts
- Global Privacy Control (GPC) and Do Not Sell support
- Customizable consent banner with Accept All, Reject All, and Manage Preferences
- Preferences dialog with per-category toggles and descriptions
- Revisit consent button for visitors to update choices anytime
- Banner templates tailored to GDPR and CCPA compliance models
- Footer bar, slide-in, and center popup layouts
- Full styling controls: colors, buttons, borders, sizing
- Editable banner text, button labels, and dialog content
- Responsive desktop and mobile design
- Live banner preview with device toggle
- Cookie manager with manual add/edit/delete and sorting
- Script manager with per-script overrides (always load, block until consent, always block)
- Consent logging with timestamped audit trail
- Configurable consent expiration (1–365 days)
- Scan history with status and results tracking
- Output buffering toggle for inline script blocking
- No external dashboards
- No CDN dependencies

= 🔥 Premium Cookie Consent features available with Elementor One =
- **White-Label**: Remove Cookie Consent branding from the consent banner entirely.
- **Multilingual Translations**: Translate the banner and preferences dialog into additional languages for international visitors.
- **Consent Log Export**: Download consent records in CSV format for audits, legal reviews, or internal documentation.
- **Advanced Scan Rule Exclusions**: Exclude specific paths or URLs from cookie scans. Useful for staging environments, admin areas, or sections that don\'t need scanning.
- **Disable Banner on Specific Pages**: Prevent the banner from loading on pages you select (e.g., internal dashboards, login screens).
- **Elementor Editor Design [Requires Elementor Pro]**: Open the cookie banner and preferences dialog in the full Elementor visual editor. Drag-and-drop layout, widget-level control, and live editing.
- **Cloud Templates [Requires Elementor Pro]**: Save banner designs to your cloud library and deploy them across multiple sites instantly.

= 🔜 Coming Soon =
- **Google Consent Mode v2** – Automatically send consent signals to Google so analytics and ad measurement continue working after consent is deployed.
- **Cookie Policy Generator** – Auto-generate a cookie policy page based on the cookies detected on your site.
- **Google Tag Manager Integration [Premium]** – Pass consent signals to your GTM container for tag-level consent control.
- **Automatic and Scheduled Scans [Premium]** – Set scans to run on a recurring schedule so your cookie list stays current without manual intervention.
- **Geo-Targeting [Premium]** – Automatically display the correct banner based on visitor location. GDPR opt-in for EU visitors, CCPA opt-out for US visitors without manual configuration.
- **Multi-Region Banner Management [Premium]** – Configure and manage separate banner designs and compliance rules for different geographic regions from one dashboard.

♿ Accessibility
The Cookie Consent consent banner is built following accessibility best practices. For full website accessibility compliance, Cookie Consent works alongside [Ally](https://go.elementor.com/wp-repo-cookiez-ally/), Elementor\'s accessibility plugin that scans for and remediates accessibility issues across your site.

= Installation =

1. Install using the WordPress built-in Plugin installer, or Extract the zip file and drop the contents in the `wp-content/plugins/` directory of your WordPress installation.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= What is Cookie Consent? =

Cookie Consent is a cookie consent plugin that adds a GDPR and CCPA compliant consent banner to your site. It includes cookie scanning, script blocking, a customizable banner with live preview, and consent logging, all managed from your admin dashboard.

= Do I need Elementor to use Cookie Consent? =

No. Cookie Consent works with any theme and any page builder. All core features — scanning, blocking, consent management, banner customization, and consent logging — are fully available without Elementor. If you have Elementor Pro installed, you can additionally design your banner in the Elementor visual editor and save designs as cloud templates.

= Does Cookie Consent make my site fully GDPR or CCPA compliant? =

Cookie Consent provides tools for cookie consent implementation including banner display, consent collection, script blocking, cookie categorization, and consent records. Full legal compliance depends on many additional factors specific to your site and business. You are responsible for ensuring your site meets all applicable legal requirements.

= How does the cookie scanner work? =

Cookie Consent sends your site URL to a scanning service that loads your pages in a headless browser (Chromium). This detects cookies actually set during a page visit, including those from JavaScript and third-party scripts. Results are returned to your dashboard, categorized, and merged into your cookie manager. Manual edits are never overwritten.

= How does script blocking work? =

For scripts loaded through WordPress's enqueue system, Cookie Consent modifies the script tag to prevent execution until consent is given. For inline or hardcoded scripts, an optional output buffering method intercepts them before they reach the browser. You can override the default behavior for any script in the Script Manager.

= What cookie categories does Cookie Consent use? =

Five categories: Necessary (always active, never blocked), Analytics, Marketing, Functional, and Uncategorized. Every cookie is assigned to one category. These categories are shared across the cookie manager, blocking logic, and the consent banner.

= Can I customize the cookie banner? =

Yes. You can change the layout, position, colors, text, button labels, toggle styles, and border radius. A live preview shows your changes in real time for both desktop and mobile. With Elementor Pro, you can also design the banner using the full Elementor visual editor.

= What happens when a visitor rejects cookies? =

In GDPR mode, scripts in rejected categories stay blocked. Only Necessary scripts run. In CCPA mode, opting out blocks Marketing category scripts. The visitor's choice is stored in a local browser cookie and the banner stays hidden until the consent period expires.

= Can I switch between GDPR and CCPA mode after setup? =

Yes. Switch compliance models anytime from Settings. Banner behavior, consent logic, and default button options update to match the new model. Review your banner content after switching to make sure the messaging fits.

= How do I report a security bug? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. Report a security vulnerability.

== External Services ==

Cookie Consent requires a connection to an active Elementor account in order to:
* Identify the user and provide the user with the purchased service.
* Log website visitors consents for "proof of consent"
* Preform an external scan of cookies generated by and on the website.
* Collect manually submitted feedback, to improve the product.

This connection is initiated manually by the user via the plugin’s settings panel.
Learn more about our [terms and conditions](https://elementor.com/terms/)and [Privacy Policy](https://elementor.com/about/privacy/). This plugin uses a 3rd party service operated by Elementor.

Cookie Consent uses a 3rd party service operated by Mixpanel to collect interactions with the plugin but only for consenting users, meaning if the user has *Not* consented to "sharing data" (default) this is disabled and no collection is made.

== Screenshots ==
1. **Cookie consent dashboard** - See your cookie consent status at a glance. Track total cookies, consent trends, and visitor responses all from one dashboard.
2. **Design** - Customize your banner layout, type, and position directly in the plugin, or design a fully branded cookie banner using the Elementor Editor with Popups.
3. **Cookie Management** - Cookies detected on your site are automatically organized into categories (Necessary, Functional, Analytics, Advertising). You can also manually add, edit, or recategorize cookies.
4. **Content** - Edit your banner's title, cookie message, and button labels. Recommended wording is included by default, with multi-language support available with Elementor One.
5. **Script Manager ** - Manage how third-party scripts load on your site. Set each script to Block until consent, Always block, or Never block, with automatic categorization by type.
6. **Settings** - Select the consent model that matches your audience. Opt-in (recommended for GDPR) blocks cookies until visitors accept, while Opt-out (recommended for CCPA) enables them by default.
7. **Consent Logs** - Track every visitor's consent response with timestamp, country, status, and accepted categories and export records as a CSV file.
8. **Design with Elementor** - Use Elementor Popups to build a fully custom cookie banner. Adjust popup styling, borders, overlays, and close button behavior with familiar controls.
9. Style your banner with Global Fonts to keep typography consistent with the rest of your site, ensuring your cookie banner feels like part of your design system.
10. Apply Global Colors to banner buttons so your accept, deny, and customize options match your brand palette automatically.
11. Use Elementor's Advanced controls to fine-tune margin, padding, width, alignment, and positioning for pixel-perfect banner design.


== Changelog ==

= 0.0.6 - 2025-06-09 =
* Tweak: Added a grow animation effect for cookie reopen icon.
* Tweak: Aligned cookie reopen icon with Ally icon.
* Tweak: Improved Elementor preference color controls.
* Fix: Added the missing "Always Active" state for Elementor-based designs.
* Fix: Compatibility issue with Cache plugins.
* Fix: Reopen icon appeared too small and had inconsistent border radius styling.
* Fix: Banners could lose design settings in edge cases.

= 0.0.5 - 2026-06-01 =
* Tweak: Simplified translation strings
* Fix: Redirection logic for Elementor Cookiez templates
* Fix: Remove "Cookie Consent by Elementor" not working.

= 0.0.4 =
* Tewak: Updated Plugin main file title and description.
* Tweak: Avoid focus trap in admin preview.
* Fix: Fatal Error with ElementorOne is missing.

= 0.0.3 =
* Fix: restored missing files.

= 0.0.2 =
* Tweak: Added Autoconnect to Elementor One users.
* Tweak: Updated tested up to WordPress 7.0.

= 0.0.1 =
* Initial release.
