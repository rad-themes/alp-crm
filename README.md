# Radpack CRM for Statamic

![The Radpack CRM dashboard](docs/screenshots/dashboard.png)

A complete, free CRM inside your Statamic Control Panel: contacts and companies, quotes and invoices with online payments, tasks and a calendar, email campaigns, segments, automations, reports, a REST API and webhooks. It's built with Statamic's own UI components, so it looks and feels like the rest of the Control Panel.

Radpack CRM brings every feature of [Jetpack CRM](https://jetpackcrm.com) to Statamic, including all of its paid extensions, free and open source under the MIT license.

## Screenshots

| | |
|---|---|
| ![Contacts](docs/screenshots/contacts.png) | ![A contact's profile](docs/screenshots/contact.png) |
| ![Quote editor](docs/screenshots/quote-editor.png) | ![An invoice](docs/screenshots/invoice.png) |
| ![The client's invoice page with payment buttons](docs/screenshots/invoice-public.png) | ![Tasks](docs/screenshots/tasks.png) |
| ![Calendar](docs/screenshots/calendar.png) | ![Segment rules](docs/screenshots/segment.png) |
| ![Campaign report](docs/screenshots/campaign.png) | ![Automation editor](docs/screenshots/automation.png) |
| ![Reports](docs/screenshots/reports.png) | ![CSV import](docs/screenshots/import.png) |
| ![Integrations](docs/screenshots/integrations.png) | ![Client portal billing](docs/screenshots/portal-billing.png) |

## Features

**Contacts and companies**
- Contacts and companies (B2B), with statuses, owners, tags, aliases (other email addresses for the same person) and Gravatar avatars
- Custom fields: edit the contact, company, task and transaction blueprints like any other Statamic blueprint
- Notes, calls, meetings, emails and text messages logged on a timeline, plus a full activity history
- Files on contacts and companies, optionally shared with the client
- A client password manager, encrypted at rest; every reveal is logged
- Filters, bulk actions (tag, change status, delete) and a bulk tagger for whole segments

**Sales**
- Quotes and invoices with a line-item editor, tax rates, discounts, numbering and multiple currencies
- PDF quotes and invoices, emailed with the PDF attached
- Client pages to view and download documents, accept or decline quotes, and pay invoices
- Online payments with **Stripe** and **PayPal**; payments mark invoices paid automatically
- Transactions (sales and refunds), lifetime value per contact, and imports from Stripe and PayPal

**Work**
- Tasks, calls, meetings and deadlines, assigned to team members, with email reminders
- A month calendar of tasks and invoice due dates
- A dashboard with your week's tasks, key numbers and latest activity

**Marketing**
- Email contacts from their profile, now or scheduled, with reusable templates and merge tags
- Segments: dynamic groups by status, tags, dates, lifetime value or any custom field
- Campaigns to a segment, sent in batches, with open and click tracking and one-click unsubscribe
- Sync with **Mailchimp**, **Kit** (ConvertKit) and **AWeber**
- Text messages with **Twilio**

**Automation and reporting**
- Automations: "when a contact is tagged VIP, if they're a customer, wait a day, then email them and create a task"
- Reports: revenue and new contacts by month, quote acceptance, days to pay, top customers, sales by source, and a status funnel

**Connections**
- Statamic forms and site registrations create contacts automatically
- CSV import with column mapping, and CSV export
- **Google Contacts** import
- A REST API with API keys, signed webhooks and REST hooks for Zapier, Make and n8n
- Billing and files pages in [Client Portal](https://github.com/rad-themes/client-portal), our free client portal addon
- White label: rename "CRM" in the navigation

## Requirements

- Statamic 6, PHP 8.3+. **Statamic Core** is enough for one person: the site owner gets the whole CRM. To give team members access with CRM permissions you need **Statamic Pro**, because Core allows only one user and has no roles. The client portal pages also need Pro, through [Client Portal](https://github.com/rad-themes/client-portal).
- A database (SQLite, MySQL, MariaDB or PostgreSQL), as for any Laravel app
- A mail driver, for sending documents, emails and reminders
- The Laravel scheduler (`* * * * * php artisan schedule:run`), for reminders, scheduled emails, campaigns, automations and syncing

## Installation

```bash
composer require rad-themes/radpack-crm
php artisan migrate
```

Open the CRM from **CRM** at the top of the Control Panel sidebar, under Dashboard. All its pages are also in the **CRM** section at the bottom of the sidebar; drag sections into the order you like in **Preferences → CP Navigation**. Super users can use everything straight away; other users need the permissions below.

### Permissions

Under **Users → Permissions**, each role can get:

| Permission | Allows |
|---|---|
| View CRM | Seeing contacts, companies, sales, tasks, campaigns and reports |
| Create and edit CRM records | Creating and changing them, sending emails and documents |
| Delete CRM records | Deleting them |
| Manage client passwords | Seeing and editing saved client passwords |

Settings, integrations, API keys and webhooks need **Configure addons**.

## Settings

**CRM → Settings** (or **Tools → Addons → Radpack CRM → Settings**):

- **Business:** name, logo, address and tax number shown on quotes, invoices and emails
- **Sales:** default currency, numbering, payment terms, quote validity, tax rates, and default terms
- **Reports:** which statuses form your funnel, in order
- **Payments:** Stripe and PayPal
- **Integrations:** Mailchimp, Kit, AWeber, Twilio and Google Contacts
- **Lead capture:** which forms create contacts, and whether registrations do
- **Email:** campaign sending speed, and the wording of invoice and quote emails
- **White label:** what to call the CRM in the navigation

### Keeping secrets out of git

Settings are saved in `resources/addons/radpack-crm.yaml`, which is usually in version control. API secrets can come from your `.env` instead, and those values take precedence:

```dotenv
RADPACK_CRM_STRIPE_SECRET_KEY=
RADPACK_CRM_STRIPE_WEBHOOK_SECRET=
RADPACK_CRM_PAYPAL_CLIENT_ID=
RADPACK_CRM_PAYPAL_SECRET=
RADPACK_CRM_MAILCHIMP_API_KEY=
RADPACK_CRM_KIT_API_KEY=
RADPACK_CRM_AWEBER_CLIENT_ID=
RADPACK_CRM_AWEBER_CLIENT_SECRET=
RADPACK_CRM_TWILIO_SID=
RADPACK_CRM_TWILIO_TOKEN=
RADPACK_CRM_GOOGLE_CLIENT_ID=
RADPACK_CRM_GOOGLE_CLIENT_SECRET=
```

Publish the config with `php artisan vendor:publish --tag=radpack-crm-config` to change it. It also sets the disk for client files (`RADPACK_CRM_FILES_DISK`, default `local`, which is private).

OAuth tokens (AWeber, Google) and sync positions are stored encrypted in `storage/app/radpack-crm`.

## Using the CRM

### Custom fields

Contacts, companies, tasks and transactions use blueprints. Edit them in **Fields → Blueprints**, under *Radpack-crm*, to add fields such as "Industry" or "Birthday". Custom fields show on profiles, in segments and automations (as *Custom field*), as merge tags (`{{ industry }}`), and in imports, exports and the API.

### Lead capture

In **Settings → Lead capture**, pick the Statamic forms that should create contacts. Submissions are matched to contacts by email: new people become leads, and existing contacts get any missing details filled in, without overwriting what you have. Fields are mapped by handle: `email`, `name`, `first_name`, `last_name`, `phone`, `company`, and any contact field handle such as `city`. Other fields are saved as a note. The form's title is added as a tag.

Turn on **Add users who register to the CRM** to do the same for site registrations; the contact is linked to the user.

### Import and export

**Contacts → Import** takes a CSV (comma, semicolon or tab separated). Match each column to a field, choose whether to update existing contacts (by email) or companies (by name), and tag everyone imported. Columns named *Company* create or link companies; *Tags* columns can hold several tags separated by commas.

Export contacts or companies from the **…** menu on their list, or a segment from the segments list. Values that spreadsheet apps would run as formulas are escaped.

### Quotes, invoices and payments

Create a quote, send it, and the client can accept or decline it on its page. Convert an accepted quote to an invoice in one click. Invoices track payments and their balance, and become *Overdue* after their due date.

To take payments online:

- **Stripe:** add your secret key. In Stripe, add a webhook to the URL shown on **CRM → Settings → Integrations** for the events `checkout.session.completed`, `charge.succeeded` and `charge.refunded`, and paste its signing secret. Payments are also confirmed when the client comes back from Stripe, so they're recorded even before the webhook is set up.
- **PayPal:** add the client ID and secret of a REST app (sandbox or live).

Turn on the imports to add your other Stripe and PayPal payments as transactions every hour, creating contacts for new customers.

### Email, segments and campaigns

Write to a contact from the **Emails** tab of their profile, now or later. **Email templates** are reusable messages with merge tags: `{{ first_name }}`, `{{ last_name }}`, `{{ name }}`, `{{ email }}`, `{{ company }}`, `{{ business_name }}` and any contact field. Templates can only use variables, not Antlers tags.

**Segments** group contacts by rules and update automatically. Use them to filter the contacts list, export, bulk tag, or send a **campaign**. Campaigns:

- go to subscribed contacts with an email address, once per address
- send in batches every minute (set the batch size to suit your mail provider)
- track opens and clicks; links are signed so they can't be used to redirect elsewhere
- include an unsubscribe link and a one-click `List-Unsubscribe` header. Opening the link shows a confirmation, so link scanners can't unsubscribe people.

Send campaigns through a transactional email provider (Postmark, Amazon SES, Mailgun, Resend…) with SPF, DKIM and DMARC set up for your domain.

### Automations

An automation has a **trigger** (contact created, tagged or status changed, form submitted, quote accepted, invoice paid, task completed, and more), optional **rules** the contact must match, and **steps**. Each step can wait minutes, hours or days first:

- add or remove tags, change status
- send an email template or a text message
- create a task, add a note
- email your team, or post to a webhook URL

Start from a recipe such as *Welcome new leads* or *Accepted quote → customer*. Each automation shows its recent runs, and steps are logged on the contact. Automations that trigger each other stop after three levels, so they can't loop.

### Client portal

Install [Client Portal](https://github.com/rad-themes/client-portal) (`composer require rad-themes/client-portal`, version 1.1 or later). Clients who log in and have a CRM contact with the same email, or are linked by registration, get:

- **Billing:** their invoices (with *View & pay*), quotes (with *Review*) and payments, including their company's
- **Files:** files you've shared with them from their profile

### Integrations

**CRM → Settings → Integrations** shows each connection's status, with *Connect* buttons for AWeber and Google and *Sync now* for imports.

- **Mailchimp, Kit, AWeber:** contacts sync as they change, with their tags. Unsubscribing in the CRM unsubscribes them in the list. Optionally only sync contacts with certain tags.
- **Twilio:** text contacts from their profile and from automations. Messages are logged as notes.
- **Google Contacts:** import contacts with an email address, once or every hour.

## REST API

Create an API key in **CRM → Settings → API & webhooks**. Keys can be read-only. Send the key as a bearer token:

```bash
curl https://example.com/api/radpack-crm/v1/contacts?tag=vip \
  -H "Authorization: Bearer rpk_…"
```

| Endpoint | |
|---|---|
| `GET /me` | Check the key works |
| `GET /events` | Event names for webhooks |
| `GET POST /contacts`, `GET PATCH DELETE /contacts/{id}` | Filter with `search`, `email`, `status`, `tag`, `company_id`, `updated_since` |
| `POST /contacts/upsert` | Create or update by `email` |
| `POST DELETE /contacts/{id}/tags` | `{"tags": ["VIP"]}` |
| `POST /contacts/{id}/notes` | `{"body": "…", "type": "call"}` |
| `GET POST /companies`, `GET PATCH DELETE /companies/{id}` | |
| `GET POST /tasks`, `GET PATCH DELETE /tasks/{id}` | |
| `GET POST /transactions`, `GET PATCH DELETE /transactions/{id}` | |
| `GET /quotes`, `GET /invoices`, `GET /quotes/{id}`, `GET /invoices/{id}` | Read only |
| `POST /hooks`, `DELETE /hooks/{id}` | REST hook subscriptions (Zapier, Make, n8n) |

Writes are validated with the same blueprints as the Control Panel, so required fields and custom fields work the same way. Send relations as `company_id`, `contact_id`, `owner_id` and `assigned_to`, and custom fields at the top level or inside `fields`. Lists are paginated (`per_page` up to 100) and limited to 120 requests a minute per IP.

## Webhooks

Add webhooks in **CRM → Settings → API & webhooks**, or subscribe through the API. Each event is POSTed as JSON:

```json
{
  "id": "1f0c…",
  "event": "invoice.paid",
  "created_at": "2026-10-02T10:00:00+00:00",
  "data": { "id": 12, "object": "invoice", "number": "INV-0012", "total": 1200, "…": "…" },
  "context": {}
}
```

Verify the `X-Radpack-Signature` header, an HMAC-SHA256 of the raw body using the webhook's signing secret:

```php
$expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
abort_unless(hash_equals($expected, $request->header('X-Radpack-Signature')), 401);
```

Events: `contact.created`, `contact.updated`, `contact.status_changed`, `contact.tagged`, `contact.unsubscribed`, `contact.deleted`, `company.created`, `company.updated`, `company.deleted`, `form.submitted`, `quote.created`, `quote.sent`, `quote.accepted`, `quote.declined`, `invoice.created`, `invoice.sent`, `invoice.paid`, `transaction.created`, `task.created`, `task.completed`.

## For developers

Every event above is also a Laravel event:

```php
use RadThemes\RadpackCrm\Events\CrmEvent;

Event::listen(function (CrmEvent $event) {
    if ($event->name === 'invoice.paid') {
        // $event->payload, $event->contact, $event->context
    }
});
```

Add your own automation step:

```php
use RadThemes\RadpackCrm\Automations\Actions;

Actions::extend('slack', 'Post to Slack', function (array $step, ?Contact $contact, array $context) {
    // …
    return 'Posted to Slack'; // shown in the run log
});
```

Data lives in your database in `crm_*` tables, with Eloquent models in `RadThemes\RadpackCrm\Models`.

## Scheduled commands

Registered automatically; they need the Laravel scheduler.

| Command | When |
|---|---|
| `radpack-crm:send-emails` | Every minute: scheduled emails and campaign batches |
| `radpack-crm:automations` | Every minute: delayed automation steps |
| `radpack-crm:task-reminders` | Every five minutes |
| `radpack-crm:sync` | Hourly: Stripe, PayPal and Google imports that are turned on. Run `radpack-crm:sync stripe` (or `paypal`, `google`, `lists`) any time. |

## Translations

Every string goes through Laravel's translator, using the English text as the key. To translate the CRM, add the strings to your site's `lang/{locale}.json`.

## Security notes

- Client pages for quotes and invoices use long random links and are not indexed by search engines.
- Client files are stored on a private disk and always downloaded, never displayed inline.
- Saved client passwords and OAuth tokens are encrypted with your app key; API keys are stored as hashes.
- Stripe webhooks and campaign links are signed and verified.
- Webhooks and automation webhook steps won't call private or local addresses (set `RADPACK_CRM_ALLOW_PRIVATE_WEBHOOKS=true` to allow them, e.g. in development).
- Email templates can't run Antlers tags or PHP.

Found a security issue? Please report it privately through the repository's **Security → Report a vulnerability** page rather than opening an issue.

## License

MIT. See [LICENSE.md](LICENSE.md).

## Development

```bash
composer install
npm install
npx vite build      # Control Panel assets, into resources/dist
vendor/bin/phpunit
```
