# 🔍 BCIT Lost & Found

**AI-Powered Campus Lost & Found Management System**

An intelligent application that helps BCIT students report found items and search for lost items using AI-powered chat and image analysis.



---

## 📦 Setup & Installation

### Prerequisites
- PHP 8.5+ with Composer
- Node.js 18+ with npm
- Git
- Cloudinary Account (for image uploads)

### Backend Setup

```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate
```

**⚠️ Important**: Add your Cloudinary API credentials to `.env`:
```
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

**Create database file:**

**For Mac/Linux:**
```bash
touch database/database.sqlite
```

**For Windows (CMD):**
```cmd
type nul > database/database.sqlite
```

**For Windows (PowerShell):**
```powershell
New-Item -Path database/database.sqlite -ItemType File
```

**Then continue with:**
```bash
php artisan migrate --seed
npm run build
php artisan serve
```

### Frontend Setup

```bash
cd frontend
npm install
cp .env.example .env
```

**Configure backend API URL in `.env`:**

**For Local Development:**
```
VITE_API_URL=http://127.0.0.1:8000
```

**For Deployed Environment:**
```
VITE_API_URL=https://lost-and-found.azurewebsites.net
```

**Then start the app:**
```bash
npm run dev
```

⚠️ **Start backend FIRST, then frontend**

---

## 🌐 Application URLs

### Admin Portal
| Environment | URL |
|---|---|
| **Local** | http://127.0.0.1:8000 |
| **Deployed** | https://lost-and-found.azurewebsites.net/ |
| **Email** | admin@bcit.ca |
| **Password** | password |

### Student Portal
| Environment | URL |
|---|---|
| **Local** | http://localhost:5173 |
| **Deployed** | https://red-mud-0883f360f.7.azurestaticapps.net/ |
| **Email** | a@bcit.ca |
| **Password** | password |

### Additional Test Account
- Email: `b@bcit.ca` | Password: `password` (same credentials for both local & deployed)

---

## 📁 Project Structure

```
comp3975project/
├── backend/ (Laravel)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── AuthController.php       # Login/Register
│   │   │   ├── ItemController.php       # Item CRUD + Cloudinary
│   │   │   ├── AIChatController.php     # AI search
│   │   │   └── ReturnLogController.php  # Return tracking
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Item.php
│   │   │   └── ReturnLog.php
│   │   └── Services/
│   │       └── GithubModelsService.php  # AI color analysis
│   ├── routes/
│   │   └── api.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── startup.sh                       # Azure deployment
│
└── frontend/ (React + TypeScript)
    ├── src/
    │   ├── pages/
    │   │   ├── SignInPage.tsx
    │   │   ├── SignUpPage.tsx
    │   │   ├── MainPage.tsx             # AI search
    │   │   ├── AddItemPage.tsx          # Report item
    │   │   ├── ItemsListPage.tsx
    │   │   ├── MyAddedItemsPage.tsx
    │   │   └── MyClaimsPage.tsx
    │   ├── components/
    │   │   ├── AiChatBox.tsx
    │   │   ├── ItemCard.tsx
    │   │   ├── ClaimItemModal.tsx
    │   │   └── Header.tsx
    │   └── types/
    │       └── ai.ts
    └── vite.config.ts
```

---

## ✨ Features

### 👥 Student Features
- **Authentication**: Register with BCIT Student ID (A00000000 format), Login/Logout
- **Report Items**: Upload found items with details (name, category, color, brand, location) and photos
- **AI Search**: Chat-based natural language search to find items using AI
- **AI Matching**: Matches items using image similarity and natural language input
- **Claim Items**: Claim lost items (changes status from active → claim pending)
- **My Items**: View items you've added and items you've claimed

### 🔧 Admin Features
- **Item Management**: Update items, handle pending items (set as active)
- **Claim Processing**: Approve claims (mark as returned) or reject claims (revert to active)
- **Return Logs**: View all return transactions

---

## ⚙️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 18, TypeScript, Vite, Bootstrap 5 |
| **Backend** | Laravel 13, PHP 8.5.1, SQLite |
| **Images** | Cloudinary CDN (max 100MB) |
| **AI** | GitHub Models API (Gpt-4o-mini) |
| **Auth** | Laravel Sanctum (Token-based) |
| **Server** | Nginx + PHP-FPM (Azure App Service) |

---

## ⚠️ Limitations & Notes

### File Upload Limits
- **Maximum image size**: 15MB per file (configured in PHP-FPM)
- **Cloudinary**: Supports up to 100MB, but we limit to 15MB
- **Format**: JPEG, PNG, GIF, WebP

### Cloudinary Quota
- **Free Plan**: 10GB storage, 25 monthly credits
- **Upgrade**: Switch to Plus ($89/month) if more storage needed

### Student ID Validation
- Must follow format: `A` + 8 digits (e.g., `A00000001`)
- Unique per student account

### AI Chat Features
- Uses natural language processing (Gpt-4o-mini)
- Extracts: category, color, location, keywords
- Suggests follow-up questions if needed

### Item Status Flow
```
pending → active → claim pending → returned
```
- Admin must approve claims before marking as returned

### Database
- Uses **SQLite** for local development
- Production uses **Azure SQL Server**
- Run migrations manually using: php artisan migrate

### CORS & Security
- API calls require valid Sanctum token (stored in localStorage)
- CORS enabled for frontend origin
- Password hashing: bcrypt

### Known Issues Fixed
- ✅ 422 Upload Error (files > 2MB): Resolved via PHP-FPM pool config
- ✅ Image upload to Cloudinary: Working with 15MB limit
- ✅ CORS issues: Middleware properly configured

---

**Last Updated**: April 16, 2026 | **Status**: ✅ Production Ready