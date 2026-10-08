# Changelog

## 2.1.1 — 2026-10-08

- Expanded README into the installation and operations guide used by the Statamic Marketplace listing. It now covers first-run setup, scheduler and queue requirements, the 2.1 portal-link migration, integrations, API examples, webhook delivery, and troubleshooting. No application behavior changed.

## 2.1.0 — 2026-10-08

- **Security: webhooks no longer follow redirects.** The URL check happened once, then the request followed redirects, so a public URL that redirected to `127.0.0.1` or `169.254.169.254` got through — and the response was readable afterwards as the webhook's last error. Webhook deliveries and automation webhook steps now refuse redirects, refuse a host that doesn't resolve, and send the request to the address that was checked. Public deliveries fail closed if PHP's cURL extension is unavailable.
- **Security: client portal access needs an explicit link.** A contact's records were shown to any logged-in user with a matching email address, including an alias. Statamic doesn't verify email addresses on front-end registration, so on a site with open registration someone could sign up as a client and read their invoices, quotes, payments and shared files, including their company's. Portal access now follows the contact's new **Portal user** field only.
  - A registration still links the contact it creates. A registration that matches an existing contact fills in missing details without linking — link those yourself on the contact.
  - **If you relied on email matching, set each client's Portal user field** (CRM → the contact → sidebar) to restore their portal access.
- PDFs are rendered with Dompdf's remote file fetching turned off; the business logo is embedded in the document instead.
- Client file uploads are limited to a list of file types (`file_extensions` in `config/alp-crm.php`), so pointing `ALP_CRM_FILES_DISK` at a public disk can't serve something executable.
- CSVs from abandoned imports are deleted after a day instead of sitting in storage.
- Control Panel: user fields on a profile show the account's email address rather than its ID.
- README: Alp CRM is described as inspired by Jetpack CRM, without claiming feature parity.

## 2.0.0 — 2026-10-03

- **Renamed from Radpack CRM to Alp CRM** (`rad-themes/alp-crm`, namespace `RadThemes\AlpCrm`), because Radpack is Statamic's brand. See "Upgrading from Radpack CRM" in the README.
- URLs, settings, config and environment variables use `alp-crm` / `ALP_CRM_`; webhook headers are `X-Alp-Event` and `X-Alp-Signature`.
- Polymorphic rows (notes, activity, tags, line items) now store stable aliases such as `crm_contact` instead of PHP class names; a migration converts existing rows, including those written by Radpack CRM.

## 1.0.2 — 2026-10-03

- Security: profile links from URL fields (such as Website or LinkedIn) are only clickable for `http`/`https` addresses. Values from forms, imports or the API could otherwise put a `javascript:` link in the Control Panel.
- README: which Statamic edition you need. Core works for one person; team access with CRM permissions, and the client portal, need Statamic Pro.

## 1.0.1 — 2026-10-02

- A **CRM** link at the top of the Control Panel sidebar, under Dashboard. Statamic lists addon sections last, so the CRM section alone was easy to miss.

## 1.0.0 — 2026-10-02

First release, free and open source under the MIT license.

- **Contacts and companies:** statuses, owners, tags, aliases, custom fields through blueprints, notes and calls, activity history, files, an encrypted client password manager, filters and bulk actions
- **Sales:** quotes and invoices with line items, taxes, discounts, numbering and currencies; PDFs; emailing with editable wording; client pages to accept quotes and pay invoices; transactions and lifetime value
- **Payments:** Stripe Checkout and PayPal on invoices, signed Stripe webhooks, and Stripe/PayPal transaction imports
- **Tasks:** tasks, calls, meetings and deadlines with reminders, a calendar, and a dashboard
- **Marketing:** one-to-one and scheduled emails, templates with merge tags, segments, campaigns with open/click tracking and one-click unsubscribe, a bulk tagger, Mailchimp, Kit and AWeber sync, and Twilio text messages
- **Automations:** triggers, rules and delayed steps, with recipes and a run log
- **Reports:** revenue, new contacts, quote acceptance, days to pay, top customers, sales by source and a status funnel
- **Connections:** Statamic form and registration capture, CSV import and export, Google Contacts import, a REST API with API keys, signed webhooks and REST hooks for Zapier, Make and n8n
- **Client Portal:** billing and files pages in rad-themes/client-portal 1.1+
- **White label:** rename the CRM in the navigation
