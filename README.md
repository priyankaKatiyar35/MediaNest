 <div align="center">

# MediaNest

**A self-hosted internal media library with AI-powered search, summarization, and document Q&A.**

For companies who want their training videos, event photos, and policy documents in one private place — with modern AI baked in.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![AI: Groq](https://img.shields.io/badge/AI-Groq-FF6F00?logo=openai&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

[Features](#features) · [Screenshots](#screenshots) · [Quick start](#quick-start) · [Tech stack](#tech-stack) · [Roadmap](#roadmap)

</div>

---

## The story

Most companies have their internal media scattered: training videos on YouTube (public), photos in someone's Google Drive, PDFs emailed back and forth. MediaNest brings it all into one private platform — with the AI features people now expect from modern tools.

Built from scratch in vanilla PHP and MySQL to be drop-in deployable on any cheap shared host or local XAMPP. No frameworks, no Composer, no build step.

---

## Features

### 🎥 Video Library
- Upload, categorize, and tag training videos
- Built-in player with **mid-video checkpoint quizzes**
- **Continue Watching** carousel (resumes where you left off)
- Auto-jumping to specific timestamps from AI search results

### 📸 Photo Galleries
- Event-based albums with cover images
- Lightbox viewer (PhotoSwipe)
- Special collection for hero/feature videos

### 📄 Document Library
- Nested folder structure
- **In-browser preview** for PDF, Word (.docx), Excel (.xlsx)
- Auto-fallback to download for unsupported formats

### 🤖 AI Features (powered by Groq's free tier)
- **Smart video search** — ask a question in plain English, jump to the exact second in any video where it's answered
- **One-click video summaries** — 3-sentence overview + key topics
- **AI-generated quizzes** — admin uploads a video, AI drafts checkpoint questions; admin reviews and saves
- **Document Q&A** — chat with any PDF, with page citations

### 👥 User Management
- Role-based access (admin / user)
- Admin invites users (no open registration)
- Password reset by admin
- Audit log of all admin actions

### 📊 Analytics
- Quiz performance per video, hardest questions, leaderboards
- Group/department breakdowns
- CSV export

### 🔔 Engagement
- **Notification bell** with real-time unread badge
- **Bookmarks** for videos, albums, and files
- Polished dark mode across all pages

### 🔒 Security
- All queries use prepared statements (zero SQL injection surface)
- CSRF tokens on every admin form
- File access gated through authenticated PHP endpoints
- `.htaccess` blocks direct browser access to upload folders

---

## Screenshots

> Add your screenshots in `screenshots/` and they'll show up here.

| Homepage | Video player with AI summary |
|---|---|
| ![Homepage](screenshots/01-homepage.png) | ![Player](screenshots/02-video-summary.png) |

| Admin Manage Content | Quiz Analytics |
|---|---|
| ![Admin](screenshots/03-admin-manage.png) | ![Analytics](screenshots/04-quiz-analytics.png) |

| Document Q&A | AI Quiz Generator |
|---|---|
| ![Doc Q&A](screenshots/05-document-qa.png) | ![Quiz Gen](screenshots/06-ai-quiz.png) |

---

## Quick start

### Prerequisites

- PHP 8.0+ with `curl`, `mysqli`, `zip` extensions
- MySQL 5.7+ or MariaDB 10.4+
- (Optional) FFmpeg — only for AI video transcription
- (Optional) Free [Groq API key](https://console.groq.com) — only for AI features

### 1. Get the code

```bash
git clone https://github.com/YOUR_USERNAME/medianest.git
cd medianest
```

Or download the ZIP and extract into your web root (`htdocs/`, `www/`, etc.)

### 2. Create the database

In phpMyAdmin or MySQL CLI:

```sql
CREATE DATABASE `s&p` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import every `.sql` file from the `sql/` folder in order:

```
schema.sql                  -- core tables
ai_migration.sql            -- AI transcripts & summaries
docqa_migration.sql         -- document Q&A cache
progress_migration.sql      -- Continue Watching
notif_migration.sql         -- notifications
bookmarks_migration.sql     -- bookmarks
```

### 3. Configure

Copy the example config and add your secrets:

```bash
cp admin/ai_config.example.php admin/ai_config.php
```

Edit `admin/ai_config.php` and paste your Groq API key.

Edit `admin/config.php` to match your DB credentials if they aren't `root` / `''`.

### 4. Permissions

```bash
chmod -R 755 admin/upload admin/gupload admin/gcatch admin/acatch admin/uploads
```

(On Windows / XAMPP this usually works out of the box.)

### 5. Create the first admin

```sql
INSERT INTO users (email, password_hash, full_name, role)
VALUES (
  'admin@yourcompany.com',
  '$2y$10$oRDSL.URd9Ocz/Xy1fHSLepGw/s5E9ce3sdryda72ZkGMqNm4H2Py',
  'Admin',
  'admin'
);
```

Default password: `admin123` — **change it on first login.**

### 6. Optional — install PDF parser for document Q&A

Visit `http://yoursite/admin/install_pdfparser.php` while logged in as admin, click **Install**, then delete that installer file.

### 7. Optional — install FFmpeg for video transcription

| OS | Command |
|---|---|
| Windows + XAMPP | Download from [gyan.dev](https://www.gyan.dev/ffmpeg/builds/), extract to `C:\ffmpeg`, add to PATH |
| macOS | `brew install ffmpeg` |
| Linux | `sudo apt install ffmpeg` |

### 8. You're live

Visit `http://yoursite/admin/login.php` and sign in.

---

## Tech stack

| Layer | Tech |
|---|---|
| Backend | PHP 8, vanilla MySQLi with prepared statements |
| Database | MySQL 5.7+ |
| Frontend | Vanilla JavaScript, Tailwind CSS (CDN), Font Awesome |
| AI | Groq (Whisper for transcription, Llama 3.1 for chat) |
| Document parsing | Smalot/PdfParser (PDF), PHP ZipArchive (DOCX) |
| In-browser viewers | pdf.js, mammoth.js, SheetJS |
| Video tooling | FFmpeg (audio extraction for transcription only) |

**No framework. No Composer. No build step.** Drop into any PHP host and run.

---

## Project structure

```
medianest/
├── admin/                  Admin panel + AI backend
│   ├── manage.php          Unified CRUD (videos, albums, folders, files, users, categories)
│   ├── quiz_editor.php     Quiz checkpoint editor + AI quiz generator
│   ├── quiz_analytics.php  Analytics dashboard
│   ├── transcribe.php      Whisper transcription endpoint
│   ├── generate_quiz.php   AI quiz generation endpoint
│   ├── extract_text.php    Document text extraction
│   ├── ai_lib.php          Groq + FFmpeg helpers
│   └── notify.php          Notification helpers
├── auth/                   Authentication + shared components
│   ├── auth.php            Session + login functions
│   ├── notif_bell.php      Drop-in notification bell
│   └── bookmark_btn.php    Drop-in star button
├── Videos/                 Public video pages + AI features
├── Photo/                  Photo galleries
├── Documents/              Document library + viewer
├── Bookmarks/              User's saved items
├── sql/                    All migration files
└── tests/                  Smoke tests (run with `php tests/run.php`)
```

---

## Architecture decisions

A few things you might wonder about:

**Why no framework?** Vanilla PHP is the most portable backend tech that still exists. Anyone on shared hosting can run it. The project is small enough that a framework would add more cognitive load than it removes. If this scales up to a real product, porting to Laravel is the obvious next step.

**Why MySQLi over PDO?** Slightly simpler API, and the project doesn't need PDO's driver abstraction (it's MySQL-only).

**Why Groq for AI?** It's free, fast, and OpenAI-API-compatible. If pricing changes, swapping to OpenAI/Anthropic is a one-line config change.

**Why lazy extraction for document Q&A?** Better UX than asking admins to remember to click "Extract" on every upload. The trade-off (slower first question per doc) is invisible in practice.

**Why polymorphic bookmarks table?** Three tables (`video_bookmarks`, `album_bookmarks`, `file_bookmarks`) would be more "correct" relationally, but one polymorphic table is simpler to query and lets the bookmarks page join across types easily.

---

## Roadmap

Things considered but not built. PRs welcome.

- [ ] Required-video tracking ("who hasn't done safety training?")
- [ ] Group-based access control (per-video visibility)
- [ ] Email-based password reset (currently admin-managed)
- [ ] OCR for scanned PDFs (Tesseract integration)
- [ ] HLS video transcoding for large files
- [ ] Bulk user import via CSV
- [ ] Activity feed across all sections
- [ ] Full Laravel port

---

## Contributing

This started as a personal project; contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

**Key guidelines:**
- All queries must use prepared statements (`mysqli_prepare` + `bind_param`)
- CSRF tokens on all admin POST forms
- Audit-log destructive admin actions
- HTML-escape all user-supplied content

---

## License

MIT — see [LICENSE](LICENSE). Do whatever you want; attribution is appreciated but not required.

---

## Acknowledgements

- [Groq](https://groq.com) — free AI inference that made this possible
- [Smalot/PdfParser](https://github.com/smalot/pdfparser) — PDF text extraction in pure PHP
- [Mozilla pdf.js](https://mozilla.github.io/pdf.js/), [mammoth.js](https://github.com/mwilliamson/mammoth.js), [SheetJS](https://sheetjs.com) — in-browser document preview
- [Tailwind CSS](https://tailwindcss.com) and [Font Awesome](https://fontawesome.com) — UI

---
 

 
