# 🌿 WANNASNI - Senior Care Platform

A calm, intuitive web application designed for **seniors and caregivers**, combining service requests, health tracking, nutrition management, and social activities in one trusted digital companion.

![Symfony](https://img.shields.io/badge/Symfony-7.x-000000?style=flat&logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)
![License](https://img.shields.io/badge/License-MIT-green)

---

## ✨ Features

### 🛎️ Service Requests
- Request transport, home care, grocery shopping, companionship
- Track request status (pending, in progress, completed)
- Quick service cards for easy access

### ❤️ Health Tracking
- Log blood pressure, heart rate, weight
- Mood tracking with emoji selector
- Health history with visual cards

### 🥗 Nutrition Management
- Daily meal tracking (breakfast, lunch, snack, dinner)
- Water intake counter (glasses tracker)
- Calorie monitoring

### 🎯 Activities
- Browse and join social activities
- Physical, cognitive, creative, and social categories
- Activity enrollment tracking

### 👴 Senior-Friendly Design
- Large fonts (18px+ body, 24px+ headings)
- High contrast colors
- 48px+ touch targets
- Floating help button on all pages
- Minimal cognitive load

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Symfony CLI
- Node.js (for assets)

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/wannasni.git
cd wannasni

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Start the server
symfony serve
```

### Access the Application
- **Home**: http://127.0.0.1:8000/fr/
- **Dashboard**: http://127.0.0.1:8000/fr/dashboard
- **Admin Panel**: http://127.0.0.1:8000/admin/

---

## 📁 Project Structure

```
my_project/
├── src/
│   ├── Controller/
│   │   ├── Admin/          # Admin panel controllers
│   │   ├── Front/          # Front office controllers
│   │   └── Api/            # API controllers
│   ├── Entity/             # Doctrine entities
│   └── Service/            # Business logic services
├── templates/
│   ├── admin/              # Admin panel templates
│   ├── front/              # User-facing templates
│   │   ├── dashboard/
│   │   ├── services/
│   │   ├── activities/
│   │   ├── health/
│   │   └── nutrition/
│   ├── base.html.twig          # Public base template
│   └── base_dashboard.html.twig # Dashboard base template
├── config/                 # Symfony configuration
├── public/                 # Public assets
└── translations/           # i18n files (fr, en, ar)
```

---

## 🌍 Multilingual Support

The application supports three languages:
- 🇫🇷 **French** (default)
- 🇬🇧 **English**
- 🇸🇦 **Arabic** (RTL support)

---

## 🎨 Design Philosophy

> "Calmness, Confidence, and Clarity"

- **Emotional Reassurance**: Warm colors, friendly language
- **Help Always Available**: Floating red help button
- **Clear Actions**: One task per screen
- **Trust Indicators**: Badges and certifications displayed

---

## 👥 User Roles

| Role | Access |
|------|--------|
| **Senior** | Dashboard, Services, Health, Nutrition, Activities |
| **Caregiver** | Same as Senior + family linking |
| **Admin** | Full admin panel access |

---

## 🔧 Admin Panel

Access at `/admin/` with features:
- User management
- Service requests management
- Activity management
- Health records overview
- Nutrition plans management

---

## 📝 License

This project is licensed under the MIT License.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📞 Contact

- **Email**: wannasni@gmail.com
- **Phone**: +216 12 234 45

---

Made with ❤️ for seniors and their families
