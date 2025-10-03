# HR SEO Assistant → HR Data Framework Handoff

## Overview
HR SEO Assistant now treats the HR Data Framework (HRDF) as the single source of truth for every JSON-LD node (Organization, WebSite, WebPage, Trip/Product, Offers, Itinerary, FAQ, Vehicles, Reviews). The plugin only retains two temporary fallbacks: site identity (name/URL + theme logo) and trip description copy (post excerpt/content) when HRDF is empty. This document defines the HRDF keys, shapes, and validation rules required so the JSON-LD module can operate without legacy lookups.

## Key Map by Node

### Organization / Brand
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `organization.name` | string | ✅ | `Himalayan Rides`
| `organization.url` | string (absolute URL) | ✅ | `https://example.com/`
| `organization.logo.url` | string (absolute URL) | ✅ | `https://example.com/uploads/logo.png`
| `organization.logo.attachment_id` | int (WP attachment) | optional | `214`
| `organization.logo.width` | int | optional | `512`
| `organization.logo.height` | int | optional | `128`
| `organization.logo.caption` | string | optional | `Adventure travel mark`
| `organization.legal_name` | string | optional | `Himalayan Rides Pvt. Ltd.`
| `organization.slogan` | string | optional | `Adventure without compromise`
| `organization.description` | string (rich text OK) | optional | `Premium motorcycle expeditions across the Himalayas.`
| `organization.founding_date` | ISO 8601 date | optional | `2015-06-01`
| `organization.email` | string (email) | optional | `hello@example.com`
| `organization.telephone` | string (E.164 preferred) | optional | `+9779800000000`
| `organization.tax_id` | string | optional | `PAN-123456789`
| `organization.vat_id` | string | optional | `VAT-567890`
| `organization.duns` | string | optional | `123456789`
| `organization.address.streetAddress` | string | optional | `Boudha-6`
| `organization.address.addressLocality` | string | optional | `Kathmandu`
| `organization.address.addressRegion` | string | optional | `Bagmati`
| `organization.address.postalCode` | string | optional | `44600`
| `organization.address.addressCountry` | string (ISO 3166-1 alpha-2) | optional | `NP`
| `organization.same_as[]` | array<string URL> | optional | `https://facebook.com/himalayanrides`
| `organization.contact_points[]` | array<ContactPoint> | optional | see validation

Each `contact_points` entry supports: `contactType` (string), `telephone` (string), `email` (string), `areaServed` (string/array), `availableLanguage` (string/array), `contactOption` (string/array).

### WebSite
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `website.url` | string (absolute URL) | ✅ | `https://example.com/`
| `website.name` | string | ✅ | `Himalayan Rides`
| `website.alternate_name` | string | optional | `HR Adventures`
| `website.description` | string | optional | `Adventure motorbike tours across the Himalayas.`
| `website.in_language` | string (BCP 47) | optional | `en-US`
| `website.potential_action` | object (SearchAction or similar) | optional | Pre-built schema structure with `@type`, `target`, `query-input`.

### WebPage (per post)
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `webpage.url` | string (absolute URL) | ✅ | `https://example.com/trips/annapurna-loop/`
| `webpage.name` | string | ✅ | `Annapurna Loop Motorcycle Tour`
| `webpage.description` | string | ✅ | `11-day Annapurna motorcycle tour covering Pokhara, Mustang, and Jomsom.`
| `webpage.primary_image` | string (URL) | optional | `https://example.com/uploads/annapurna-cover.jpg`
| `webpage.breadcrumb` | array (BreadcrumbList or string refs) | optional | Pre-formatted schema nodes
| `webpage.speakable` | array (SpeakableSpecification) | optional | Pre-formatted schema nodes
| `webpage.date_published` | ISO 8601 date/datetime | optional | `2025-09-15`
| `webpage.date_modified` | ISO 8601 date/datetime | optional | `2025-09-30`

