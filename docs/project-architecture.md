### **Project Architecture Document: Multi-Resort OTA Platform**

#### **1. High-Level Architecture**
The system is built on a **monolithic architecture** with a clear separation of concerns, following a clean layered approach. It consists of a decoupled backend API and a dedicated frontend application. This design allows for independent development and deployment of the user interface and the core business logic. The architecture is designed to be scalable, supporting up to 10,000 concurrent users.

The core technology stack includes:
* **Backend:** Laravel 10 (PHP)
* **Database:** MySQL
* **Frontend:** Next.js 14 (React)
* **Caching:** Redis
* **Queueing:** Redis with Laravel Horizon
* **File Storage:** AWS S3 or Hostinger Object Storage
* **CI/CD:** GitHub Actions

---

#### **2. Backend Architecture (Laravel 10)**
The backend is a **layered monolith** organized into distinct components:
* **Presentation Layer (Controllers):** Handles incoming HTTP requests, validates data, and orchestrates the flow by calling services.
* **Business Logic Layer (Services):** Contains the core business logic, coordinating between repositories and other services. This layer is responsible for tasks like booking validation, rate calculations, and data processing.
* **Data Access Layer (Repositories):** Abstracts the database, providing a consistent interface for the services to interact with the Eloquent models. This separates business logic from data storage details.
* **Queues:** Utilizes Redis and Laravel Horizon for asynchronous tasks, such as sending emails or processing payment webhooks, preventing long-running processes from blocking user requests.

The backend exposes a **REST API** using **Laravel Sanctum** for token-based authentication. API documentation is automatically generated using Laravel/Scribe.

---

#### **3. Frontend Architecture (Next.js 14)**
The frontend is a **server-rendered React application** built with Next.js 14, leveraging the App Router. This approach provides several key benefits:
* **SEO Optimization:** Server-side rendering (SSR) ensures that search engine crawlers can easily index the content.
* **Performance:** The app router allows for selective rendering and caching, improving page load times.
* **Mobile-First Design:** The application is built with a focus on responsiveness, ensuring a seamless user experience across all devices.

The frontend communicates with the backend via the REST API and uses **Stripe Elements** for secure payment processing.

---

#### **4. Data Model**
The database schema is designed for an OTA platform and includes key entities such as:
* `Users`: Manages user roles and authentication.
* `Resorts`: Stores details about each resort.
* `RoomTypes`: Defines the types of rooms available.
* `SeasonalRates`: Handles dynamic pricing based on dates.
* `Inventory`: Tracks the availability of rooms.
* `Bookings`: Stores all booking-related information.
* `Transactions`: Logs all payment gateway transactions.

The model uses **Eloquent relationships**, **migrations**, and **soft deletes** for data integrity and maintainability.

---

#### **5. Deployment & CI/CD**
* **Backend:** Deployed on a Hostinger VPS.
* **Frontend:** Deployed on Vercel.
* **CI/CD:** **GitHub Actions** are configured to automate the testing and deployment process for both the backend and frontend. Pull requests trigger tests, and merges to the main branch trigger deployments.

This setup ensures a consistent and reliable deployment pipeline.