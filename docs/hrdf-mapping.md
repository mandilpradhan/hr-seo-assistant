# HRDF Mapping Reference

This table lists how HR SEO Assistant resolves Schema.org, Open Graph, and Twitter Card fields from the HR Data Framework (HRDF). Only the listed HRDF keys are consulted—if no value is present, the corresponding output is omitted.

## JSON-LD (Organization / Website / WebPage)

| Schema Field | HRDF Source Keys (checked in order) |
| --- | --- |
| Organization.name | `hrdf.org.name`, `hrdf.site.name` |
| Organization.legalName | `hrdf.org.legalName` |
| Organization.url | `hrdf.org.url`, `hrdf.site.url` |
| Organization.logo | `hrdf.org.logo.url`, `hrdf.site.logo_url` |
| Organization.priceRange | `hrdf.org.priceRange` |
| Organization.vatID | `hrdf.org.vatId` |
| Organization.registrationNumber | `hrdf.org.registrationNumber` |
| Organization.sameAs | `hrdf.org.sameAs` |
| Organization.contactPoint | `hrdf.org.contactPoints`, `hrdf.org.contactPoint` |
| Organization.address | `hrdf.org.address` |
| Organization.geo | `hrdf.org.geo` |
| Organization.openingHoursSpecification | `hrdf.org.openingHours` |
| WebSite.name | `hrdf.website.name`, `hrdf.site.name` |
| WebSite.url | `hrdf.website.url`, `hrdf.site.url` |
| WebSite.potentialAction.target | `hrdf.website.search_url_template`, `hrdf.site.search_url_template` |
| WebSite.publishingPrinciples | `hrdf.policy.privacy_url`, `hrdf.policy.terms_url`, `hrdf.policy.refund_url` |
| WebPage.name | `hrdf.webpage.title`, `hrdf.meta.title`, `hrdf.trip.title` |
| WebPage.url | `hrdf.webpage.url`, `hrdf.meta.canonical_url`, `hrdf.trip.url` |
| WebPage.description | `hrdf.webpage.description`, `hrdf.meta.description`, `hrdf.trip.description` |
| WebPage.image / primaryImageOfPage | `hrdf.webpage.image`, `hrdf.webpage.images`, `hrdf.trip.images`, `hrdf.hero.image_url` |
| WebPage.about | Derived from Organization `@id` (`hrdf.org.url` / `hrdf.site.url`) |

## JSON-LD (Trip / Product Graph)

| Schema Field | HRDF Source Keys (checked in order) |
| --- | --- |
| Product.name | `hrdf.trip.title`, `hrdf.webpage.title`, `hrdf.meta.title` |
| Product.url | `hrdf.trip.url`, `hrdf.webpage.url`, `hrdf.meta.canonical_url` |
| Product.description | `hrdf.trip.description`, `hrdf.webpage.description`, `hrdf.meta.description` |
| Product.image[] | `hrdf.trip.images`, `hrdf.webpage.images`, `hrdf.hero.image_url`, `hrdf.gallery.images`, `hrdf.trip.gallery.images` |
| Product.brand | Organization `@id` (`hrdf.org.url`, `hrdf.site.url`) |
| Product.additionalProperty | `hrdf.trip.additionalProperty`, `hrdf.trip.properties` |
| Offer[] | `hrdf.trip.offers`, `hrdf.offer.primary`, `hrdf.trip.vehicles[].offers`, `hrdf.bikes.offers` |
| Offer price | `price.amount`, `price`, `priceAmount` within each offer |
| Offer priceCurrency | `price.currency`, `priceCurrency` within each offer |
| Offer availability | `availability` within each offer |
| Offer inventoryLevel | `inventoryRemaining`, `inventory_remaining` within each offer |
| Offer eligibleQuantity | `eligibleQuantity`, `eligible_quantity` within each offer |
| Offer date windows | `priceValidFrom`, `valid_from`, `priceValidUntil`, `valid_until`, `availabilityEnds`, `validFrom`, `date` within each offer |
| AggregateOffer | `hrdf.trip.aggregateOffer` |
| Itinerary ItemList | `hrdf.trip.itinerary.steps`, `hrdf.itinerary.items` |
| FAQPage | `hrdf.trip.faq.items`, `hrdf.faq.items` |
| Reviews[] | `hrdf.trip.reviews`, `hrdf.reviews` |
| AggregateRating | `hrdf.trip.aggregateRating`, `hrdf.aggregate_rating` |
| Vehicles[] | `hrdf.trip.vehicles`, `hrdf.bikes.list` |
| Vehicle offers[] | `hrdf.trip.vehicles[].offers`, `hrdf.bikes.offers` |
| Stopovers ItemList | `hrdf.trip.stopovers`, `hrdf.stopovers.list` |
| Guides Person[] | `hrdf.trip.guides`, `hrdf.guides.list` |

## Open Graph

| OG Field | HRDF Source Keys (checked in order) |
| --- | --- |
| og:title | `hrdf.webpage.title`, `hrdf.meta.title`, `hrdf.trip.title` |
| og:description | `hrdf.webpage.description`, `hrdf.meta.description`, `hrdf.trip.description` |
| og:url | `hrdf.webpage.url`, `hrdf.meta.canonical_url`, `hrdf.trip.url` |
| og:site_name | `hrdf.org.name`, `hrdf.site.name` |
| og:type | `hrdf.meta.og_type`, `hrdf.webpage.og_type` |
| og:image | `hrdf.webpage.image`, `hrdf.webpage.images`, `hrdf.trip.images`, `hrdf.hero.image_url`, `hrdf.gallery.images`, `hrdf.trip.gallery.images` |
| product:price:amount | Offer.price from HRDF sources listed above |
| product:price:currency | Offer.priceCurrency from HRDF sources listed above |
| product:availability | Offer.availability from HRDF sources listed above |

## Twitter Cards

| Twitter Field | HRDF Source Keys (checked in order) |
| --- | --- |
| twitter:card | `hrdf.twitter.card` |
| twitter:title | `hrdf.webpage.title`, `hrdf.meta.title`, `hrdf.trip.title` |
| twitter:description | `hrdf.webpage.description`, `hrdf.meta.description`, `hrdf.trip.description` |
| twitter:image | `hrdf.webpage.image`, `hrdf.webpage.images`, `hrdf.trip.images`, `hrdf.hero.image_url`, `hrdf.gallery.images`, `hrdf.trip.gallery.images` |
| twitter:site | `hrdf.twitter.site` |
| twitter:creator | `hrdf.twitter.creator` |

