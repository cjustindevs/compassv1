Project Overview
COMPASS (Competency Oversight, Monitoring, Peer Assistance, Support, and Supervision) is a web-based peer support platform developed for Project Dial-A-Friend at Divine Word College of Calapan. It connects students (help seekers) with trained psychology student volunteers (helpers) in a secure, anonymous, and confidential environment.

The system streamlines the entire peer support process—from anonymous registration and pre-session screening to helper matching, real-time chat/voice sessions, post-session evaluation, and administrative monitoring. It replaces the program's manual, fragmented workflow with a centralized, database-driven platform that enhances service coordination, competency tracking, referral management, and institutional reporting.

Target Users
User Role	Description
Help Seeker	Students seeking anonymous emotional support
Helper	Trained psychology student volunteers providing peer support
Moderator	Program coordinators managing daily operations
Adviser	Faculty supervisors overseeing helpers and reviewing sessions
Psychology Professional	Licensed professionals managing referrals
System Administrator	Technical administrators maintaining the platform
Key Features
For Help Seekers

Pseudonymous registration with OTP email verification

3-step request support flow (screening, preferences, matching)

Real-time risk classification based on screening responses

Secure chat and voice sessions with recording consent

Post-session evaluation and session history

Self-help resources (guided exercises, articles, tools)

Notification system for session updates and reminders

For Helpers (In Development)

Session management and availability settings

Competency monitoring with adviser feedback

Session documentation and reflection submission

For Advisers (In Development)

Helper supervision and competency evaluation dashboards

Referral review and approval workflow

Performance analytics and program reporting

System-Wide

Role-based access control with fine-grained permissions

Identity Vault for secure storage of personally identifiable information

Audit logging for all system activities

Compliance with Data Privacy Act of 2012 (RA 10173)

Technology Stack
Category	Technology
Backend Framework	Laravel 12
Backend Language	PHP 8.3+
Database	PostgreSQL 17
Frontend	Laravel Blade, Tailwind CSS, JavaScript
Assets	Vite
Authentication	Laravel Breeze (JWT-based)
Icons	Font Awesome 6
Typography	Google Fonts (Inter)
Version Control	Git, GitHub
Hosting	DigitalOcean VPS
Database Schema
The system uses 25+ relational tables organized into the following modules:

Users and Roles (users, help_seekers, helpers, advisers, moderators, professionals, admins)

Sessions and Queue (sessions, queue_requests, concern_categories)

Communication (messages, call_logs)

Consent and Identity (consent_records, identity_vault)

Competency and Feedback (helper_competency_history, seeker_evaluations, adviser_feedback)

Referrals (referrals, session_reports, incident_reports)

Resources (self_help_resources, emergency_resources, faqs)

System (notifications, analytics_reports, audit_logs)

System Architecture
The application follows a three-tier architecture:

Presentation Layer: Responsive Blade views with role-specific dashboards

Application Layer: Business logic including helper matching, queue management, risk classification, and competency monitoring

Data Layer: PostgreSQL database with Identity Vault for PII isolation

Key security components include HTTPS encryption, bcrypt password hashing, CSRF protection, prepared statements, and role-based access control.

Current Development Status
Module	Status
Authentication (OTP Registration, Login)	Complete
Landing Page	Complete
Seeker Dashboard	Complete
Request Support Flow	Complete
Active Session (Chat/Voice)	Complete
Post-Session Evaluation	Complete
Session History	Complete
Self-Help Tools	In Progress
Notifications	In Progress
Profile & Settings	In Progress
Helper Module	Planned
Adviser Module	Planned
Installation
Prerequisites
PHP 8.3+

Composer

PostgreSQL 17+

Node.js 18+

NPM

Quick Setup
bash
git clone https://github.com/your-username/compass.git
cd compass
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configure database in .env
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
Environment Configuration
env
APP_NAME=COMPASS
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=compass_db
DB_USERNAME=compass_user
DB_PASSWORD=your_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="COMPASS Support"
Development Commands
bash
php artisan serve                 # Start Laravel server
npm run dev                       # Start Vite development server
npm run build                     # Build assets for production
php artisan migrate                # Run database migrations
php artisan db:seed                # Seed database with sample data
php artisan test                   # Run tests
php artisan optimize               # Optimize for production
Project Structure
text
COMPASS/
├── app/
│   ├── Http/Controllers/          # Controllers
│   ├── Models/                    # Eloquent models
│   └── Providers/                 # Service providers
├── database/
│   ├── migrations/                # Database migrations
│   └── seeders/                   # Database seeders
├── resources/
│   └── views/                     # Blade templates
│       ├── landing/               # Landing page
│       ├── auth/                  # Authentication views
│       ├── dashboard/             # Role-specific dashboards
│       ├── request/               # Request support flow
│       ├── session/               # Session views
│       ├── selfhelp/              # Self-help resources
│       ├── notifications/         # Notification center
│       ├── profile/               # User profile
│       └── settings/              # Settings pages
├── routes/
│   ├── web.php                    # Web routes
│   └── api.php                    # API routes
├── public/
│   ├── manifest.json              # PWA manifest
│   └── serviceworker.js           # Service worker
└── .env.example                   # Environment template
Contributors
Name	Role
Cuenza, Lorraine Anne O.	Developer
Dancel, Lean Margaret L.	Scrum Master
Faello, Aiken M.	Developer
Lumanglas, Carl Justin B.	Lead Developer
Institution
Divine Word College of Calapan
Gov. Infantado St., Calapan City, Oriental Mindoro
