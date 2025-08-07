Project: Multi-Resort OTA Platform MVP (Laravel 10 + FilamentPHP + Next.js)

### 1. Project Overview
Build a **production-ready MVP** for a multi-resort Online Travel Agency (OTA) platform, inspired by Booking.com, tailored for Maldives resorts (currency: **USD**, languages: **English, Russian, French**). use context7.

The platform includes:
- **Backend**: Laravel 10 (monolith with REST API) + FilamentPHP v3 for admin CMS.
- **Frontend**: Next.js 14 (App Router, mobile-first, SEO-optimized).
- **Deployment**: Backend on Hostinger VPS (CloudPanel, Nginx, PHP 8.2), frontend on Vercel.
- **Core Features**: Real-time availability, seasonal rates, booking engine, promotions, payments, guest communication, and analytics.
- **Architecture**: Clean layered monolith (Controllers, Services, Repositories, Queues) for future scalability.
- **References**: [ocus.com](https://www.ocus.com/resources/booking-com-extranet), [AltexSoft](https://www.altexsoft.com/blog/extranet-in-travel-booking-systems/), [HotelMinder](https://www.hotelminder.com/most-important-features-and-functionalities-of-a-hotel-booking-engine), [Hotel Tech Report](https://hoteltechreport.com/news/hotel-booking-engine-guide), [PrenoHQ](https://prenohq.com/blog/how-to-set-seasonal-pricing-for-your-hotel/).

### 2. Domain Data Model (Database Schema)
Create **Eloquent models** with migrations, relationships, and **soft deletes** using Laravel 10 conventions. Use **Spatie/laravel-translatable** for multilingual fields (English, Russian, French). Tables include:

- **Users**: `id`, `name`, `email`, `password`, `role` (enum: admin, resort_manager, agency_operator), `2fa_secret`, timestamps.
- **Resorts**: `id`, `name`, `slug`, `location`, `description` (translatable), `star_rating`, `tax_rules` (JSON, e.g., `{"gst": 8, "service_fee": 12}`), `currency` (default: MVR), `featured_image` (single image URL), `media` (Spatie Media Library collection for gallery), timestamps.
- **RoomTypes**: `id`, `resort_id`, `code`, `name` (translatable), `capacity_adults`, `capacity_children`, `default_price`, `images` (JSON array or Spatie Media Library), timestamps.
- **RatePlans**: `id`, `room_type_id`, `name` (translatable), `refundable` (boolean), `breakfast_included`, `cancellation_policy` (JSON), `deposit_required`, timestamps.
- **SeasonalRates**: `id`, `rate_plan_id`, `start_date`, `end_date`, `nightly_price`, `min_stay`, `max_stay`, timestamps.
- **Inventory**: `id`, `rate_plan_id`, `date`, `available_rooms`, `blocked` (boolean), timestamps.
- **Amenities**: `id`, `code`, `name` (translatable), timestamps.
- **ResortAmenities**: `resort_id`, `amenity_id`.
- **RoomTypeAmenities**: `room_type_id`, `amenity_id`.
- **Transfers**: `id`, `resort_id`, `name` (translatable), `type` (enum: shared, private), `route`, `price`, `capacity`, timestamps.
- **Promotions**: `id`, `resort_id` (nullable), `code`, `description` (translatable), `discount_type` (enum: percentage, fixed), `discount_value`, `start_date`, `end_date`, `max_uses`, `applicable_rate_plans` (JSON array), timestamps.
- **Bookings**: `id`, `user_id` (nullable), `guest_profile_id`, `resort_id`, `room_type_id`, `rate_plan_id`, `check_in`, `check_out`, `total_price_mvr`, `currency_rate_usd`, `promo_id`, `transfer_id`, `status` (enum: pending, confirmed, cancelled, completed), timestamps.
- **BookingItems**: `id`, `booking_id`, `item_type` (enum: transfer, extra_bed, tax, service_fee), `item_id`, `price`, timestamps.
- **GuestProfiles**: `id`, `email`, `full_name`, `phone`, `country`, timestamps.
- **Transactions**: `id`, `booking_id`, `payment_gateway` (enum: stripe, local_mvr), `amount`, `currency`, `status` (enum: pending, success, failed), `gateway_response` (JSON), timestamps.
- **AuditLogs**: `id`, `user_id`, `action`, `auditable_id`, `auditable_type`, `changes` (JSON), timestamps.
- **SiteSettings**: `id`, `key`, `value` (JSON, e.g., `tourist_service_fee: 12%`), timestamps.
- **CommunicationTemplates**: `id`, `type` (enum: email, sms), `name`, `subject` (translatable), `content` (translatable), `placeholders` (JSON, e.g., `{guest_name}`, `{booking_id}`), timestamps.

**Indexes**: Add indexes on `Inventory(date, rate_plan_id)`, `Bookings(check_in, check_out, resort_id)`, `SeasonalRates(start_date, end_date)`.

### 3. Backend (Laravel 10 + FilamentPHP v3)
#### 3.1 FilamentPHP Admin Panel
Scaffold a **FilamentPHP v3** admin panel with custom resources for:
- **Resorts**: CRUD with WYSIWYG (Tiptap editor), image uploads (Spatie Media Library, `gallery` collection), and gallery management.
- **Room Types & Rate Plans**: Nested CRUD with inline seasonal rate management (calendar-grid UI for nightly prices, min/max stay).
- **Inventory**: Bulk update UI for availability (calendar view for `available_rooms`, `blocked`).
- **Amenities & Transfers**: Global library with CRUD, linked to resorts/rooms via pivot tables.
- **Promotions**: CRUD with validation for discount rules and rate plan applicability.
- **Bookings**: Dashboard with search, filters (status, date, resort), and actions (view, cancel, refund, send invoice/email).
- **Reports**: Analytics for occupancy, ADR, RevPAR, cancellation %, promo usage (group by resort/date, use Laravel Charts or Livewire).
- **Finance**: Transaction list with filters, commission calculations (B2B agency rates, JSON-configurable).
- **Guest Communication**: CRUD for `CommunicationTemplates` (email/SMS) with placeholder support.
- **Settings**: Manage `SiteSettings` (e.g., tax rates, currency defaults).

Implement **Filament Shield** for role-based access (super_admin, resort_manager, finance). Log all admin actions to `AuditLogs`.

#### 3.2 REST API (Laravel Sanctum)
Scaffold API routes with **Laravel Sanctum** for authentication and **Laravel/Scribe** for OpenAPI documentation. Endpoints:
- **GET /api/search**: Filter resorts by date range, guests, amenities, price range. Return resorts, room types, availability, and rates.
- **GET /api/availability**: Query by `resort_id`, `room_type_id`, `date_range`. Return available rooms and prices.
- **POST /api/bookings**: Create booking with `resort_id`, `room_type_id`, `rate_plan_id`, `check_in`, `check_out`, `guest_data`, `transfer_id`, `promo_code`. Validate availability with `DB::transaction` or Redis locks.
- **POST /api/payment/webhook**: Handle Stripe/local MVR gateway webhooks to update `Transactions` and `Bookings`.
- **CRUD /api/guest-profiles**: Manage guest data for repeat bookings.
- **Channel Manager Stubs**:
  - **GET /api/channel-manager/availability**: Simulate pushing inventory to external OTAs.
  - **POST /api/channel-manager/bookings**: Simulate receiving bookings from OTAs.

Use **FormRequest** for validation, **API Resources** for responses, and **throttle** middleware for rate limiting.

#### 3.3 Business Logic
- **Booking Engine**: Calculate total price across seasonal rate periods (prorate overlapping seasons). Apply `tax_rules` (from `Resorts`) and `tourist_service_fee` (from `SiteSettings`) as `BookingItems`. Enforce rate plan rules (min/max stay, cancellation, deposit). Handle ancillaries (transfers, extras) as `BookingItems`.
- **Promo Codes**: Validate and apply discounts (percentage/fixed) to eligible rate plans.
- **Inventory Management**: Use optimistic locking (`Inventory::lockForUpdate`) or Redis for availability checks.
- **Currency Handling**: Store prices in MVR, convert to USD using cached rates (e.g., `laravel-exchange-rates`).
- **Queue Jobs**: Use Laravel Horizonexplore various options for email, SMS, and payment processing.

### 4. Frontend (Next.js 14)
Build a **mobile-first, SEO-optimized** frontend using **Next.js 14 (App Router)** with:
- **Homepage**: Resort search with filters (dates, guests, amenities, price).
- **Resort Detail Page**: Resort info, gallery (carousel), amenities, room types, booking calendar (hide unavailable dates, show nightly prices).
- **Booking Flow**: Multi-step checkout (guest details, promo code, transfers, payment via Stripe Elements and **Stripe Payment Request Button** for Apple Pay/Google Pay).
- **My Bookings**: Authenticated user portal to view/cancel bookings.
- **Admin Redirect**: Route `/admin` to Filament panel (external redirect).

Use **next-i18next** for English, Russian, and French localization (auto-detect locale). Implement **Incremental Static Regeneration (ISR)** for resort pages (revalidate every 24 hours). Use **Axios** with **CSRF tokens** (via Sanctum’s CSRF endpoint) for API calls. Ensure **WCAG 2.1 accessibility** compliance.

### 5. Integrations
- **Payments**: Stripe for international cards (with Apple Pay/Google Pay), stub for local MVR gateway (PCI-compliant).
- **Email/SMS**: Mailgun for emails, Twilio for SMS (using `CommunicationTemplates`).
- **Storage**: AWS S3 or Hostinger Object Storage for images (Spatie Media Library).
- **Queue**: Redis + Laravel Horizon for async tasks (notifications, payment webhooks).

### 6. Security & Performance
- **Security**: Sanctum for API/SPA auth, 2FA for admins (Laravel Fortify), input sanitization (Laravel Purifier), HTTPS, CSRF tokens for Next.js forms.
- **Performance**: Cache static data (amenities, settings) in Redis. Optimize DB queries with indexes. Lazy-load frontend images.
- **Scalability**: Support 10k concurrent users, 500 bookings/minute (Redis inventory locks, queue jobs).
- **GDPR Compliance**: Soft deletes, auto-purge bookings/guest data after 6 months (configurable).

### 7. Testing & CI/CD
#### 7.1 Testing
- **Backend**: PHPUnit + Pest for unit/feature tests (booking logic, rates, availability).
- **Frontend**: Playwright for E2E tests (search, booking, checkout).
- **API**: Pest for endpoint tests (use `laravel/scribe`).

#### 7.2 CI/CD (GitHub Actions)
- **PR Workflow**: Run PHP CS Fixer, ESLint, PHPUnit, Pest, Playwright tests.
- **Deploy Workflow**:
  - **Backend**: SSH to Hostinger VPS, run `composer install`, `php artisan migrate`, `php artisan horizon`, restart Nginx.
  - **Frontend**: Vercel Git integration (`next build && next start`).

### 8. Deployment
#### 8.1 Backend (Hostinger VPS via CloudPanel)
- Use PHP 8.2, Nginx, MySQL 8.0, Redis 7.0.
- Steps: Clone repo, set `.env` (DB, Mailgun, Stripe, Redis), run `composer install`, `php artisan migrate --seed`, `php artisan horizon`, configure Nginx vhost, Let’s Encrypt SSL.
- Queue worker: Supervisord for `php artisan horizon`.

#### 8.2 Frontend (Vercel)
- Set environment variables (API URL, Stripe key, i18n config).
- Run `next build && next start` via Vercel CLI.

#### 8.3 Environment Config
- Time zone: `Indian/Maldives`.
- Default currency: MVR.
- Cache driver: Redis.
- Queue driver: Redis.

### 9. Documentation
- **OpenAPI**: Auto-generate via `laravel/scribe` with Axios examples.
- **Admin Guide**: Markdown-based PDF (`laravel-dompdf`) for resort managers (CRUD, rates, bookings).
- **Dev README**: Include architecture diagram (Mermaid.js), setup steps, CI/CD flow, testing guide.

### 10. Agile Milestones
| Sprint | Duration | Deliverables |
|--------|----------|--------------|
| 1      | 2 weeks  | Models, migrations, API (search, availability), Filament resort/room CRUD |
| 2      | 2 weeks  | Seasonal rates, inventory UI, booking API, frontend search/resort pages |
| 3      | 2 weeks  | Booking flow, Stripe integration, email/SMS templates |
| 4      | 1 week   | Promotions, analytics, booking dashboard, audit logs |
| 5      | 1 week   | Tests, CI/CD pipelines, deployment scripts, docs |

### 11. Non-Functional Requirements
- **Idempotency**: Ensure API endpoints (bookings, payments) handle retries safely.
- **Audit Logging**: Log all admin actions to `AuditLogs`.
- **Scalability**: Optimize for 10k concurrent users, 500 bookings/minute.
- **Data Retention**: Soft deletes, auto-purge after 6 months (configurable).

---

### ✅ For Code Agent
> Scaffold a Laravel 10 backend with Eloquent models, migrations, FilamentPHP v3 admin panel, REST API (Sanctum, Scribe), and service/repository layers for booking/availability logic. Include a Next.js 14 frontend (App Router) with search, resort pages, booking flow, and Stripe integration (including Apple Pay/Google Pay). Use TDD (PHPUnit, Pest, Playwright) and set up GitHub Actions for CI/CD to Hostinger VPS (CloudPanel) and Vercel. Generate OpenAPI docs, admin guide PDF, and dev README with architecture diagram. Support English, Russian, and French languages using `spatie/laravel-translatable` and `next-i18next`. Follow the specification exactly, prioritizing clean code, modularity, and production readiness.

