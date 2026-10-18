# Neighbours Rental – Backend API

Laravel 12 + MySQL + Sanctum REST API for the **Neighbours Rental** Flutter app (peer-to-peer rentals in Edmonton, AB).
It replaces everything in `lib/data/mock.dart` with real data: accounts, listings, search, bookings, payments,
chat, notifications, reviews, earnings and support cases.

## Requirements

PHP 8.2+ (extensions: pdo_mysql, mbstring, openssl, fileinfo, gd or imagick), Composer 2, MySQL 8 (or MariaDB 10.6+).
XAMPP / Laragon / WAMP all work.

## Setup

```bash
cd neighbours_rental_backend
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# create the database first, e.g.  CREATE DATABASE neighbours_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
# then set DB_USERNAME / DB_PASSWORD in .env

php artisan migrate --seed    # tables + demo data from the prototype
php artisan storage:link      # serves uploaded photos at /storage/...
php artisan serve --host=0.0.0.0 --port=8000
```

Base URL: `http://localhost:8000/api/v1`
From the Android emulator use `http://10.0.2.2:8000/api/v1`; from a real phone use your PC's LAN IP.

Scheduler (expires unpaid bookings, sends pickup/return reminders) – add a cron entry, or run `php artisan schedule:work` while developing:

```
* * * * * cd /path/to/neighbours_rental_backend && php artisan schedule:run >> /dev/null 2>&1
```

### Demo accounts (password `Password123`)

