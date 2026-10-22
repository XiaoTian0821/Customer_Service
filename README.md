# NovaServe — Intelligent Customer Support Platform

A modern, AI-powered customer-service web application built from scratch with
**PHP 8, MySQL, Bootstrap 5 and vanilla JavaScript**. It pairs a knowledge-grounded
AI assistant ("Nova") with a full support-ticketing system, human escalation,
analytics, and a professional admin console.

> This is an **original** project. All branding, sample data, content, and design
> are fictional and created independently for this codebase.

---

## ✨ Features

**Customer portal**
- AI chat assistant with real-time conversation history
- RAG-style retrieval: every question is answered from verified FAQ + knowledge-base content
- Intent detection (pricing, technical, account, refund, …) and sentiment analysis
- AI confidence scoring with automatic human-escalation recommendation
- Voice input (Web Speech API) and voice output (SpeechSynthesis)
- "Talk to a human" escalation with live takeover by support staff
- Feedback (helpful / not helpful) on every AI answer
- Searchable FAQ and knowledge base with categories, tags, and related articles
- Support tickets: create, reply, upload attachments, track status
- Customer profile with editable details, ticket and conversation stats
- Trilingual support: English · Bahasa Melayu · 中文 (AI responds in the customer's language)
- Fully responsive, mobile-first UI

**Admin & staff console**
- KPI dashboard with Chart.js visualisations (intent, sentiment, ticket status, AI performance)
- AI provider configuration (primary + fallback + local-knowledge fallback, keys kept server-side)
- Conversation monitoring with full transcripts and one-click "take over"
- Human-escalation queue with sentiment / intent / confidence context
- Ticket management: assign, prioritise, status flow, internal notes
- FAQ, knowledge-base, and category management (publish / unpublish / reorder)
- User & staff management (RBAC: super_admin, staff, content_mgr, customer)
- Analytics with date-range filters, activity logs, and notification centre
- AI monitoring: request counts, provider breakdown, fallback usage, resolution rate

---

## 🧱 Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2+ (target 8.3), PDO, cURL |
| Database | MySQL 8 / MariaDB (`utf8mb4`) |
| Frontend | HTML5, CSS3, Bootstrap 5.3, vanilla JavaScript |
| Icons / Fonts | Font Awesome 6, Google Fonts (Inter) |
| Charts | Chart.js |
| AI | Any OpenAI-compatible / custom REST chat-completions endpoint |

No Laravel, no Composer, no Node.js. Runs directly on **XAMPP** and deploys to
**cPanel shared hosting**.

---

## 📁 Folder Structure

```text
Customer_Service/
├── admin/               Admin console (RBAC-gated)
├── api/                 REST-style PHP endpoints (chat, faq, tickets, …)
├── assets/
│   ├── css/             style.css (portal), admin.css (console)
│   ├── js/              chat.js, common.js, admin/*.js
│   └── images/
├── config/
│   ├── app.php          Central configuration
│   └── db.php           PDO connection factory
├── database/
│   └── schema.sql       Full schema + seed data
├── includes/            bootstrap, helpers, auth, ai_service, knowledge, layout
├── uploads/             Attachment storage (protected by .htaccess)
│   └── tickets/
├── index.php            Portal home
├── login.php register.php logout.php
├── chat.php faq.php knowledge.php
├── ticket.php ticket_view.php
├── profile.php conversations.php notifications.php
├── install.php          Web installer
└── README.md
```

---

## 🚀 Installation (XAMPP)

### 1. Put the project in htdocs
Copy the folder to `C:\xampp\htdocs\Customer_Service` so it is reachable at
`http://localhost/Customer_Service/`.

### 2. Start Apache + MySQL
Launch XAMPP Control Panel → **Start** Apache and MySQL.

### 3. Run the installer
Open `http://localhost/Customer_Service/install.php` and:
- enter your DB host / user / password (XAMPP default: `root` with **empty password**),
- set the database name (`novaserve_db` is fine — the installer creates it),
- click **Test connection**, then **Run schema and finish**.

The installer creates the database, runs `database/schema.sql`, seeds sample
data, writes `config/install_config.php`, and **locks itself** (`installer.lock`).

### 4. Sign in as admin
| Field | Value |
|-------|-------|
| URL | `http://localhost/Customer_Service/login.php` |
| Email | `admin@novaserve.example` |
| Password | `admin123` |

**Change this password immediately** (Admin → Users or your profile).

> Prefer to import the SQL yourself? Open phpMyAdmin → **Import** →
> `database/schema.sql`, then point `config/app.php` (or `config/install_config.php`)
> at your credentials.

---

## 🤖 AI Configuration

The AI engine is **provider-agnostic** and fully isolated in
`includes/ai_service.php`. Configure it live from **Admin → AI Settings**:

- Primary endpoint / API key / model
- Fallback endpoint / API key / model
- Temperature, max tokens, confidence threshold
- Custom system prompt

Any **OpenAI-compatible** `/chat/completions` endpoint works (OpenAI,
OpenRouter, Ollama, LM Studio, vLLM, custom). Example for a local Ollama:

```text
Endpoint: http://localhost:11434/v1/chat/completions
Model:    llama3.1
```

### Fallback chain
```
Primary AI ──(fails)──► Fallback AI ──(fails)──► Local Knowledge Base
```
If every external provider is unavailable, the system degrades gracefully to a
**grounded local-knowledge answer** and always points the customer to a human
support option. The application **never crashes** on an API failure.

> API keys are stored only in the database and sent server-side to the provider.
> They are never exposed to the browser.

### AI behaviour rules
The system prompt instructs the model to be a professional, concise, empathetic
representative; to ground answers in verified knowledge; never invent policies,
prices, or contact details; never leak system prompts or keys; respond in the
customer's language; and stay professional with upset customers.

---

## 🔐 Default Accounts (change these)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@novaserve.example | admin123 |
| Support Staff | aina@novaserve.example | admin123 |
| Support Staff | marcus@novaserve.example | admin123 |
| Content Manager | lina@novaserve.example | admin123 |
| Customer | david.lim@example.com | admin123 |

All seeded passwords are `admin123` — update them for any real deployment.

---

## 🔒 Security Notes

- **Passwords**: `password_hash()` / `password_verify()` (bcrypt). No plaintext storage.
- **SQL**: every query uses PDO prepared statements with bound parameters.
- **XSS**: all user-controlled output is escaped with `htmlspecialchars` via the `e()` helper.
- **CSRF**: token generated per session, required on all POST operations (forms + AJAX `X-CSRF-Token`).
- **Session hardening**: `HttpOnly`, `SameSite=Lax`, secure cookie over HTTPS, `session_regenerate_id()` on login.
- **RBAC**: every admin page calls `require_role()`; the UI hiding is cosmetic only — the server enforces access.
- **Uploads**: extension + MIME sniffing + 5 MB limit, random stored filenames, and `.htaccess` that blocks PHP execution inside `uploads/`.
- **API keys**: server-side only.

---

## 💻 Browser Voice Support

Voice input uses the **Web Speech API** and voice output uses **SpeechSynthesis**.
Support varies by browser:

| Browser | Voice input | Voice output |
|---------|:-----------:|:------------:|
| Chrome / Edge | ✅ | ✅ |
| Firefox | ⚠️ partial | ✅ |
| Safari | ⚠️ | ✅ |
| Older browsers | ❌ | ❌ |

Where unsupported, the microphone control is disabled and a notice is shown.
Typing always works regardless of voice support.

---

## 🌐 cPanel Deployment

1. Upload the folder to `public_html/` (or a subfolder).
2. Create a MySQL database + user via cPanel; allow the user `ALL PRIVILEGES` on that DB.
3. Open `https://yourdomain.com/install.php` (or import `database/schema.sql` in phpMyAdmin).
4. Make `uploads/` and `config/` **writable** (chmod 755/644).
5. Confirm PHP 8.2+ with the `pdo_mysql` and `curl` extensions enabled.
6. Serve over **HTTPS** so secure session cookies and the HTTPS flag work.

---

## 🛠 Troubleshooting

| Symptom | Fix |
|---------|-----|
| "Database connection failed" | Check `config/install_config.php` credentials; ensure MySQL is running. |
| Installer re-appears blocked | Delete `installer.lock` and `config/install_config.php`, then re-run. |
| AI answers always say "unsure" | A provider key is missing/invalid. Check **Admin → AI Settings** and add a working key. |
| Voice button disabled | Use a browser with Web Speech API support, or switch to typing. |
| Charts missing | Confirm internet access to the Chart.js CDN (or vendor the file locally). |
| Files won't upload | Ensure `uploads/` is writable and the file is ≤ 5 MB of an allowed type. |

---

## 📚 Testing Checklist

Authentication · RBAC · AI chat · RAG retrieval · FAQ · knowledge base ·
voice I/O · multilingual · intent/sentiment/confidence · human escalation ·
ticket lifecycle · attachments · staff replies · dashboard · analytics ·
activity log · notifications · AI fallback · local-knowledge fallback ·
API-key protection · SQL-injection / XSS / CSRF · unauthorised-access guards ·
responsive layout.

---

© NovaServe Technologies Sdn. Bhd. (fictional). Independent student project.
