# Affiliate Program Module

The Affiliate Program module allows you to run your own affiliate marketing program within NetSendo. It supports multiple affiliate programs, offers, tiered commissions, and a dedicated partner portal.

## Features

- **Multi-Program Support**: Run different affiliate programs for different product lines.
- **Flexible Commissions**:
  - Percentage-based or fixed amount commissions.
  - Tiered commissions (Platinum, Gold, Silver levels).
  - Custom attribution rules (First Click, Last Click).
- **Partner Portal**: Dedicated area for affiliates to sign up, grab links, view stats, and manage payouts.
- **Conversion Tracking**:
  - Built-in click tracking.
  - Integration with Stripe for purchase tracking.
  - Integration with NetSendo Forms for lead tracking.
- **Payout Management**: Track pending commissions and generate payout batches (CSV export).

## Architecture

### Database Schema

The module uses 13 tables, including:

- `affiliate_programs`: The main container for a program.
- `affiliate_offers`: Specific offers (products, funnels) that affiliates promote.
- `affiliates`: The users who promote your products.
- `affiliate_links` & `affiliate_coupons`: Tracking mechanisms.
- `affiliate_conversions`: Records of successful leads or purchases.
- `affiliate_commissions`: Financial records of earnings.
- `affiliate_payouts`: Records of payments to affiliates.

### Services

- **AffiliateTrackingService**: Handles cookie generation, click recording, and attribution logic.
- **AffiliateConversionService**: Records conversions (leads/purchases) and triggers commission calculation.
- **AffiliateCommissionService**: Calculates commission amounts based on program rules and tiers.
- **AffiliatePayoutService**: Manages the payout workflow.

## Integration Guide

### 1. Stripe Integration

The module automatically listens to Stripe webhooks (`checkout.session.completed` and `charge.refunded`) via `StripeController`.

- **Purchase**: When a Stripe session completes, `AffiliateConversionService::processStripeCheckoutSession` is called. It looks for `affiliate_id` in the session metadata or resolves it from a coupon code.
- **Refund**: When a charge is refunded, commissions are automatically reversed.

### 2. Form Integration

NetSendo Subscription Forms automatically track leads via `FormSubmissionService`.

- **Lead**: When a form is submitted, `AffiliateConversionService::recordLeadFromRequest` is called. It attributes the lead to the affiliate based on the `ns_affiliate` cookie (valid for 30-90 days depending on program settings).

### 3. Frontend Integration (Tracking)

To track clicks, simply direct traffic to the tracking URL:
`https://your-app.com/a/{ref_code}` or `https://your-app.com/offer/{offer_id}/{affiliate_id}`.

The system sets a `ns_visitor` cookie and a `ns_affiliate` cookie.

## User Guide (Owner)

1. **Create a Program**: Go to **Profit > Affiliate Program > Programs**. Set the default commission rate and cookie duration.
2. **Create Offers**: Go to **Offers**. Link an offer to a specific product, Stripe product, or URL.
3. **Approve Affiliates**: Review affiliate applications in **Affiliates**.
4. **Payouts**: Go to **Payouts** to see who is owed money. Generate a payout batch and export the CSV for your bank or PayPal.

## User Guide (Affiliate)

Affiliates access their portal at `/partner/login/{program_slug}`.

- **Dashboard**: View clicks, leads, sales, and earnings.
- **Offers**: Browse available offers and generate tracking links.
- **Reports**: View detailed commission history.

## Development Notes

### Adding New Conversion Triggers

To track a custom conversion event:

```php
use App\Services\AffiliateConversionService;

public function handleCustomEvent(Request $request) {
    // ... your logic ...

    $conversionService = app(AffiliateConversionService::class);
    $conversionService->recordPurchase(
        request: $request,
        amount: 100.00,
        currency: 'PLN',
        entityType: 'custom_event',
        entityId: $event->id
    );
}
```

### Translations

The module is fully localized. Translation files are located at:

- `src/lang/en/affiliate.php`
- `src/lang/pl/affiliate.php`
- `src/lang/de/affiliate.php`
- `src/lang/es/affiliate.php`
