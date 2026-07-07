# AI-Solution B2B Portal — Enterprise AI Suite Catalog & CRM Dashboard

AI-Solution is a B2B web application designed for enterprise software cataloging, lead capture, and CRM deal management. The application features a dynamic customer portal with accessibility controls, a smart semantic chatbot, verified customer reviews, event registration, and a secure admin dashboard powered by an automated SQLite database and pure PHP SMTP sockets.

---

## 🚀 Key Features

### 💻 Customer Portal (Public Site)
* **Product Catalog**: Dynamic catalog loaded via SQLite showing product descriptions, categories, tags, pricing, and ratings. Customers can filter by category (All, New, Legacy, Analytics, Assistants) and sort by Newest, Top Rated, and Recommended.
* **Interactive Modal Spec Sheets**: View integration guidelines, deployment options (Cloud / On-Premise), spec details, and dynamic custom packages (Basic, Standard, Custom Enterprise).
* **Lead Capture & Demo Scheduler**:
  * **Inquiry (Free)**: General interest forms.
  * **Set a Demo**: Schedules product sandboxes. Automatically calculates a 5% deposit of the product subscription price (e.g. £14.95 for basic or £39.95 for standard packages) and displays the notice: **"Demo may charge, we will contact you shortly."**
* **Instant Confirmation Emails**: Submitting forms triggers confirmation emails containing unique, sequential client IDs, company info, and pricing details via SMTP.
* **Accessibility Suite**: Integrated font scaling (`+`, `-`, and Reset) and a persistent High Contrast mode that optimizes readability by styling colors, cards, and forms.
* **Verified Reviews System**: Allows customers to review products. The system checks if the email is in the database and tags reviews as "Verified" only for customers with logged purchases.
* **Interactive Chatbot**: Floating chatbot powered by semantic matching. It answers pricing questions, details specific products, lists office locations, and provides structured code snippets for APIs.
* **Events Registration**: Register for live summits and webinars.

### 🛡️ Admin Portal (`admin-login.php` & `admin-dashboard.php`)
* **Secure Authentication**: Protected with session-level security and SQLite-backed password hashing. Supports an offline fallback mode if database files are locked.
* **Executive Overview Tab**: Real-time business intelligence metrics including total inquiries, total client accounts, active deal pipeline values, total closed revenue, and pending demo deposits.
* **CRM Inquiries Tab**: Complete deal pipeline table allowing sorting, search, and deletion.
* **Deal Management Modal**: View custom client wishes and conversational threads:
  * Log payments (updates received amounts and switches payment status to Paid).
  * Update deal statuses (New Lead, Contacted, Proposal Sent, Sold, Closed Lost, Cancelled).
  * *Cancelled status* deletes the record permanently.
  * *Sold status* automatically moves the customer into the buyer logs, verify reviews associated with their email, and credits their total spent.
* **Interactive Communications Center**: View client-admin chat histories, add private notes, or type emails that are sent directly to the client's inbox via Gmail SMTP.
* **Customer Management Tab**: View sequential Client IDs, dates registered, and update spent balances.
* **Product Catalog Manager**: Add, edit, or delete products. Supports file uploads for catalog images.
* **Review Moderation Tab**: Manually verify reviews or delete spam posts.
* **Event Registrations Desk**: List summit registrants and send custom invitation passes.
* **Analytics & Graphs**: Visually analyze top countries, top-performing companies, and monthly request timelines.

---

## 🗃️ Database Architecture

The system utilizes an embedded SQLite database (`ai_solution_db.sqlite`) which initializes, creates, and seeds its tables automatically when the application is loaded.

### Database Schema Details

#### 1. `admin_users`
Stores administrative authentication credentials.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `username` (TEXT, Unique) — Default: `admin`
* `password` (TEXT) — Hashed via `password_hash()` (Default raw: `AdminSecure2026!`)

#### 2. `customers`
Stores registered customer accounts and their total transaction spend.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `full_name` (TEXT)
* `email_address` (TEXT)
* `amount` (REAL, Default `0.00`) — Total spent on subscriptions
* `created_at` (DATETIME, Default current timestamp)

#### 3. `products`
Product catalog details.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `name` (TEXT, Unique)
* `description` (TEXT)
* `detail_description` (TEXT)
* `category` (TEXT)
* `tags` (TEXT)
* `integration` (TEXT)
* `deployment` (TEXT)
* `release_date` (TEXT)
* `rating` (REAL, Default `5.00`)
* `review_count` (INTEGER, Default `0`)
* `image_path` (TEXT)
* `basic_price` (REAL)
* `standard_price` (REAL)
* `custom_price` (TEXT, Default `'Custom'`)
* `created_at` (DATETIME, Default current timestamp)

#### 4. `customer_purchases`
Tracks products owned by customers.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `customer_id` (INTEGER, Foreign Key referencing `customers(id)`)
* `product_name` (TEXT)
* `purchase_date` (DATETIME, Default current timestamp)

