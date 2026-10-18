# Neighbours Rental – Production & Development API Reference

This file contains all base URLs, headers, endpoints, request formats, and demo credentials for connecting mobile, web, and Postman clients to the Neighbours Rental backend.

---

## 🌐 1. Base URLs

| Environment | Base API URL | Health Check URL | Notes |
|---|---|---|---|
| **Production (Render)** | `https://rentalbackend-c7yw.onrender.com/api/v1` | `https://rentalbackend-c7yw.onrender.com/up` | Live Cloud API |
| **Local (Web / iOS)** | `http://localhost:8000/api/v1` | `http://localhost:8000/up` | `php artisan serve` |
| **Local (Android Emulator)** | `http://10.0.2.2:8000/api/v1` | `http://10.0.2.2:8000/up` | Alias to host machine |
| **Local (Physical Device)** | `http://<YOUR_LAN_IP>:8000/api/v1` | `http://<YOUR_LAN_IP>:8000/up` | Same Wi-Fi network |

---

## 🔑 2. Headers & Conventions

Every request should include:
```http
Accept: application/json
Content-Type: application/json
```

For authenticated endpoints (after logging in), include:
```http
Authorization: Bearer <YOUR_SANCTUM_TOKEN>
```

For file uploads (avatar and item photos):
```http
Content-Type: multipart/form-data
```

---

## 👥 3. Demo Accounts (Password: `Password123`)

| Email | Role / State |
|---|---|
| `alex@example.com` | Renter with booking NR1042 (Portable Dryer, confirmed), saved Visa •••• 4242 |
| `maya@example.com` | Owner of Portable Dryer, has active chat thread with Alex |
| `taylor@example.com` | Owner of Cordless Drill with a pending booking request |
| `admin@neighboursrental.ca` | Admin account (listing approvals and support disputes) |

---

## 📋 4. All API Endpoints Reference

### 🟢 4.1 System & Public Meta

| Method | Endpoint Path | Full Production URL | Auth Required | Description |
|---|---|---|---|---|
| `GET` | `/up` | `https://rentalbackend-c7yw.onrender.com/up` | No | Server health check (returns 200 OK) |
| `GET` | `/meta` | `https://rentalbackend-c7yw.onrender.com/api/v1/meta` | No | Fees, conditions, cancellation policies, defaults |
| `GET` | `/categories` | `https://rentalbackend-c7yw.onrender.com/api/v1/categories` | No | All rental categories with item counts and icons |

---

### 🔐 4.2 Authentication (`/auth`)

| Method | Endpoint Path | Full Production URL | Auth | Request Body Parameters |
|---|---|---|---|---|
| `POST` | `/auth/register` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/register` | No | `name`, `email`, `phone`, `password`, `agree_terms: true` |
| `POST` | `/auth/login` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/login` | No | `email`, `password` |
| `POST` | `/auth/google` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/google` | No | `id_token` |
| `GET` | `/auth/me` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/me` | **Yes** | Current authenticated user profile & unread badge counts |
| `POST` | `/auth/logout` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/logout` | **Yes** | Revokes current API access token |
| `POST` | `/auth/verify-email` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/verify-email` | **Yes** | `code` (6-digit numeric verification code) |
| `POST` | `/auth/resend-verification` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/resend-verification` | **Yes** | Requests a new 6-digit email code |
| `POST` | `/auth/change-email` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/change-email` | **Yes** | `email` |
| `POST` | `/auth/forgot-password` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/forgot-password` | No | `email` |
| `POST` | `/auth/reset-password` | `https://rentalbackend-c7yw.onrender.com/api/v1/auth/reset-password` | No | `email`, `code`, `password`, `password_confirmation` |

---

### 👤 4.3 Profile & User Management (`/profile`, `/users`)

