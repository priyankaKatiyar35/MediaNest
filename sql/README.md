# Demo Data Setup — for Screenshots

This pack gets your app looking populated so screenshots show off the features.

## Step 1 — Run the SQL

In phpMyAdmin:
1. Open `s&p` database
2. SQL tab
3. Paste the contents of `seed_demo.sql`
4. Click "Go"

You should see "Demo data inserted!" with counts at the bottom.

## Step 2 — Upload 1 real video + 1 PDF (so player/Q&A screenshots work)

The SQL added rows that reference filenames like `demo_safety.mp4` and `demo_employee_handbook.pdf` — but those files don't actually exist yet. For most screenshots you don't need them (lists, cards, dashboards all look fine). For the **video player page** and **document Q&A page**, you'll want a real file.

### Option A — Use videos/files you already have

If you already uploaded test videos earlier via admin, the easiest thing is to update one demo row to point at your real file:

```sql
-- Replace 'YOUR_REAL_VIDEO.mp4' with whatever's actually in admin/upload/
UPDATE video SET name='YOUR_REAL_VIDEO.mp4'
WHERE name='demo_safety.mp4';
```

Same for a PDF:

```sql
-- Replace with a real PDF in admin/uploads/
UPDATE files SET file_name='YOUR_REAL_FILE.pdf'
WHERE file_name='demo_employee_handbook.pdf';
```

### Option B — Download free sample files

If you don't have any uploads yet:

**Free sample video** (under 5 MB):
- https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4
- Rename to `demo_safety.mp4`
- Drop into `admin/upload/`

**Free sample PDF:**
- https://www.w3.org/WAI/WCAG21/working-examples/pdf-table/table.pdf
- Rename to `demo_employee_handbook.pdf`
- Drop into `admin/uploads/` (or wherever your file upload folder is)

## Step 3 — Log in as the demo user for best screenshots

The bell, bookmarks, and Continue Watching are set up for **Sarah** to look populated. Log in as:

- **Email:** `sarah@demo.medianest.test`
- **Password:** `demo123`

When you take the screenshot of the home page / videos page, log in as Sarah so:
- The bell shows "5" unread notifications
- Continue Watching shows 3 videos
- Bookmarks page has 3 items

For admin screenshots (Manage page, Quiz Analytics), log in as your usual admin account.

## What screenshots to take (5 recommended)

| # | Screenshot | Page to visit | Logged in as |
|---|---|---|---|
| 1 | Homepage with bell + Continue Watching | `Videos/index.php` | Sarah |
| 2 | Video player with AI summary panel open | `Videos/video_player.php?id=1` (click Summarize button) | Any user |
| 3 | Admin Manage Content (Files tab) | `admin/manage.php?tab=files` | Admin |
| 4 | Quiz Analytics — Overview tab with 14-day chart | `admin/quiz_analytics.php` | Admin |
| 5 | Document Q&A in action | Open a PDF, click "Ask AI", ask a question | Any user |

Save as PNG into `screenshots/` folder using these names so the README displays them:

```
screenshots/01-homepage.png
screenshots/02-video-summary.png
screenshots/03-admin-manage.png
screenshots/04-quiz-analytics.png
screenshots/05-document-qa.png
```

## Cleanup after screenshots

To remove all demo data later, open `seed_demo.sql` and uncomment the CLEANUP block at the bottom, then run that.