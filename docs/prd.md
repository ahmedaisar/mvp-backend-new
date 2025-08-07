As the Product Manager, I've created a brownfield PRD based on the provided codebase specifications.

### **Product Requirement Document: Multi-Resort OTA Platform MVP**

#### **1. Project Overview**
The platform is a production-ready MVP for a multi-resort Online Travel Agency (OTA) tailored for Maldives resorts. The platform is inspired by Booking.com and supports multiple languages including English, Russian, and French.

**Key Components:**
* **Backend:** Laravel 10 (monolith with REST API) + FilamentPHP v3 for the admin CMS.
* **Frontend:** Next.js 14 (App Router, mobile-first, SEO-optimized).
* **Core Features:** Real-time availability, seasonal rates, a booking engine, promotions, payments, guest communication, and analytics.
* **Architecture:** Clean layered monolith (Controllers, Services, Repositories, Queues) designed for future scalability.
* **Deployment:** Backend on Hostinger VPS, frontend on Vercel.

#### **2. Domain Data Model**
The project uses Eloquent models with migrations, relationships, and soft deletes following Laravel 10 conventions. Key tables include:
* **Users:** Manages user roles (admin, resort_manager, agency_operator) and 2FA secrets.
* **Resorts:** Contains resort details, including translatable descriptions, tax rules, and media galleries.
* **RoomTypes:** Defines room specifics like capacity and default pricing.
* **RatePlans:** Manages refundable policies, breakfast inclusion, and cancellation policies.
* **SeasonalRates:** Sets nightly prices and stay limits for specific date ranges.
* **Inventory:** Tracks `available_rooms` and `blocked` rooms for each rate plan on a given date.
* **Bookings:** Stores booking details, including guest info, check-in/out dates, and status.
* **Transactions:** Records payment gateway information, amounts, and status.
* **Amenities:** A global library of amenities linked to resorts and room types.
* **Promotions:** Configurable discounts by code, type, and value, applicable to specific rate plans.

#### **3. Backend (Laravel 10 + FilamentPHP v3)**
**FilamentPHP Admin Panel**
The admin panel provides custom resources for CRUD operations on resorts, room types, rate plans, and more. It features a calendar-grid UI for managing seasonal rates and bulk updating inventory. Role-based access is implemented using Filament Shield, with all admin actions logged to an `AuditLogs` table.

**REST API (Laravel Sanctum)**
The API uses Laravel Sanctum for authentication and Laravel/Scribe for OpenAPI documentation. Key endpoints include:
* `GET /api/search`: To filter resorts by various criteria.
* `POST /api/bookings`: To create new bookings, with availability validated using database transactions or Redis locks.
* `POST /api/payment/webhook`: To handle payment gateway callbacks.
* `GET/POST /api/channel-manager`: Stubs to simulate pushing and receiving data from external OTAs.

#### **4. Frontend (Next.js 14)**
The frontend is a mobile-first, SEO-optimized application using Next.js 14 (App Router). It includes a homepage with search filters, a resort detail page with a booking calendar, and a multi-step checkout flow. The payment flow integrates Stripe Elements and the Stripe Payment Request Button for Apple Pay/Google Pay. The site also supports English, Russian, and French localization via `next-i18next`.

#### **5. Integrations**
* **Payments:** Stripe for international payments and a stub for a local MVR gateway.
* **Email/SMS:** Mailgun and Twilio, respectively, using `CommunicationTemplates`.
* **Storage:** AWS S3 or Hostinger Object Storage for images via Spatie Media Library.
* **Queue:** Redis and Laravel Horizon for asynchronous tasks.

#### **6. Security & Performance**
* **Security:** Uses Sanctum for API authentication, 2FA for admins, and CSRF tokens for Next.js forms.
* **Performance:** Static data is cached in Redis, and DB queries are optimized with indexes.
* **Scalability:** Designed to support 10k concurrent users and 500 bookings per minute.
* **GDPR Compliance:** Implemented with soft deletes and auto-purging of old data.

#### **7. Testing & CI/CD**
* **Testing:** PHPUnit and Pest for backend tests, and Playwright for E2E tests on the frontend.
* **CI/CD:** GitHub Actions workflows are in place for PRs and deployment to both the backend (Hostinger VPS) and frontend (Vercel).

#### **8. Agile Milestones**
The project is planned across five sprints:
* **Sprint 1:** Models, migrations, core API, and basic Filament CRUD.
* **Sprint 2:** Seasonal rates, inventory UI, booking API, and frontend search.
* **Sprint 3:** Full booking flow with Stripe integration and communication templates.
* **Sprint 4:** Promotions, analytics, booking dashboard, and audit logs.
* **Sprint 5:** Final testing, CI/CD setup, deployment scripts, and documentation.