#### 5. `customer_inquiries`
Main CRM database for lead generation and payments.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `customer_id` (INTEGER, Foreign Key referencing `customers(id)`)
* `full_name` (TEXT)
* `email_address` (TEXT)
* `phone_number` (TEXT)
* `company_name` (TEXT)
* `country` (TEXT)
* `job_title` (TEXT)
* `request_type` (TEXT) — `Inquiry` or `Demo`
* `product_name` (TEXT)
* `package_name` (TEXT) — Basic, Standard, or Custom
* `deposit_amount` (REAL, Default `0.00`)
* `payment_status` (TEXT) — `Free`, `Pending`, or `Paid`
* `custom_wishes` (TEXT)
* `inquiry_details` (TEXT)
* `deal_status` (TEXT) — `New Lead`, `Contacted`, `Proposal Sent`, `Sold`, `Closed Lost`
* `total_received` (REAL, Default `0.00`) — Payments logged by admin
* `deal_value` (REAL, Default `0.00`)
* `created_at` (DATETIME, Default current timestamp)

#### 6. `customer_conversations`
Audit log of communications inside a lead's profile.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `inquiry_id` (INTEGER, Foreign Key referencing `customer_inquiries(id)`)
* `sender` (TEXT) — `Customer`, `Admin`, `System`, or `Admin Note`
* `message` (TEXT)
* `created_at` (DATETIME, Default current timestamp)

#### 7. `customer_reviews`
Customer-written product feedback.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `reviewer_name` (TEXT)
* `reviewer_role` (TEXT)
* `email_address` (TEXT)
* `rating` (INTEGER)
* `product_name` (TEXT)
* `review_date` (TEXT) — Format: `M Y`
* `review_text` (TEXT)
* `reviewer_img` (TEXT)
* `is_verified` (INTEGER, Default `0`) — `1` for verified buyer reviews
* `created_at` (DATETIME, Default current timestamp)

#### 8. `events`
Summit and webinar listings.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `title` (TEXT)
* `badge_text` (TEXT)
* `badge_class` (TEXT)
* `description` (TEXT)
* `event_date` (TEXT)
* `image_path` (TEXT)
* `created_at` (DATETIME, Default current timestamp)

#### 9. `event_registrations`
Public user registration passes for events.
* `id` (INTEGER, Primary Key, Auto-Increment)
* `full_name` (TEXT)
* `email_address` (TEXT)
* `company_name` (TEXT)
* `event_title` (TEXT)
* `registration_date` (DATETIME, Default current timestamp)

---

## 🎨 Design System & Aesthetics

* **Fonts**:
  * Brand & Headers: `Outfit` (sans-serif) for high-impact geometric headings.
  * Body text: `Plus Jakarta Sans` for clean, professional legibility.
* **Colors**:
  * Primary Dark background: `#070b19` (rich midnight blue)
  * Secondary Dark paneling: `#0f162d`
  * Accent Cyan: `#00f0ff` (used for active status, focus rings, highlights)
  * Accent Purple: `#8257e5` (vibrant backdrop gradients)
* **Glassmorphism**: Elements like modals, cards, chatbot interfaces, and charts leverage CSS `backdrop-filter: blur(12px)` combined with subtle white borders (`rgba(255,255,255,0.08)`) to give a modern, premium layer effect.

---

## 📬 SMTP Email Integration Flow

Emails are sent using a **pure PHP socket mailer** (`mailer.php`), avoiding external package bloat. It connects directly to Gmail servers over SSL/TLS:

1. **Subevent Handler**: When a user registers a lead or schedules a demo on the website, `submit_inquiry.php` registers the record in the database.
2. **Mail Generation**: An HTML email layout is compiled with the customer's request specs, name, and sequential Client ID.
3. **Socket Handshake**:
   - Establishes a TCP socket to `smtp.gmail.com` on port `587`.
   - Sends `EHLO` and upgrades the socket connection to TLS using `STARTTLS`.
   - Re-handshakes over the encrypted socket and authenticates via base64 encoded login credentials (`AUTH LOGIN`).
   - Inputs envelope directives (`MAIL FROM` and `RCPT TO`), transmits the HTML headers and message payload, and issues `QUIT`.
4. **Offline Resilience**: The mailing routine is suppressed with `@` operators; if Gmail blocks the connection or the server lacks internet access, the database transaction still saves successfully and returns a local fallback confirmation to the browser.

---

## ⚙️ Installation & Local Setup

Please see [SETUP_LOCAL_ENVIRONMENT.md](file:///d:/xampp/htdocs/PRODUCT_DEVELOPEMENT/SETUP_LOCAL_ENVIRONMENT.md) for full setup instructions.

1. Copy the directory into your XAMPP Apache root folder: `D:\xampp\htdocs\PRODUCT_DEVELOPEMENT`.
2. Start the **Apache** server from the XAMPP Control Panel.
3. Open your browser and navigate to:
   ```
   http://localhost/PRODUCT_DEVELOPEMENT/index.html
   ```
4. Access the CRM Portal:
   * URL: `http://localhost/PRODUCT_DEVELOPEMENT/admin-login.php`
   * Username: `admin`
   * Password: `AdminSecure2026!`
