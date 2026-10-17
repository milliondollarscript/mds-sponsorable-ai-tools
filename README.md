# Million Dollar Script - Sponsorable AI Tools

Sponsorable AI Tools lets a Million Dollar Script site owner publish small sponsor-supported tools such as calculators, planners, quizzes, estimators, generators, and guides.

## Features

- Admin-created tool templates.
- Sponsor placement, sponsor URL, sponsor label, and disclosure fields.
- Lead capture with explicit consent, nonce checks, a honeypot, configurable hourly limits, moderation, paginated privacy export, privacy erasure, and scheduled retention cleanup.
- Free email, manually reviewed, and payment-provider saved-result delivery modes.
- Independent payment and approval gates, idempotent direct `wp_mail()` delivery, visible delivery state, and administrator retry after mail failure.
- Configurable administrator notifications, exact lead totals, and paginated lead management.
- Public shortcode: `[mds_sponsorable_ai_tools]`, `[mds_sponsorable_ai_tools id="123"]`, or `[mds_sponsorable_ai_tools slug="launch-budget-estimator"]`.
- Public REST read endpoint for active tools.
- Protected lead endpoint for administrators.
- Currency uses WooCommerce or Million Dollar Script currency when available and does not hardcode a visible default.

## Saved result delivery

The browser output is an informational preview in every mode. Set a tool to **Free result email** to send a saved copy immediately. Use **Manual reviewed delivery** when the site owner verifies offline payment and approves delivery. Use **Payment provider delivery** when checkout and administrator approval must both complete before the saved result is emailed.

The extension uses the Million Dollar Script core payments API for provider checkout. It does not call WooCommerce, EDD, Stripe, or any gateway directly.

## Safety model

The built-in templates are narrow, informational, and sponsor-supported. They do not provide financial, legal, medical, tax, or regulated advice. Remote AI generation is not required for v1; Revenue Agent can be used separately to draft copy for review.

## Requirements

- Million Dollar Script 3.0+
- WordPress 6.0+
- PHP 8.1+

## REST API

- `GET /wp-json/million-dollar-script/v1/sponsorable-ai-tools/tools`
- `GET /wp-json/million-dollar-script/v1/sponsorable-ai-tools/leads` requires `manage_options`

## Changelog

### 0.2.0 - 2026-07-13

- Added explicit tool, delivery, and privacy consent with configurable terms links.
- Added administrator notifications, configurable retention and request limits, scheduled private-data cleanup, and keyed abuse controls.
- Added exact lead counts, paginated and filterable administration, paginated REST responses, and strict public REST field separation.
- Hardened field bounds, checkout failure handling, HTTP(S)-only URLs, WordPress date handling, semantic notices, responsive layouts, and regression coverage beyond 100 leads.

### 0.1.0 - 2026-06-02

- Initial MDS 3.0 implementation.
- Added complete saved-result delivery, payment and approval reconciliation, mail retry, privacy handling, and current API discovery metadata.