| Email | Role |
|---|---|
| alex@example.com | Renter with booking **NR1042** (Maya's Portable Dryer, confirmed), saved card Visa •••• 4242 |
| maya@example.com | Owner of the Portable Dryer, has a chat with Alex |
| taylor@example.com | Owner of the Cordless Drill with a pending request from Sam |
| admin@neighboursrental.ca | Admin (approve listings, handle support cases) |

Verification and password-reset codes are e-mailed; with `MAIL_MAILER=log` they appear in `storage/logs/laravel.log`.

## Conventions

Send `Accept: application/json` on every request and `Authorization: Bearer {token}` after login.
Single objects come back as `{"data": {...}}`, lists as `{"data": [...], "links": {...}, "meta": {...}}`.
Validation and business-rule errors return **422** `{"message": "...", "errors": {"field": ["..."]}}`.
A declined card returns **402** from `/bookings/{id}/pay`. Money is CAD as numbers (e.g. `66.0`), dates are `YYYY-MM-DD`, timestamps ISO-8601.
File uploads use `multipart/form-data` (arrays as `photos[]`).

## Booking lifecycle

```
requested ──accept──▶ accepted ──pay──▶ confirmed ──pickup──▶ active ──return──▶ returned ──confirm-return──▶ completed
    │                    │                 │                                                                   │
 decline              expire /          cancel (refund per policy)                                         review (both sides)
                      cancel
```

Pricing follows the app: days are inclusive (Oct 12 → Oct 14 = 3 days), service fee 10% of the rental, taxes 0% (both configurable in `.env`).
Owner earnings are rental + delivery fee; the payout becomes withdrawable `NR_PAYOUT_DELAY_DAYS` after the owner confirms the return.
Cancellation policies are `flexible` (full refund until pickup time) and `moderate` (full minus service fee ≥ 7 days out, 50% 1–6 days, none in the last 24 h).
The exact pickup address is only revealed to the renter after payment.

## Endpoints → app screens

### Auth & account
| Method | Path | Screen / purpose |
|---|---|---|
| POST | /auth/register | Create your account (`name, email, phone, password, agree_terms`) → token; a 6-digit code is emailed |
| POST | /auth/login | Sign in (`email, password`) → token |
| POST | /auth/google | Continue with Google (`id_token` from google_sign_in) |
| POST | /auth/verify-email | Check your email (`code`) |
| POST | /auth/resend-verification | "Resend code" |
| POST | /auth/change-email | "Change email address" |
| POST | /auth/forgot-password | Reset your password (`email`) |
| POST | /auth/reset-password | Set new password (`email, code, password, password_confirmation`) |
| GET | /auth/me | Current user + unread counts |
| POST | /auth/logout | Logout |
| PUT | /profile | Set up / edit profile (`name, phone, bio, city, province, postal_code`) |
| POST | /profile/avatar | Profile photo (`avatar` file) |
| PUT | /profile/location | Location step (`latitude, longitude, label, city, province, permission`) |
| PUT | /profile/notification-settings | `new_items, messages, saved_items, rental_updates` |
| PUT | /profile/password | Change password |
| DELETE | /profile | Delete account (`confirm: "DELETE"`) |
| GET | /users/{id} | Public profile: stats, listings, reviews |

### Explore & items
| Method | Path | Purpose |
|---|---|---|
| GET | /meta, /categories | Dropdown values, fees, categories (public) |
| GET | /items | Search results + Filters: `q, category, min_price, max_price, lat, lng, radius_km, start_date, end_date, delivery=pickup\|delivery, condition, sort=relevance\|price_low\|price_high\|distance\|rating\|newest, page` |
| GET | /items/popular | "Popular near you" |
| GET | /items/{id} | Item details (+ latest reviews, `distance_km`, `is_favorite`) |
| GET | /items/{id}/availability | Calendar: `unavailable_dates`, min/max days, pickup window |
| GET | /items/{id}/quote | Price breakdown for `start_date, end_date, delivery` |
| GET | /favorites | Favorites tab |
| POST / DELETE | /items/{id}/favorite | Heart toggle |

### Listing an item (owner)
| Method | Path | Step |
|---|---|---|
| POST | /listings | Start draft (any fields, optional `photos[]`) |
| POST | /listings/{id}/photos | Add item photos (max 10) |
| PUT | /listings/{id}/photos/order | Reorder (`photo_ids[]`, first = cover) |
| DELETE | /listings/{id}/photos/{photoId} | Remove photo |
| PUT | /listings/{id} | Details, pricing, pickup & delivery, "Save draft" |
| PUT | /listings/{id}/availability | `blocked_dates[]`, `pickup_window_start/end` (HH:MM), `advance_notice_hours` |
| GET | /listings/{id} | Preview (+ blocked dates, upcoming bookings) |
| POST | /listings/{id}/submit | Submit listing → `pending` (or `active` if auto-approve) |
| POST | /listings/{id}/pause, /resume | Pause listing |
| DELETE | /listings/{id} | Delete |
| GET | /listings?status= | My listings tabs (Active / Pending / Paused) |
| GET | /listings/summary | Totals + pending/paid earnings cards |

### Bookings
| Method | Path | Screen |
|---|---|---|
| POST | /bookings | Rental request (`item_id, start_date, end_date, pickup_time, message, delivery`) |
| GET | /bookings?role=renter\|owner&group=upcoming\|active\|past\|requests | My Rentals / owner requests |
| GET | /bookings/{id} | Booking / active rental details |
| POST | /bookings/{id}/accept, /decline | Owner request screen |
| POST | /bookings/{id}/pay | Checkout (`payment_method_id, agree_terms, simulate_failure`) |
| GET | /bookings/{id}/refund-estimate | Refund estimate + policy schedule |
| POST | /bookings/{id}/cancel | Cancel rental (`reason`, `details`) |
| POST | /bookings/{id}/pickup | Pickup condition check (`photos[], works, accessories_included, scratches_noted, notes`) |
| POST | /bookings/{id}/return | Return condition check (`photos[], accessories_checklist[i][name/returned], notes, returned_at`) |
| POST | /bookings/{id}/confirm-return | Owner confirms → completed |
| POST | /bookings/{id}/review | Rate (`rating 1-5, comment`) |
| GET | /bookings/{id}/timeline | Rental timeline |
| GET | /bookings/{id}/receipt | View / download receipt |
| GET | /bookings/{id}/extension-quote?new_end_date= | Extension cost preview |
| POST | /bookings/{id}/extensions | Request extension (`new_end_date, message`) |
| POST | /extensions/{id}/approve, /decline | Owner answers (approval charges the renter's card) |

### Messages, notifications, support
| Method | Path | Purpose |
|---|---|---|
| GET | /conversations?filter=all\|rentals\|support\|unread&search= | Messages tab |
| POST | /conversations | Message owner / Contact (`user_id` or `item_id`, `body`) |
| GET | /conversations/{id}/messages?after_id=&before_id= | Chat history / polling |
| POST | /conversations/{id}/messages | Send (`body`, optional `attachment`) |
| POST | /conversations/{id}/read | Mark read |
| GET | /notifications?category=all\|bookings\|reviews\|updates | Notifications screen |
| POST | /notifications/{id}/read, /notifications/read-all | Mark read |
| POST | /support-cases | Report issue (`booking_id, type=damage\|not_working\|late_return\|refund_request\|other, description, photos[]`) |
| GET | /support-cases, /support-cases/{id} | Support case status + timeline |

### Payments & earnings
| Method | Path | Purpose |
|---|---|---|
| GET / POST | /payment-methods | List / add card (`brand, last4, exp_month, exp_year, provider_token`) |
| POST | /payment-methods/{id}/default | Make default |
| DELETE | /payment-methods/{id} | Remove |
| GET | /billing-history | Receipts (charges + refunds) |
| GET | /earnings?period=this_month\|last_month\|this_year\|all | Owner earnings overview |
| GET | /earnings/payouts | Payout history |
| PUT | /earnings/payout-account | Set up payouts (`account_holder, institution_number, transit_number, account_number`) |
| POST | /earnings/withdraw | Withdraw available balance |

### Admin (is_admin users)
`GET /admin/listings?status=pending`, `POST /admin/listings/{id}/approve`, `POST /admin/listings/{id}/reject` (`reason`),
`GET /admin/support-cases`, `PUT /admin/support-cases/{id}` (`status=reviewing|resolved, resolution, refund_amount, message`).
Admins reply in support chats with the normal `POST /conversations/{id}/messages`.

## Before going to production

Payments use `FakePaymentGateway` (always succeeds unless `simulate_failure` is sent or the card's last4 is `0002`).
Implement `App\Services\Payments\PaymentGateway` with Stripe (PaymentIntents + Stripe Connect for payouts) and bind it in
`AppServiceProvider`; the app should tokenise cards with the Stripe SDK and send only `provider_token`.
Also configure a real mailer, set `APP_DEBUG=false`, set `GOOGLE_CLIENT_IDS`, and consider push notifications (FCM) in
`NotificationService` and real-time chat via Laravel Reverb instead of polling.

## Project layout

```
app/Http/Controllers/Api   one controller per area (Auth, Item, Listing, Booking, Conversation, ...)
app/Http/Resources         JSON shapes returned to the app
app/Models                 Eloquent models
app/Services               BookingService (state machine), PricingService (quotes, refunds), Messaging, Notifications, Payments
config/neighbours.php      business settings (fees, policies, limits)
database/migrations        schema
database/seeders           demo data matching the prototype
routes/api.php             all endpoints
routes/console.php         scheduled jobs
postman_collection.json    import into Postman to try every endpoint
```
