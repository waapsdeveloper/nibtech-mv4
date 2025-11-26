<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Refurbed Merchant API integration

This project ships with `App\Http\Controllers\RefurbedAPIController`, a lightweight service wrapper around the [Refurbed Merchant API](https://gitlab.com/refurbed-community/public-apis). The controller mirrors the marketplace-friendly structure we already use for BackMarket and provides helpers for common workflows such as fetching orders, updating order-item states, managing offers, and generating shipping labels.

### Environment variables

Configure your credentials in `.env` (or the respective secret manager). The relevant keys are:

| Key | Description |
| --- | --- |
| `REFURBED_API_KEY` | Personal access token generated in the Refurbed merchant portal. |
| `REFURBED_API_BASE_URL` | API host (defaults to `https://api.refurbed.com`). |
| `REFURBED_AUTH_SCHEME` | Authorization prefix, usually `Bearer`. |
| `REFURBED_USER_AGENT` | Custom user agent string used for HTTP calls. |
| `REFURBED_TIMEOUT` / `REFURBED_MAX_RETRIES` / `REFURBED_RETRY_DELAY_MS` | Network hardening knobs for slow or rate-limited responses. |
| `REFURBED_LOG_CHANNEL` | Optional Laravel log channel for marketplace traffic. |
| `REFURBED_SOURCE_SYSTEM` | Free-form identifier included via `X-Source-System` header. |

After updating the `.env`, run `php artisan config:clear` so the new values are picked up.

### Usage example

```php
use App\Http\Controllers\RefurbedAPIController;

$refurbed = app(RefurbedAPIController::class);

$orders = $refurbed->listOrders(
	filter: ['state' => ['any_of' => ['NEW', 'ACCEPTED']]],
	pagination: ['limit' => 25]
);

$refurbed->updateOrderItemState(
	orderItemId: '123456',
	state: 'SHIPPED',
	attributes: [
		'parcel_tracking_number' => '1Z9999999999999999',
		'parcel_tracking_carrier' => 'UPS',
	]
);

// Push tracking or fulfillment data for many order items at once (max 50 per API call)
$updates = [
	[
		'id' => 'order-item-1',
		'state' => 'SHIPPED',
		'parcel_tracking_number' => '1Z9999999999999999',
		'parcel_tracking_carrier' => 'UPS',
	],
	[
		'id' => 'order-item-2',
		'state' => 'DELIVERED',
		'parcel_tracking_number' => '00340434123456789012',
		'parcel_tracking_carrier' => 'DHL',
	],
];

$result = $refurbed->batchUpdateOrderItemsState($updates);
// $result['total'] === count($updates);
// $result['batches'] contains the raw Refurbed responses for each chunked API call.

// You can also update non-state attributes (tracking, references, etc.) via BatchUpdateOrderItems
$refurbed->batchUpdateOrderItems($updates, [
	'chunk_size' => 25, // optional override (defaults to 50 which is Refurbed's limit)
	'body' => ['dry_run' => true], // merged into every request body if Refurbed adds flags in the future
]);
```

Refurbed orders that are packed through the sales dashboard (see `app/Http/Livewire/Order.php`) now call these helpers automatically: once a tracking number is captured, the component batches every Refurbed order item ID for that order and sends a `SHIPPED` state update (plus tracking metadata when available). This keeps Refurbed aligned with Back Market without requiring extra manual clicks.

## Back Market Pro (BMPRO) API integration

`App\Http\Controllers\BMPROAPIController` wraps Back Market's seller APIs (orders, listings, and status endpoints). The controller now resolves access tokens from the `marketplace` table: marketplace ID `2` is reserved for the EUR account, while ID `3` is for GBP. When you call any BMPRO method, pass either `marketplace_id` or `currency` via the optional `$options` array to determine which credential should be used. If no option is provided, the controller falls back to the legacy `BMPRO_API_TOKEN` environment variable.

```php
use App\Http\Controllers\BMPROAPIController;

$bmpro = app(BMPROAPIController::class);

// Fetch GBP listings using the marketplace table credentials (id 3)
$listings = $bmpro->getListings(
	filters: ['publication_state' => 'active'],
	environment: 'prod',
	autoPaginate: true,
	options: ['currency' => 'GBP'] // or ['marketplace_id' => 3]
);

// Quick health check using the EUR account
$status = $bmpro->getStatus(options: ['marketplace_id' => 2]);
```

If the referenced marketplace record does not contain an API key (or the database is unavailable), the controller automatically falls back to the `BMPRO_API_TOKEN` value. Make sure `.env` still contains a valid backup token for development and automated tests.

> Tip: you can store the token column either as the raw credential (the controller will prepend `Bearer`) or with an explicit prefix such as `Bearer abc123` / `Basic base64-credentials`. Whatever prefix you provide will be passed straight through to the `Authorization` header.

### Test URL for listings

Need a quick sanity check without writing code? Hit `GET /api/bmpro/listings/test` (optionally pass `currency`, `marketplace_id`, `publication_state`, `per_page`, `page`, or `auto_paginate=false`). If you omit `publication_state`, the controller automatically requests `publication_state=active` so the response only contains active listings. The endpoint proxies through to Back Market Pro using the same controller logic and responds with the raw API payload plus metadata about the filters/options that were applied.

Looking for orders instead? Call `GET /api/bmpro/orders/pending` (supports `currency`, `marketplace_id`, `fulfillment_status`, `financial_status`, `per_page`, `page`, and `auto_paginate=false`). The endpoint defaults to `fulfillment_status=pending`, aggregates across pages when `auto_paginate` is true, and returns the raw BMPRO orders payload alongside the applied filters/options so ops can troubleshoot quickly.

Refer to the controller's docblocks for more available helpers (offers, shipping profiles, shipping labels, etc.). When adding new marketplace flows, prefer extending the controller so we keep all Refurbed wiring in a single place.

### Quick link for active listings

A lightweight JSON endpoint is now available for ops/BI tooling: send a `GET` request to `/api/refurbed/listings/active` (optionally passing `per_page`, `state`, `page_token`, `sort_by`, or `sort_direction` query parameters) to proxy through to Refurbed's `ListOffers` API with the `ACTIVE` state filter pre-applied. The response mirrors the raw Refurbed payload so you can feed it straight into dashboards or scripts.

## DHL Express API integration

`App\Http\Controllers\DHLAPIController` offers a small wrapper around the [MyDHL API](https://developer.dhl.com/api-reference/mydhl-api) so we can programmatically create shipments, fetch rates, pull labels, and track parcels from within Laravel. It mirrors the Refurbed helper conventions (shared HTTP client, retries, structured logging, and env-driven configuration).

### Environment variables

Populate the following keys to enable DHL access:

| Key | Description |
| --- | --- |
| `DHL_CLIENT_ID` / `DHL_CLIENT_SECRET` | OAuth client pair generated in the DHL developer portal. |
| `DHL_API_BASE_URL` | REST base URL (`https://api.dhl.com/mydhlapi/test` for sandbox, `https://api.dhl.com/mydhlapi` for production). |
| `DHL_AUTH_URL` | OAuth token endpoint (defaults to `https://api.dhl.com/mydhlapi/oauth/token`). |
| `DHL_ACCOUNT_NUMBER` | Optional default shipper account injected when creating shipments. |
| `DHL_PREFERRED_LANGUAGE` | Optional `Accept-Language` override (e.g. `en-US`). |
| `DHL_TIMEOUT` / `DHL_MAX_RETRIES` / `DHL_RETRY_DELAY_MS` | Network settings mirroring the Refurbed controller. |
| `DHL_TOKEN_TTL` | Cache lifetime (in seconds) for OAuth tokens. |
| `DHL_CACHE_STORE` | Cache store to persist OAuth tokens (`array`, `file`, `redis`, etc.). |
| `DHL_LOG_CHANNEL` | Optional log channel for marketplace traffic.

### Usage example

```php
use App\Http\Controllers\DHLAPIController;

$dhl = app(DHLAPIController::class);

$shipment = $dhl->createShipment([
	'plannedShippingDateAndTime' => '2024-01-01T12:00:00GMT+01:00',
	'pickup' => [
		'isRequested' => false,
	],
	'productCode' => 'P',
	'customerDetails' => [...],
	'packages' => [...],
]);

$tracking = $dhl->trackShipment($shipment['shipmentTrackingNumber']);
```

Keep using the controller when adding new DHL-related flows so credentials, retries, and logging stay centralized.
