# 🗄️ AI Chatbot with Database Integration - Setup Guide

## 📋 Overview
Your AI chatbot (Nexus) can now query and control your XAMPP MySQL database! The AI can view tables, fetch data, and execute SQL commands.

---

## ⚙️ Setup Instructions

### 1. **Configure Database Connection**
Edit `db_config.php` and update the database name:

```php
define('DB_NAME', 'your_database');  // ⬅️ Change this to your actual database name
```

Leave the other settings as default for XAMPP:
- Host: `localhost`
- User: `root`
- Password: `` (empty)

### 2. **Place Files in XAMPP**
Make sure your chat folder is accessible via XAMPP:

**Option A:** If using `htdocs`
```
C:\xampp\htdocs\chat\
  ├── index.html
  ├── style.css
  ├── script.js
  ├── api.js
  ├── db_config.php
  └── db_api.php
```

**Option B:** If current location works
- Your files are at: `C:\Users\azizm\OneDrive\Desktop\chat\`
- Make sure XAMPP can access this path (create a virtual host or move to htdocs)

### 3. **Start XAMPP Services**
1. Open XAMPP Control Panel
2. Start **Apache** (for PHP)
3. Start **MySQL** (for database)

### 4. **Test Database Connection**
Visit: `http://localhost/chat/db_api.php` in your browser

You should see a JSON error (because it expects POST), which means PHP is working!

---

## 🎯 How to Use

### Example Queries You Can Ask Nexus:

#### 📊 **Explore Database**
- "What tables do I have?"
- "Show me the database schema"
- "What's in the users table?"

#### 🔍 **Query Data**
- "Show me all users"
- "Find users with ID less than 10"
- "Get the first 5 products"

#### ✏️ **Modify Data**
- "Add a new user named John with email john@example.com"
- "Update user ID 5 to have email newemail@test.com"
- "Delete the product with ID 3"

---

## 🛡️ Security Features

✅ **SQL Injection Protection**
- Input sanitization
- Query type validation (SELECT only for queries, INSERT/UPDATE/DELETE only for execute)

✅ **Action Separation**
- Read operations (SELECT) use `query` action
- Write operations (INSERT/UPDATE/DELETE) use `execute` action

⚠️ **Important Notes:**
- This is for **development/testing only**
- For production, add authentication and user permissions
- Never expose database credentials in frontend code

---

## 🔧 Configuration Options

### Adjust Database API Path
If your chat folder is in a different location, update `api.js`:

```javascript
this.dbApiUrl = "http://localhost/your-path/db_api.php";
```

### Limit Query Results
Edit `db_api.php` to change the default limit:

```php
$limit = $input['limit'] ?? 100;  // Change 100 to your preferred limit
```

---

## 🧪 Testing

1. Create a test database:
```sql
CREATE DATABASE test_db;
USE test_db;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100)
);

INSERT INTO users (name, email) VALUES 
    ('Alice', 'alice@example.com'),
    ('Bob', 'bob@example.com');
```

2. Update `db_config.php`:
```php
define('DB_NAME', 'test_db');
```

3. Ask Nexus:
- "Show me all users"
- "What's the structure of the users table?"

---

## 🎨 **What's New in the Chatbot**

### AI Capabilities
✨ **Database Actions** - AI can now:
- List all tables
- View table structures
- Query data with SQL
- Insert, update, delete records

### Response Format
The AI will:
1. Understand your natural language request
2. Generate appropriate database query
3. Execute the query via PHP backend
4. Format results in a friendly, readable way

---

## 📁 File Structure

```
chat/
├── index.html         # Main page
├── style.css         # Styles
├── script.js         # UI controller
├── api.js            # AI + Database integration
├── db_config.php     # Database connection settings
└── db_api.php        # Database API endpoints
```

---

## ❓ Troubleshooting

**Problem:** "Database connection failed"
- ✅ Check if MySQL is running in XAMPP
- ✅ Verify database name in `db_config.php`

**Problem:** "CORS error" or "Failed to fetch"
- ✅ Make sure Apache is running
- ✅ Check the `dbApiUrl` path in `api.js`

**Problem:** AI doesn't execute database queries
- ✅ Hard refresh browser (Ctrl+Shift+R)
- ✅ Check browser console for errors

---

## 🚀 You're All Set!

Open `http://localhost/chat/index.html` and start chatting with Nexus about your database! 🎉
