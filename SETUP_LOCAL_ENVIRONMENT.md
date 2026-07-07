# Local Development Environment Setup

## Prerequisites
You need to set up PHP to run this application locally. The application uses an embedded SQLite database (`ai_solution_db.sqlite`), so **no MySQL database setup or imports are required**.

### XAMPP Setup (Recommended)
1. **Start XAMPP Control Panel**:
   - Open the XAMPP Control Panel (located at `D:\xampp\xampp-control.exe`).
   - Click **"Start"** next to **Apache**.
   - *(Note: MySQL does not need to be started since we use SQLite).*
2. **Access in browser**:
   - Open your browser and navigate to:
     ```
     http://localhost/PRODUCT_DEVELOPEMENT/index.html
     ```

> [!WARNING]
> Do NOT use Vite's dev server (`http://localhost:5173/`) to access the site. Vite is a node-based development server and does not execute PHP scripts. Accessing files through port 5173 will display raw PHP code and break database operations (like loading products, sending forms, and admin logins).

---

## Database Setup & Initialization
The database initializes itself automatically! When you access the site via Apache for the first time, `db_connect.php` will automatically create the SQLite database file (`ai_solution_db.sqlite`), set up the tables, and seed them with default products, reviews, and admin credentials.

If you ever need to reset or cleanly re-install the database, you can run the setup wizard in your browser:
```
http://localhost/PRODUCT_DEVELOPEMENT/setup_database.php
```

---

## Testing the Setup

### 1. Access the Admin Portal
Open in your browser:
```
http://localhost/PRODUCT_DEVELOPEMENT/admin-login.php
```
Log in with the default admin credentials:
- **Username**: `admin`
- **Password**: `AdminSecure2026!`

---

## Common Issues

### Issue: "admin-login.php shows raw PHP code"
- You are accessing the website using Vite's development server (usually port 5173, e.g. `http://localhost:5173/admin-login.php`).
- **Fix**: Stop using port 5173 and access the application via Apache at `http://localhost/PRODUCT_DEVELOPEMENT/admin-login.php`.

### Issue: "Database is not writable"
- Ensure that the directory `D:\xampp\htdocs\PRODUCT_DEVELOPEMENT` is writable by Apache so it can create and write to the SQLite database file.