| Method | Endpoint Path | Full Production URL | Auth | Request Parameters |
|---|---|---|---|---|
| `PUT` | `/profile` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile` | **Yes** | `name`, `phone`, `bio`, `city`, `province`, `postal_code` |
| `POST` | `/profile/avatar` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile/avatar` | **Yes** | `multipart/form-data`: `avatar` (image file) |
| `PUT` | `/profile/location` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile/location` | **Yes** | `latitude`, `longitude`, `label`, `city`, `province`, `permission` |
| `PUT` | `/profile/notification-settings` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile/notification-settings` | **Yes** | `new_items`, `messages`, `saved_items`, `rental_updates` |
| `PUT` | `/profile/password` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile/password` | **Yes** | `current_password`, `password`, `password_confirmation` |
| `DELETE`| `/profile` | `https://rentalbackend-c7yw.onrender.com/api/v1/profile` | **Yes** | `confirm: "DELETE"` |
| `GET` | `/users/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/users/{id}` | **Yes** | Public profile: stats, active listings, owner reviews |

---

### 🔍 4.4 Items, Search & Details (`/items`)

| Method | Endpoint Path | Full Production URL | Auth | Query / Path Parameters |
|---|---|---|---|---|
| `GET` | `/items` | `https://rentalbackend-c7yw.onrender.com/api/v1/items` | **Yes** | `q`, `category`, `min_price`, `max_price`, `lat`, `lng`, `radius_km`, `start_date`, `end_date`, `delivery=pickup\|delivery`, `condition`, `sort=relevance\|price_low\|price_high\|distance\|rating\|newest`, `page` |
| `GET` | `/items/popular` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/popular` | **Yes** | `lat`, `lng` (returns top-rated items nearby) |
| `GET` | `/items/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/{id}` | **Yes** | Item details, owner summary, reviews, `is_favorite` |
| `GET` | `/items/{id}/availability` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/{id}/availability` | **Yes** | Blocked calendar dates, min/max rental days, pickup hours |
| `GET` | `/items/{id}/quote` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/{id}/quote` | **Yes** | `start_date`, `end_date`, `delivery_type=pickup\|delivery` → calculates daily rent, delivery fee, service fee, total |

---

### ❤️ 4.5 Favorites (`/favorites`)

| Method | Endpoint Path | Full Production URL | Auth | Description |
|---|---|---|---|---|
| `GET` | `/favorites` | `https://rentalbackend-c7yw.onrender.com/api/v1/favorites` | **Yes** | List user's saved items |
| `POST` | `/items/{id}/favorite` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/{id}/favorite` | **Yes** | Add item to favorites |
| `DELETE`| `/items/{id}/favorite` | `https://rentalbackend-c7yw.onrender.com/api/v1/items/{id}/favorite` | **Yes** | Remove item from favorites |

---

### 📦 4.6 Owner Listings Management (`/listings`)

