# COMPASS — Peer Support & Guidance System

> **A secure, supervised, and accessible digital platform for student peer support.**

**COMPASS** (*Competency Oversight, Monitoring, Peer Assistance, Support, and Supervision*) is a web-based peer support platform developed for **Project Dial-A-Friend at Divine Word College of Calapan**.

The system connects students seeking support with trained psychology student volunteers through a centralized platform for **support requests, sessions, messaging, self-help resources, referrals, notifications, and administrative monitoring**.

---

## ✦ Features

- **Anonymous Help-Seeking** — Request peer support while maintaining privacy.
- **Peer Helper Management** — Manage helper availability and assignments.
- **Support Sessions** — Organize and track peer-support sessions.
- **Messaging** — Facilitate communication within support sessions.
- **Self-Help Resources** — Access, save, and track educational resources.
- **Notifications** — Receive updates about requests, sessions, and activities.
- **Referral Coordination** — Escalate cases beyond the scope of peer support.
- **Competency Monitoring** — Support helper evaluation and supervision.
- **Administrative Monitoring** — Monitor users, sessions, and system activities.

---

## 👥 User Roles

| Role | Responsibilities |
|:---|:---|
| **Help Seeker** | Requests support, joins sessions, and accesses resources. |
| **Helper** | Provides peer support and manages availability. |
| **Administrator / Adviser** | Oversees users, sessions, helpers, and system activities. |
| **Psychology Professional** | Reviews and handles cases requiring professional intervention. |

---

## 🛠️ Technology Stack

| Category | Technologies |
|:---|:---|
| **Backend** | PHP, Laravel 12, Composer |
| **Frontend** | Blade, HTML5, CSS3, JavaScript, Vite |
| **Database** | MySQL / MariaDB |
| **Development** | Git, GitHub, VS Code, XAMPP / Laragon |

---

## 📁 Project Structure

```text
Compassv1/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   └── Models/
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── resources/
│   └── views/
│
├── routes/
├── public/
├── tests/
├── composer.json
└── package.json
