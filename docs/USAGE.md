# Sponsorable AI Tools

Sponsorable AI Tools lets a site owner publish sponsor-supported calculators, planners, quizzes, estimators, generators, and guides with lead capture and moderation.

## Practical Examples

- Publish a sponsor-backed launch-budget estimator that gives an immediate browser result and offers an emailed copy after consent.
- Create a reviewed project-planning worksheet where a staff member approves each saved result before delivery.
- Sell access to a saved calculator or planning result through the active Million Dollar Script payment provider without making the tool depend directly on a gateway.

### Example workflow: a sponsored budget estimator

1. Open **Million Dollar Script -> Sponsorable AI Tools**, create a budget estimator, and add its sponsor label, disclosure, terms, and retention period.
2. Choose **Free result email** and add the **Sponsorable Tool** block to a page.
3. A visitor runs the browser tool, accepts the terms and privacy consent, and requests the saved result by email.
4. Review the request and delivery state under **Leads**. If WordPress mail fails, use **Retry delivery** after correcting the site's mail transport.

## Setup

1. Activate Million Dollar Script and Sponsorable AI Tools.
2. Open **Million Dollar Script -> Sponsorable AI Tools**.
3. Review the seeded tool or create a new tool.
4. Configure the tool title, slug, description, sponsor label, sponsor URL, disclosure text, pricing, result delivery mode, contact email, terms URL, retention period, and hourly request limit.
5. Choose free saved-result email, manually reviewed delivery, or payment-provider delivery.
6. Add the tool to a page with the block or shortcode.

## Admin Workspace

Existing tools open on **Leads** so saved-result requests and delivery status are visible first. Use **Tool settings** to create or configure a browser tool. The selected tool and tab remain active across actions and paginated views.

## Public Display

Use the **Sponsorable Tool** block from the Million Dollar Script block category, or add one of these shortcodes:

```text
[mds_sponsorable_ai_tools]
[mds_sponsorable_ai_tools id="123"]
[mds_sponsorable_ai_tools slug="launch-budget-estimator"]
```

The block can select an existing tool by ID. The slug field is optional and is only used when no tool ID is selected.

## Lead Capture

Visitor submissions use nonce checks, a honeypot field, keyed hourly rate limiting, explicit terms and privacy consent, and moderation. Visitors must run the browser tool and accept the configured terms before requesting a saved result. Administrators can search, filter, and page through requests while reviewing payment state, delivery state, provider order, saved result, consent time, and delivery errors.

The configured contact email receives a direct WordPress email when a request is submitted. Reviewed and provider-based requesters also receive a confirmation while the saved result remains gated by approval and payment.

The extension includes paginated WordPress privacy export and erasure hooks for result-request data. A daily cleanup anonymizes private contact details, messages, generated results, checkout links, and abuse data after each tool's configured retention period. Non-identifying consent time, payment, provider-order, moderation, and delivery records remain available for legitimate accounting and dispute needs.

## Result Delivery

The browser result is an informational preview in every mode. Delivery modes control the saved result email and reviewed follow-up:

- **Free result email**: a valid request sends the saved result immediately.
- **Manual reviewed delivery**: an administrator verifies any offline payment, approves the request, and triggers email delivery.
- **Payment provider delivery**: checkout starts through the active Million Dollar Script payment provider. Successful payment and administrator approval are independent requirements and can happen in either order. The result is sent only after both are complete.

This extension does not call WooCommerce, Stripe, or any gateway directly.

Delivery is idempotent, so repeated payment callbacks do not send duplicate email. A failed WordPress mail attempt is shown in the request record with a **Retry delivery** action. Failed, cancelled, expired, denied, or refunded payments prevent an undelivered provider request from being sent. A later refund cannot retract an email that was already delivered, but its payment and delivery history remains visible to the administrator.

## AI And Safety

The built-in templates are narrow, informational, and sponsor-supported. They do not provide legal, medical, tax, financial, or regulated advice. Remote AI generation is not required. Revenue Agent can be used separately to draft copy for administrator review.

## Legal And Privacy

Sponsorable AI Tools can contribute a **Sponsorable AI Tools Terms and Lead Privacy Notice** page through the Million Dollar Script extension setup flow. Review it before publishing.

Site owners are responsible for sponsor disclosure, lead consent, tool claims, pricing language, privacy requests, and any regulated subject matter restrictions.

Use **Tools -> Export Personal Data** to export matching contact details, notes, saved results, moderation, payment, delivery, amount, currency, and timestamps. **Tools -> Erase Personal Data** removes contact details, messages, saved results, checkout links, and IP-derived abuse data while retaining non-identifying payment, provider-order, moderation, and delivery records for accounting, fraud prevention, and dispute handling.

## REST API

```text
GET /wp-json/million-dollar-script/v1/sponsorable-ai-tools/tools
GET /wp-json/million-dollar-script/v1/sponsorable-ai-tools/leads
```

Both endpoints accept `page` and `per_page` and return pagination metadata. The leads endpoint additionally accepts `tool_id`, `status`, and `search` filters.

The tools endpoint is public and contains only active public tool configuration. It never includes the contact email, retention settings, request limits, private lead count, or lead data. The leads endpoint contains private contact and delivery data and requires administrator capability; it is not an LLM-safe read action.