| Method | Endpoint Path | Full Production URL | Auth | Description / Parameters |
|---|---|---|---|---|
| `GET` | `/listings/summary` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/summary` | **Yes** | Status counts: active, pending, draft, paused |
| `GET` | `/listings` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings` | **Yes** | `status=active\|pending\|draft\|paused` |
| `POST` | `/listings` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings` | **Yes** | Create listing draft (`category_id`, `title`, `daily_rate`, etc.) |
| `GET` | `/listings/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}` | **Yes** | Owner's editable view of the item |
| `PUT` | `/listings/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}` | **Yes** | Update item details, pricing, pickup rules |
| `DELETE`| `/listings/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}` | **Yes** | Delete item (if no active bookings) |
| `POST` | `/listings/{id}/photos` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/photos` | **Yes** | `multipart/form-data`: `photos[]` (up to 10 images) |
| `PUT` | `/listings/{id}/photos/order` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/photos/order` | **Yes** | `order: [photo_id_1, photo_id_2, ...]` |
| `DELETE`| `/listings/{id}/photos/{photo_id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/photos/{photo_id}` | **Yes** | Delete single photo |
| `PUT` | `/listings/{id}/availability` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/availability` | **Yes** | `blocked_dates: ["YYYY-MM-DD", ...]` |
| `POST` | `/listings/{id}/submit` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/submit` | **Yes** | Submit draft for review / activate |
| `POST` | `/listings/{id}/pause` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/pause` | **Yes** | Temporarily pause listing |
| `POST` | `/listings/{id}/resume` | `https://rentalbackend-c7yw.onrender.com/api/v1/listings/{id}/resume` | **Yes** | Resume paused listing |

---

### 🤝 4.7 Bookings & Rental Lifecycle (`/bookings`)

| Method | Endpoint Path | Full Production URL | Auth | Parameters / Description |
|---|---|---|---|---|
| `GET` | `/bookings` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings` | **Yes** | `role=renter\|owner`, `status=active\|completed\|cancelled` |
| `POST` | `/bookings` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings` | **Yes** | `item_id`, `start_date`, `end_date`, `delivery_type`, `notes` |
| `GET` | `/bookings/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}` | **Yes** | Booking details, item info, owner/renter contact |
| `POST` | `/bookings/{id}/accept` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/accept` | **Yes** | Owner accepts pending request |
| `POST` | `/bookings/{id}/decline` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/decline` | **Yes** | Owner declines request (`reason`) |
| `POST` | `/bookings/{id}/pay` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/pay` | **Yes** | Renter pays (`payment_method_id`) → confirms booking |
| `GET` | `/bookings/{id}/refund-estimate` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/refund-estimate` | **Yes** | Calculates refund based on cancellation policy |
| `POST` | `/bookings/{id}/cancel` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/cancel` | **Yes** | `reason` → cancels booking and processes refund |
| `POST` | `/bookings/{id}/pickup` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/pickup` | **Yes** | Renter confirms item received |
| `POST` | `/bookings/{id}/return` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/return` | **Yes** | Renter marks item returned |
| `POST` | `/bookings/{id}/confirm-return` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/confirm-return` | **Yes** | Owner confirms return → schedules payout |
| `POST` | `/bookings/{id}/review` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/review` | **Yes** | `rating` (1–5), `comment` |
| `GET` | `/bookings/{id}/timeline` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/timeline` | **Yes** | Event log with timestamps |
| `GET` | `/bookings/{id}/receipt` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/receipt` | **Yes** | PDF/printable receipt data |
| `GET` | `/bookings/{id}/extension-quote` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/extension-quote` | **Yes** | `new_end_date` |
| `POST` | `/bookings/{id}/extensions` | `https://rentalbackend-c7yw.onrender.com/api/v1/bookings/{id}/extensions` | **Yes** | `new_end_date`, `notes` |
| `POST` | `/extensions/{id}/approve` | `https://rentalbackend-c7yw.onrender.com/api/v1/extensions/{id}/approve` | **Yes** | Owner approves extension |
| `POST` | `/extensions/{id}/decline` | `https://rentalbackend-c7yw.onrender.com/api/v1/extensions/{id}/decline` | **Yes** | Owner declines extension |

---

### 💬 4.8 Chat & Messaging (`/conversations`)

| Method | Endpoint Path | Full Production URL | Auth | Parameters |
|---|---|---|---|---|
| `GET` | `/conversations` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations` | **Yes** | List user's active chat channels |
| `POST` | `/conversations` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations` | **Yes** | `user_id`, `item_id` (optional), `booking_id` (optional) |
| `GET` | `/conversations/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations/{id}` | **Yes** | Conversation metadata & participants |
| `GET` | `/conversations/{id}/messages` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations/{id}/messages` | **Yes** | Paginated chat message history |
| `POST` | `/conversations/{id}/messages` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations/{id}/messages` | **Yes** | `body` (text message) |
| `POST` | `/conversations/{id}/read` | `https://rentalbackend-c7yw.onrender.com/api/v1/conversations/{id}/read` | **Yes** | Marks all incoming messages in thread as read |

---

### 🔔 4.9 Notifications (`/notifications`)

| Method | Endpoint Path | Full Production URL | Auth | Description |
|---|---|---|---|---|
| `GET` | `/notifications` | `https://rentalbackend-c7yw.onrender.com/api/v1/notifications` | **Yes** | List all in-app notifications |
| `POST` | `/notifications/read-all` | `https://rentalbackend-c7yw.onrender.com/api/v1/notifications/read-all` | **Yes** | Mark all notifications as read |
| `POST` | `/notifications/{id}/read` | `https://rentalbackend-c7yw.onrender.com/api/v1/notifications/{id}/read` | **Yes** | Mark specific notification as read |
| `DELETE`| `/notifications/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/notifications/{id}` | **Yes** | Delete notification |

---

### 💳 4.10 Payments & Earnings (`/payment-methods`, `/earnings`)

| Method | Endpoint Path | Full Production URL | Auth | Parameters / Description |
|---|---|---|---|---|
| `GET` | `/payment-methods` | `https://rentalbackend-c7yw.onrender.com/api/v1/payment-methods` | **Yes** | Renter's saved cards |
| `POST` | `/payment-methods` | `https://rentalbackend-c7yw.onrender.com/api/v1/payment-methods` | **Yes** | `token` or test card details |
| `POST` | `/payment-methods/{id}/default` | `https://rentalbackend-c7yw.onrender.com/api/v1/payment-methods/{id}/default` | **Yes** | Set default card |
| `DELETE`| `/payment-methods/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/payment-methods/{id}` | **Yes** | Delete saved card |
| `GET` | `/billing-history` | `https://rentalbackend-c7yw.onrender.com/api/v1/billing-history` | **Yes** | List all completed charges |
| `GET` | `/earnings` | `https://rentalbackend-c7yw.onrender.com/api/v1/earnings` | **Yes** | Owner's available balance, pending payouts |
| `GET` | `/earnings/payouts` | `https://rentalbackend-c7yw.onrender.com/api/v1/earnings/payouts` | **Yes** | Payout transaction history |
| `PUT` | `/earnings/payout-account` | `https://rentalbackend-c7yw.onrender.com/api/v1/earnings/payout-account` | **Yes** | `transit_number`, `institution_number`, `account_number` |
| `POST` | `/earnings/withdraw` | `https://rentalbackend-c7yw.onrender.com/api/v1/earnings/withdraw` | **Yes** | `amount` → initiates bank transfer |

---

### 🛡️ 4.11 Support & Dispute Cases (`/support-cases`)

| Method | Endpoint Path | Full Production URL | Auth | Description |
|---|---|---|---|---|
| `GET` | `/support-cases` | `https://rentalbackend-c7yw.onrender.com/api/v1/support-cases` | **Yes** | List user's support tickets |
| `POST` | `/support-cases` | `https://rentalbackend-c7yw.onrender.com/api/v1/support-cases` | **Yes** | `booking_id`, `issue_type`, `description`, `photos[]` |
| `GET` | `/support-cases/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/support-cases/{id}` | **Yes** | Support case details and admin resolution |

---

### ⚙️ 4.12 Admin Endpoints (`/admin`)

*(Requires user account with `is_admin: true`)*

| Method | Endpoint Path | Full Production URL | Description |
|---|---|---|---|
| `GET` | `/admin/listings` | `https://rentalbackend-c7yw.onrender.com/api/v1/admin/listings` | List pending listings requiring moderation |
| `POST` | `/admin/listings/{id}/approve` | `https://rentalbackend-c7yw.onrender.com/api/v1/admin/listings/{id}/approve` | Approve item listing |
| `POST` | `/admin/listings/{id}/reject` | `https://rentalbackend-c7yw.onrender.com/api/v1/admin/listings/{id}/reject` | Reject item listing (`reason`) |
| `GET` | `/admin/support-cases` | `https://rentalbackend-c7yw.onrender.com/api/v1/admin/support-cases` | All disputes / open support cases |
| `PUT` | `/admin/support-cases/{id}` | `https://rentalbackend-c7yw.onrender.com/api/v1/admin/support-cases/{id}` | Resolve case, issue refund, or close ticket |

---

## 📱 5. Flutter Dart Configuration Snippet

Save this in your Flutter project (e.g. `lib/core/constants/api_constants.dart`):

```dart
class ApiConstants {
  // Switch between live Render backend and local dev
  static const bool isProduction = true;

  static const String prodBaseUrl = 'https://rentalbackend-c7yw.onrender.com/api/v1';
  static const String localAndroidUrl = 'http://10.0.2.2:8000/api/v1';
  static const String localIosUrl = 'http://localhost:8000/api/v1';

  static String get baseUrl => isProduction ? prodBaseUrl : localAndroidUrl;

  // Header helpers
  static Map<String, String> headers({String? token}) => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
}
```
