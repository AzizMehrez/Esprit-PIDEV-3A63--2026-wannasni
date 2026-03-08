# 🌿 WANNASNI - Senior Care Platform

A calm, intuitive web application designed for **seniors and caregivers**, combining service requests, health tracking, nutrition management, social activities, AI-powered chat, ML food detection, medication management, subscriptions, loyalty programs, and content moderation in one trusted digital companion.

> *"WANNASNI — Soins, sécurité et vie quotidienne, le tout dans une seule application."*
> (Care, safety, and daily life — all in one app.)

![Symfony](https://img.shields.io/badge/Symfony-7.x-000000?style=flat&logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)
![Python](https://img.shields.io/badge/Python-3.10+-3776AB?style=flat&logo=python)
![TensorFlow](https://img.shields.io/badge/TensorFlow-Keras-FF6F00?style=flat&logo=tensorflow)
![Stripe](https://img.shields.io/badge/Stripe-Payments-635BFF?style=flat&logo=stripe)
![License](https://img.shields.io/badge/License-MIT-green)

---

## ✨ Features Overview

| Module | Description |
|--------|-------------|
| 🛎️ Service Requests | Transport, home care, groceries, companionship with urgency levels |
| ❤️ Health Tracking | Vitals, mood, sleep, pain level, treatments & prescriptions |
| 🥗 Nutrition Management | ML food detection, diet compliance, barcode scanning, AI coach |
| 🍵 Beverage Sommelier | 200+ beverages, personalized recommendations, marketplace |
| 🎯 Activities | Physical, cognitive, creative & social activities with enrollment |
| 💊 Medicament Alternatives | KNN drug recommendations, OCR image analysis for medications |
| 🤖 AI Chat Assistant | LLM-powered chat, voice calls, DB queries, web search, file analysis |
| 👥 Social Networking | Posts, reels, comments, likes, friend system, direct messaging |
| 🔐 Authentication | Form login, OAuth (Google/GitHub/X), face recognition, CAPTCHA |
| 💳 Subscriptions & Payments | 3 plans via Stripe, feature gating, invoice PDF generation |
| ⭐ Loyalty Program | Points system, ML reward prediction, tier-based rewards |
| 🛡️ Content Moderation | AI image moderation (NSFW/violence) + multilingual text toxicity |
| 🗺️ Map & Location | Interactive maps, location-based service filtering |

---

## 🧠 Technology Stack

| Layer | Technology |
|-------|------------|
| **Backend Framework** | Symfony 7.x / PHP 8.2+ |
| **Database** | MySQL (`wannasni`) / PostgreSQL 16 (Docker dev) |
| **Frontend** | Twig templates, Stimulus controllers, AssetMapper, Bootstrap |
| **ML/AI Python Services** | FastAPI (port 8001 nutrition, port 8090 medicaments) |
| **Deep Learning** | TensorFlow/Keras (MobileNetV2, CNN food classifier — 19 classes, 94.74% accuracy) |
| **Computer Vision** | OpenCV (face detection via Haar cascades), CLIP (ViT-B/32) |
| **LLM / Generative AI** | Google Gemini API (`gemini-1.5-flash`), OpenRouter (`meta-llama/llama-3.1-8b-instruct:free`) |
| **OCR** | EasyOCR (FR/EN) for medicament image text extraction |
| **NLP Moderation** | `xlm-roberta` multilingual toxic classifier + CLIP zero-shot + heuristic fallback |
| **PDF Generation** | Dompdf (invoices, quotes, exports) |
| **Payments** | Stripe (subscriptions + intervention payments) |
| **OAuth** | KnpU OAuth2Client — Google, GitHub, X/Twitter |
| **Email** | Gmail SMTP (Python) + Symfony Mailer |
| **Voice** | Web Speech API (recognition + synthesis), `pyttsx3` for Python TTS |
| **Containerization** | Docker Compose |
| **ML Libraries** | scikit-learn (KNN), Pillow, NumPy, EasyOCR |
| **i18n** | French, English, Arabic (RTL support) |

---

## 🛎️ Service Requests & Interventions

- **Service types**: Transport, home care (aide ménagère), groceries (courses), companionship
- **Urgency levels** with preferred date scheduling
- **Budget range**: Min/max budget specification
- **Admin assignment** workflow: pending → assigned → in progress → completed
- **Technician profiles**: Competences, hourly rates, service zones, working hours
- **Payment methods**: Cash, check, online (Stripe integration)
- **PDF generation**: Automated quotes (devis) and invoices via Dompdf
- **Email notifications**: Automated at each status change
- **Image analysis**: CLIP-based service problem classification (electricity, plumbing, groceries, transport, cleaning, companionship)

---

## ❤️ Health Tracking

### Health Journal
- **Vitals**: Blood pressure, heart rate (30–200 bpm), temperature (35–42°C)
- **Wellness**: Mood (excellent/good/average/poor), sleep quality, appetite
- **Pain tracking**: Pain level scale (0–10) with symptom logging
- **Activity log**: Physical activity, hydration levels, medications taken
- **Notes**: Free-text daily health notes

### Treatment Management
- **Prescription tracking**: Medications, dosage, frequency
- **Date management**: Start/end dates, renewal tracking
- **Instructions & side effects** documentation
- **Doctor-patient linking**: Treatments linked to senior and prescribing doctor
- **Admin CRUD** with export capabilities (PDF/Excel)

---

## 🥗 Nutrition Management

### ML Food Detection Pipeline (100% Local — No External API)
The most complex module, featuring a multi-layer detection system:

1. **Layer 1 — Similarity Matching**: MobileNetV2 deep learning (1280-D embeddings) with 929 indexed reference images and data augmentation
2. **Layer 2 — CNN Classifier**: Trained model with **19 food classes at 94.74% accuracy**
3. **Layer 3 — Color Signature Analysis**: Advanced color histograms for 6+ food types
4. **Layer 4 — Region-based Analysis**: Spatial detection for complex plates

### False Positive Filtering (3 Levels)
- **Level 1**: Source-aware thresholds with category-specific adaptive detection
- **Level 2**: Intelligent correction (incompatible pair removal, plausible combination checking, outlier filtering)
- **Level 3**: Strict filtering (MIN_CONFIDENCE=0.55, COUNT_THRESHOLD=4, AVG_CONFIDENCE=0.50)

### Additional Nutrition Features
- **Nutritional analysis**: Cooking correction factors, quantity validation, **Nutri-Score** calculation
- **Diet compliance checking** for 6 diet types: diabétique, hypo_sodé, sans_gluten, cardioprotecteur, perte_poids, prise_masse
- **USDA FoodData Central API** integration for external nutritional data
- **OpenFoodFacts barcode scanning** for product identification
- **MealDB API** for recipe suggestions
- **AI Nutrition Coach** via Google Gemini: Personalized dietary guidance
- **Weekly Reports** (RapportHebdomadaire): Compliance rates, problematic foods, AI-generated suggestions
- **Prescribed Diets** workflow: Request → Nutritionist review → Prescribed regime (3–6 meals/day)
- **Text-to-Speech**: Read nutrition information aloud via Web Speech API (642-line JS module)

### FastAPI Nutrition Service (Port 8001)
- Endpoints: `/step1-detect`, `/step2-nutrition`, `/step3-recipes`, `/step4-alerts`, `/full-analysis`
- UTF-8 safe wrappers for Windows compatibility

---

## 🍵 Beverage Sommelier & Marketplace

- **Personalized recommendations** based on meal type and diet regime
- **200+ built-in beverages**: Teas, coffees, infusions, juices, smoothies with health benefits
- **Photo analysis** of beverages via Google Gemini AI
- **Full e-commerce marketplace**: Product catalog, shopping cart, orders, checkout
- **Hydration tracking** and daily logging
- **Diet-aware suggestions**: Recommendations adapted to user's prescribed diet

---

## 🎯 Activities & Participation

- **Activity types**: Physical, cognitive, creative, social
- **Browse & search** activities with location filtering
- **Join/leave** with max participant limits and coach assignment
- **Participation history** with statistics dashboard
- **Feedback & rating** system for completed activities
- **Admin CRUD** with PDF export

### 🎙️ Voice-Controlled Activity Assistant
- **Multi-language voice interface**: English, French, Arabic, Tunisian dialect
- **Speech recognition** + `pyttsx3` TTS responses
- **Fuzzy matching** for natural language activity search
- **Direct MySQL integration** for real-time activity data (724-line Python script)

---

## 💊 Medicament Alternatives (ML-Powered)

- **KNN-based drug recommendation** engine (scikit-learn)
- **EasyOCR image analysis**: Photograph a medication → extract its name via OCR → find alternatives
- **Database of 50+ common medications** with:
  - Therapeutic class & active molecules
  - Price comparison
  - **Senior-friendliness scoring**
- **FastAPI service** on port 8090 with endpoints: `/predict`, `/analyze-image`, `/health`

---

## 🤖 AI Chat Assistant

- **LLM Backend**: OpenRouter proxy to `meta-llama/llama-3.1-8b-instruct:free`
- **Database querying**: AI-generated read-only SQL queries against the application database
- **Web search**: Wikipedia knowledge retrieval for health and nutrition questions
- **Image upload & analysis**: AI describes and analyzes uploaded images
- **File upload & analysis**: Document processing and summarization
- **Voice call mode**: Animated orb UI, real-time speech recognition (Web Speech API), AI TTS responses, full call controls (mute, speaker, end)
- **Chat export**: Download conversations as TXT, JSON, or PDF
- **Persona selector**: Switch between different AI personalities
- **Suggested prompts**: Context-aware quick action buttons
- **UI customization**: Theme toggle, background change

---

## 👥 Social Networking

### Posts & Media
- **Post types**: Text posts, videos, reels
- **Media upload** with AI content moderation
- **Comments & likes** system
- **Public user profiles**

### Connections & Messaging
- **Friend system**: Send/accept/reject connection invites, remove connections
- **Direct messaging**: Private conversations with real-time message exchange

### AI Content Moderation
- **Image moderation**: Dual-model system (Falconsai NSFW detector + CLIP zero-shot) for NSFW, violence, weapons, drugs, political content
- **Text moderation**: `xlm-roberta` multilingual toxic classifier + CLIP + heuristic fallback (EN/FR/AR) detecting toxicity, hate speech, harassment

### Profile Verification
- **AI-scored verification requests** analyzing:
  - Profile completeness
  - Account age
  - Post activity
  - Content quality
  - Social engagement
- Verification statuses: pending → ai_rejected / approved / rejected

---

## 🔐 Security & Authentication

- **Form login** with CSRF protection
- **Password hashing** (bcrypt/argon2)
- **User roles**: `ROLE_USER`, `ROLE_ADMIN`, `ROLE_CAREGIVER`, `ROLE_NUTRITIONNISTE`
- **OAuth social login**: Google, GitHub, X/Twitter (via KnpU OAuth2Client)
- **Face recognition login**: OpenCV Haar cascade face detection → histogram encoding → matching
- **Custom CAPTCHA**: Pillow-based image generation with distortion effects
- **Forgot password flow**: 6-digit email verification code
- **Custom security handlers**: `UserChecker`, `AjaxAuthenticationEntryPoint`, `LoginSuccessHandler`, `AccessDeniedHandler`

---

## 💳 Subscriptions & Payments

### Subscription Plans (Stripe Integration)

| Plan | Price | Discount | Key Features |
|------|-------|----------|--------------|
| **Essentiel** | €9.99/mo | 10% | Basic service discount |
| **Confort** | €19.99/mo | 20% | + Priority urgencies, PDF export |
| **Premium** | €29.99/mo | 30% | + AI detection, dedicated technician, online payment |

### Feature Gating
- **AI food detection**: Premium only
- **Priority urgencies**: Confort+
- **Dedicated technician**: Premium only
- **PDF export**: Confort+
- **Online payment**: Premium only

### Payments
- **Stripe checkout** for subscriptions (`stripeSubscriptionId`, `stripePriceId`)
- **Intervention payments**: Checkout → process → success → invoice PDF
- **Invoice generation**: Automated PDF via Dompdf

---

## ⭐ Loyalty Program

### Points System
| Action | Points |
|--------|--------|
| Intervention completed | 50 pts + complexity bonus |
| Subscription payment | 100 pts |
| Activity participation | 20 pts |

### Tier System
- 🥉 **Bronze** → 🥈 **Silver** → 🥇 **Gold** → 💎 **Platinum**

### ML-Powered Reward Prediction
- Personalized reward suggestions using ML model analyzing:
  - Total points, number of interventions, average cost
  - Subscription plan, account age, monthly point velocity
- Predicts reward type with confidence scoring

### Reward Catalog
- **Discount** vouchers
- **Free maintenance** sessions
- **Plan upgrade** offers
- Full redemption flow: Points → reward selection → confirmation

---

## 📧 Email System

- **Gmail SMTP** integration via Python (`send_email.py`)
- **Symfony Mailer** for PHP-side notifications
- **Email templates**: Verification codes, verification approved/rejected, admin welcome, intervention status notifications

---

## 🗺️ Map & Location

- **Interactive map view** for services and activities
- **Location-based filtering** for nearby activities and service providers
- **Zone-based availability** checking for interventions

---

## 👤 User Roles

| Role | Access |
|------|--------|
| **Senior** | Dashboard, Services, Health, Nutrition, Activities, Social, Chat, Loyalty |
| **Caregiver** | Same as Senior + family linking |
| **Nutritionniste** | Weekly nutrition reports, diet prescriptions |
| **Admin** | Full admin panel access + user verification review |

---

## 🔧 Admin Panel

Access at `/admin/` with features:
- **User management**: Full CRUD, verification review (AI report + manual approval), PDF/Excel export
- **Service requests management**: CRUD, assignment, PDF generation
- **Intervention management**: CRUD, technician assignment, payment tracking, PDF
- **Activity management**: CRUD, participation tracking, PDF export
- **Health records**: CRUD, vitals overview, export
- **Treatment management**: Prescription CRUD, PDF export
- **Nutrition management**: Diet requests, prescribed regimes, weekly reports
- **Loyalty program**: Points management, rewards catalog
- **Notification management**: System-wide notifications
- **Statistics dashboards**: Overview metrics across all modules

---

## 👴 Senior-Friendly Design

> "Calmness, Confidence, and Clarity"

- **Large fonts**: 18px+ body text, 24px+ headings
- **High contrast colors**: Soft blues, warm neutrals, gentle greens
- **48px+ touch targets**: Large, well-spaced buttons
- **Floating help button** on all pages
- **Minimal cognitive load**: One task per screen
- **Senior Mode toggle**: Even larger text, higher contrast, reduced motion
- **Soundless design**: All-visual feedback (glows, shakes, morphs)
- **Humanized AI**: "Smart Companion" naming with soft orb animation
- **Emotional reassurance**: Warm colors, friendly language, trust indicators

---

## 🌍 Multilingual Support

The application supports three languages:
- 🇫🇷 **French** (default) — 301+ translation keys
- 🇬🇧 **English**
- 🇸🇦 **Arabic** (Full RTL support)

Translation files: `messages.{fr,en,ar}.yaml` + `security.{fr,en,ar}.yaml`

All routes use locale prefix: `/{_locale}/...` where `_locale` = `fr|en|ar`

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Symfony CLI
- Node.js (for assets)
- Python 3.10+ (for ML services)
- MySQL or PostgreSQL

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/wannasni.git
cd wannasni

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build assets
npm run build

# Setup database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Start the Symfony server
symfony serve
```

### Start ML Services

```bash
# Nutrition ML Service (port 8001)
cd python
uvicorn app:app --host 0.0.0.0 --port 8001 --reload

# Medicament ML Service (port 8090)
cd ml_service
uvicorn main:app --host 0.0.0.0 --port 8090 --reload
```

Or use the batch launcher on Windows:
```bash
start_fastapi.bat
```

### Access the Application
- **Home**: http://127.0.0.1:8000/fr/
- **Dashboard**: http://127.0.0.1:8000/fr/dashboard
- **Admin Panel**: http://127.0.0.1:8000/admin/
- **Nutrition ML API**: http://127.0.0.1:8001/docs
- **Medicament ML API**: http://127.0.0.1:8090/docs

---

## 📁 Project Structure

```
my_project/
├── src/
│   ├── Controller/
│   │   ├── Admin/              # 12 admin panel controllers
│   │   ├── Front/              # 20+ user-facing controllers
│   │   ├── Api/                # 8 API controllers
│   │   └── BackOffice/         # Nutritionist report controllers
│   ├── Entity/                 # 34 Doctrine entities
│   └── Service/                # 34 business logic services
├── templates/
│   ├── admin/                  # Admin panel templates
│   ├── front/                  # User-facing templates
│   │   ├── dashboard/
│   │   ├── services/
│   │   ├── activities/
│   │   ├── health/
│   │   ├── nutrition/
│   │   ├── chat/
│   │   ├── social/
│   │   ├── sommelier/
│   │   ├── subscription/
│   │   ├── loyalty/
│   │   ├── medicament/
│   │   ├── messaging/
│   │   └── map/
│   ├── base.html.twig              # Public base template
│   ├── base_dashboard.html.twig    # Dashboard base template
│   └── base_auth.html.twig         # Auth pages base template
├── python/
│   ├── app.py                  # FastAPI nutrition service (port 8001)
│   └── ml/                     # 14 ML modules (food detection pipeline)
├── ml_service/
│   └── main.py                 # FastAPI medicament service (port 8090)
├── ml_model/
│   ├── food_classifier.h5      # CNN model (19 classes, 94.74% accuracy)
│   ├── loyalty_reward_predictor.py  # ML reward prediction
│   └── reference_images/       # 929 indexed food images
├── config/                     # Symfony configuration
├── migrations/                 # 10 Doctrine migrations
├── translations/               # i18n files (fr, en, ar)
├── assets/
│   ├── js/                     # Stimulus controllers + nutrition TTS module
│   └── styles/                 # CSS + design guidelines
├── scripts/
│   └── activity_assistant.py   # Voice-controlled activity assistant (724 lines)
├── public/                     # Public assets
├── compose.yaml                # Docker Compose (PostgreSQL 16)
├── compose.override.yaml       # Docker dev overrides
├── face_recognition_service.py # OpenCV face detection/encoding/matching
├── image_analyzer_clip.py      # CLIP zero-shot service classification
├── image_moderation_service.py # NSFW + violence AI moderation
├── text_moderation_service.py  # Multilingual toxicity detection
├── captcha_service.py          # Custom CAPTCHA generation
├── verification_analyzer.py    # AI profile verification scoring
├── service_image_analyzer.py   # CLIP service problem analysis
└── send_email.py               # Gmail SMTP email service
```

---

## 🧪 Python ML Services Detail

### Nutrition ML Service (`python/app.py` — Port 8001)

| Module | Technology | Function |
|--------|-----------|----------|
| `full_nutrition_analyzer.py` | TensorFlow + OpenCV | Main orchestrator: multi-layer food detection pipeline |
| `similarity_matcher.py` | MobileNetV2 | Deep learning similarity (1280-D embeddings, 929 images) |
| `food_detection_corrector.py` | Heuristics | Incompatible pair removal, plausible combination checking |
| `strict_false_positive_filter.py` | Statistical | Level 3 strict confidence filtering |
| `intelligent_dish_strategy.py` | Logic | Simple food vs. complete dish classification |
| `nutrition_knowledge.py` | Database | Comprehensive nutrition data + diet rules |
| `detection_debugger.py` | Logging | Debug and validation system |
| `train.py` / `train_smart.py` | TensorFlow | CNN model training scripts |

### Medicament ML Service (`ml_service/main.py` — Port 8090)

| Feature | Technology | Function |
|---------|-----------|----------|
| Drug alternatives | scikit-learn KNN | Find similar medications by class, molecule, price |
| Image OCR | EasyOCR (FR/EN) | Extract drug name from photograph |
| Senior scoring | Custom algorithm | Rate medication suitability for elderly patients |

### Other Python Services

| Script | Technology | Function |
|--------|-----------|----------|
| `face_recognition_service.py` | OpenCV | Face detect/encode/match for login |
| `image_moderation_service.py` | Falconsai + CLIP | NSFW, violence, weapons, drugs, political detection |
| `text_moderation_service.py` | xlm-roberta + CLIP | Multilingual toxicity/hate/harassment (EN/FR/AR) |
| `captcha_service.py` | Pillow | CAPTCHA with distortion effects |
| `verification_analyzer.py` | Scoring algorithm | AI profile verification scoring |
| `image_analyzer_clip.py` | CLIP ViT-B/32 | Service type classification from images |

---

## 🐛 Key Fixes & Improvements

| Issue | Resolution |
|-------|-----------|
| ML false positives in food detection | 3-level filtering system (source-aware → intelligent correction → strict filter) |
| Missing food categories (chocolate, etc.) | Intelligent dish strategy + category-specific adaptive thresholds |
| Windows UTF-8 / charmap crashes | UTF-8 safe wrappers in Python services + batch launcher |
| Gemini 403 API errors | Model switch from `gemini-2.5-flash` to `gemini-1.5-flash` |
| Chat API failures | Fixed OpenRouter model ID, DB query handler, user context loading |
| Voice call issues | Fixed overlay UI, mute/speaker controls, speech synthesis |
| Beverage AJAX errors | Fixed missing session credentials and CSRF token handling |
| Threshold mismatches | Aligned CONFIDENCE/WHOLE_DISH thresholds to 0.50 across all modules |
| JS initialization errors | Fixed `currentPhotoFile` initialization order in sommelier |
| Profile image issues | Fixed `imageProfil` field handling |

---

## 📊 Project Statistics

| Metric | Count |
|--------|-------|
| Doctrine Entities | 34 |
| PHP Controllers | 40+ |
| PHP Services | 34 |
| Python ML Modules | 14+ |
| Twig Templates | 137 |
| Routes | ~150 |
| Database Migrations | 10 |
| Translation Keys (FR) | 301+ |
| ML Reference Images | 929 |
| Food Classes (CNN) | 19 |
| CNN Accuracy | 94.74% |
| Medications in DB | 50+ |
| Beverages in Catalog | 200+ |

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
# WANNASNI - Senior Care Platform

## Overview
This project was developed as part of the PIDEV – 3rd Year Engineering Program at **Esprit School of Engineering – Tunisia** (Academic Year 2025–2026).
WANNASNI is a full-stack web platform for seniors and caregivers, combining health tracking, service requests, nutrition management, social interaction, AI assistance, and secure digital services.

## Features
- Service requests and interventions
- Health tracking
- Nutrition management
- Beverage recommendation and marketplace
- Activities and participation
- Medicament alternatives
- AI chat assistant
- Social networking
- Authentication and security
- Payments and subscriptions
- Loyalty program

## Tech Stack

### Frontend
- Twig
- JavaScript
- Bootstrap
- CSS
- HTML

### Backend
- Symfony
- PHP
- Python
- FastAPI
- MySQL / PostgreSQL

## Architecture
Describe the Symfony web application, Twig frontend, Python ML services, database layer, and integrations.

## Contributors
- Mohamed Aziz Mehrez
- Koussay Ben Khadher
- SYrine Ayadi
- Roua Baccour
- Mariem Slatni 

## Academic Context
Developed at **Esprit School of Engineering – Tunisia**  
PIDEV – 3A63 | 2025–2026

## Getting Started
Keep your current installation and launch steps here.

## Acknowledgments
Thanks to Esprit School of Engineering, supervisors, and project mentors.