### Trip / Product
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.product.url` | string (absolute URL) | ✅ | `https://example.com/trips/annapurna-loop/`
| `trip.product.name` | string | ✅ | `Annapurna Loop Motorcycle Tour`
| `trip.product.description` | string | ✅ | `Experience the Annapurna circuit on a Royal Enfield Himalayan...`
| `trip.product.images[]` | array<string URL> | optional (>=1 recommended) | `https://example.com/uploads/annapurna-day1.jpg`
| `trip.product.sku` | string | optional | `HR-ANNA-11D`
| `trip.product.mpn` | string | optional | `ANNA-2025`
| `trip.product.color` | string | optional | `Royal Enfield Himalayan`
| `trip.product.category` | string | optional | `AdventureTour`
| `trip.product.additional_properties[]` | array<PropertyValue> | optional | see validation
| `trip.product.about[]` | array<Thing reference> | optional | e.g. `[ { "@id": "https://example.com/vehicles/himalayan#bike" } ]`
| `trip.product.has_part[]` | array<Thing reference> | optional | itinerary/faq anchor references

`additional_properties` entries require `name` + `value`, optional `unitCode`, optional nested `valueReference` object.

### Offers & AggregateOffer
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.offers[]` | array<Offer source objects> | ✅ when offers exist | see validation below
| `trip.aggregate_offer.currency` | string (ISO 4217) | optional (auto-resolved if omitted) | `USD`

Each `trip.offers[]` entry supports:
- `price` (string or numeric) ✅
- `currency` (ISO 4217) ✅
- `availability` (`InStock`, `SoldOut`, `LimitedAvailability`, `PreOrder`, or full schema URL) optional
- `availability_starts`, `availability_ends`, `valid_from`, `valid_through`, `price_valid_until` (ISO 8601 date/time) optional
- `url` (absolute URL) optional
- `inventory_level`, `eligible_quantity` (int or QuantitativeValue object) optional
- `name`, `sku`, `category`, `item_condition`, `description` optional

AggregateOffer is assembled automatically; if `trip.aggregate_offer.currency` is blank, the plugin uses the shared currency from all offers (fails if mixed).

### Itinerary
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.itinerary.name` | string | optional (defaults to `Itinerary`) | `11-Day Itinerary`
| `trip.itinerary.description` | string | optional | `Daily breakdown of the Annapurna expedition.`
| `trip.itinerary.url` | string (absolute URL) | optional | `https://example.com/trips/annapurna-loop/#itinerary`
| `trip.itinerary.steps[]` | array<ListItem source> | ✅ if itinerary present | see validation

Each step requires `name` (string). Optional fields: `description` (string), `startDate`, `endDate` (ISO 8601), `position` (int). If `position` missing, plugin assigns sequential positions.

### FAQPage
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.faq.url` | string (absolute URL) | optional | `https://example.com/trips/annapurna-loop/#faqs`
| `trip.faq[]` | array | ✅ if FAQ present | Each entry: `{ "question": "Do I need prior experience?", "answer": "Yes, at least 2 years of riding..." }`

Answers may contain limited HTML (`<p>`, `<br>`, `<ul>/<ol>/<li>`, `<strong>`, `<em>`, `<a>` with `href/title/rel`).

### Vehicles & Rental Offers
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.vehicles[]` | array | optional | see below

Vehicle entry fields:
- `@id` or `id` or `url` (absolute URL) ✅
- `name` (string) ✅
- `image` (URL) or `images[]` (URLs) optional
- `description` (string) optional
- `brand` (Brand object) optional
- `additionalProperty[]` (PropertyValue) optional
- `offers[]` (array<Offer source objects>) optional – same schema as `trip.offers[]`

### Reviews & AggregateRating
| Path | Type | Required | Example |
| --- | --- | --- | --- |
| `trip.reviews.items[]` | array | optional | Each entry: `{ "id": "https://example.com/reviews/sarah", "reviewBody": "Amazing ride!", "datePublished": "2025-03-10", "author": { "@type": "Person", "name": "Sarah" }, "reviewRating": { "ratingValue": 5, "bestRating": 5, "worstRating": 1 } }`
| `trip.reviews.aggregate` | object | optional | `{ "ratingValue": 4.8, "reviewCount": 32, "bestRating": 5, "worstRating": 1 }`

Review items require a stable identifier: `id`, `@id`, or `url` (absolute URL). Author objects must provide `name`, optional `@type`. Rating values should be numeric (0–5 typical).

## Validation Rules
- **URLs:** Must be absolute (`https://`). The plugin normalizes but drops invalid entries.
- **Currency codes:** ISO 4217 uppercase (e.g., `USD`, `EUR`). Mixed currencies in a single trip are not supported.
- **Dates:** ISO 8601 (`YYYY-MM-DD` or `YYYY-MM-DDThh:mm:ss±hh:mm`). Non-compliant values are ignored.
- **Quantities:** Integers for `inventory_level` and `eligible_quantity`. Can also be full `QuantitativeValue` arrays with `value` and optional `unitCode`.
- **Text fields:** UTF-8 strings, trimmed. Descriptions longer than ~400 characters should be truncated upstream.
- **Arrays:** Provide empty arrays instead of `null` when intentionally blank.

## Edge Cases to Support
- Trips with **no upcoming offers** → provide empty `trip.offers[]` and omit `trip.aggregate_offer.currency`.
- Offers flagged as **sold out** → set `availability = "SoldOut"` and `inventory_level = 0`.
- Trips with **no itinerary or FAQ** → supply empty arrays so plugin skips those nodes.
- **Missing logo** → once HRDF provides `organization.logo.url` the plugin stops falling back to theme custom logo.
- **Unknown currency** → do not emit offer; plugin will skip entries lacking currency.
- **Multi-vehicle trips** → ensure each vehicle has a unique `@id`/URL.
- **Reviews without ratings** → omit `reviewRating`; aggregate should still include `reviewCount` if ratings exist.
- **No images** → plugin omits `image` array, but Google prefers at least one.

## Current Fallbacks & Required Keys to Remove Them
| Fallback | Trigger | HRDF keys needed to remove |
| --- | --- | --- |
| Site name & URL from WordPress | Missing `organization.name`/`organization.url` or `website.name`/`website.url` | Populate `organization.name`, `organization.url`, `website.name`, `website.url` for every site |
| Theme custom logo | Missing HRDF logo reference | Provide `organization.logo.url` (or attachment ID + dimensions) |
| Trip description from post excerpt/content | Missing `trip.product.description` | Provide sanitized `trip.product.description` |

No other fallbacks remain; missing HRDF data results in omitted fields.

## Migration Guidance
1. **Backfill HRDF**
   - Mirror existing organization settings into the HRDF keys listed above.
   - For every published Trip post, populate `trip.product.*`, `trip.offers[]`, itinerary steps, FAQ entries, vehicles, and reviews.
   - Ensure values are sanitized (strip dangerous HTML, convert smart quotes, normalise whitespace).
2. **Data Normalisation**
   - URLs should already be canonical HTTPS.
   - Convert currencies to ISO 4217 and round prices to two decimals (strings preferred to avoid float drift).
   - Sanitize descriptions/answers to allow only the whitelisted HTML tags.
3. **Contracts**
   - Guarantee that when HRDF returns an array/object the keys match the shapes documented above.
   - Provide consistent identifiers (`@id` or `url`) for vehicles, itinerary anchors, FAQ anchors, and reviews.
4. **After HRDF Backfill**
   - Notify the HR SEO Assistant team so we can remove the temporary fallbacks and hard failures for missing data.

## Sanitization Expectations
- Apply HTML sanitization upstream: only allow tags permitted in answers or descriptions.
- Trim leading/trailing whitespace.
- Remove duplicate spaces and convert Windows line endings to `\n`.
- Encode special characters in URLs (spaces → `%20`).

## Testing Checklist for HRDF Delivery
1. **Site Identity**
   - Organization node shows HRDF name, URL, legal name, logo, contact points.
   - WebSite node uses HRDF name and URL, includes description/potential action when supplied.
2. **Trip/Product**
   - Product name, description, and images match HRDF values.
   - Additional properties render each `PropertyValue` correctly.
3. **Offers**
   - Offer list matches HRDF dataset (dates, prices, availability, inventory).
   - AggregateOffer currency/low/high price computed correctly.
4. **Itinerary**
   - ItemList includes all steps with positions, descriptions, optional start/end dates.
5. **FAQ**
   - FAQPage contains every Q/A pair with sanitized HTML answers.
6. **Vehicles**
   - Vehicle nodes exist for each HRDF entry, carrying offers when provided.
7. **Reviews**
   - Review nodes reflect HRDF authors, bodies, dates, and ratings; AggregateRating values align with dataset.
8. **Edge Cases**
   - Trip without offers (empty array) renders no AggregateOffer.
   - Trip with sold-out offers reflects `SoldOut` availability.
   - Trip missing itinerary or FAQ produces no orphan nodes.
9. **Preview UI**
   - Admin JSON-LD Preview displays HRDF-sourced values without referencing legacy meta.

Deliver HRDF in this shape so HR SEO Assistant can remove all fallbacks and rely solely on HRDF for schema output.
