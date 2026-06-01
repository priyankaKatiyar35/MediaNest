-- ============================================================
-- MediaNest — Demo Data Seeder
-- ============================================================
-- Run this AFTER all migrations are installed.
-- Populates the app with realistic demo content for screenshots.
--
-- To remove later, see "CLEANUP" block at the bottom (commented).
-- ============================================================

USE `s&p`;

-- Wipe any previous demo data (safe if first run — won't error)
DELETE FROM quiz_responses    WHERE user_name LIKE 'demo:%';
DELETE FROM notifications     WHERE title     LIKE '[demo]%';
DELETE FROM bookmarks         WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.medianest.test');
DELETE FROM video_progress    WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.medianest.test');
DELETE FROM video_summaries   WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
DELETE FROM video_transcripts WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
DELETE FROM video_quizzes     WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
DELETE FROM video             WHERE name LIKE 'demo_%';
DELETE FROM tbl_gallery       WHERE gimages LIKE 'demo_%';
DELETE FROM tbl_album         WHERE name LIKE '[demo]%';
DELETE FROM files             WHERE file_name LIKE 'demo_%';
DELETE FROM folders           WHERE name LIKE '[demo]%';
DELETE FROM video_categories  WHERE name LIKE '[demo]%';
DELETE FROM users             WHERE email LIKE '%@demo.medianest.test';

-- ============================================================
-- USERS (4 fake staff across departments + 1 admin)
-- Password for all demo users: demo123
-- ============================================================
INSERT INTO users (email, password_hash, full_name, group_name, role) VALUES
('sarah@demo.medianest.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Mitchell',  'Sales',       'user'),
('rajesh@demo.medianest.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rajesh Kumar',    'Engineering', 'user'),
('emily@demo.medianest.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emily Chen',      'HR',          'user'),
('marcus@demo.medianest.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marcus Johnson',  'Marketing',   'user'),
('priya@demo.medianest.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Priya Sharma',    'Engineering', 'user');

-- ============================================================
-- VIDEO CATEGORIES
-- ============================================================
INSERT INTO video_categories (name) VALUES
('[demo] Training'),
('[demo] All-Hands'),
('[demo] Events');

SET @cat_train  := LAST_INSERT_ID();
SET @cat_hands  := @cat_train + 1;
SET @cat_events := @cat_train + 2;

-- ============================================================
-- VIDEOS (6 with realistic titles)
-- ============================================================
-- NOTE: `name` column should match a real file in admin/upload/ to play.
-- For demo, we use a placeholder name. Replace one with your actual uploaded video.
INSERT INTO video (name, title, des, category_id) VALUES
('demo_safety.mp4',      'Workplace Safety Fundamentals',          'Mandatory training covering fire safety, ergonomics, and emergency procedures. Required for all new hires.', @cat_train),
('demo_sales.mp4',       'Q3 Sales Methodology — Discovery Calls', 'Learn the SPIN selling framework and how to run discovery calls that uncover real pain points.', @cat_train),
('demo_security.mp4',    'Cybersecurity Awareness 2026',           'Phishing, password hygiene, and how to spot social engineering attacks. Updated for new threats.', @cat_train),
('demo_townhall.mp4',    'October All-Hands Town Hall',            'Quarterly review with leadership: Q3 results, Q4 priorities, and Q&A session.', @cat_hands),
('demo_diwali.mp4',      'Diwali Celebration 2026',                'Highlights from our annual Diwali celebration at the office. Sweets, lights, and good vibes.', @cat_events),
('demo_offsite.mp4',     'Engineering Team Offsite — Goa',         'Three days of strategy, code reviews, and beach volleyball. Featuring the now-famous coconut incident.', @cat_events);

-- Capture the inserted video IDs
SET @vid_safety   := (SELECT id FROM video WHERE name='demo_safety.mp4');
SET @vid_sales    := (SELECT id FROM video WHERE name='demo_sales.mp4');
SET @vid_security := (SELECT id FROM video WHERE name='demo_security.mp4');
SET @vid_townhall := (SELECT id FROM video WHERE name='demo_townhall.mp4');

-- ============================================================
-- TRANSCRIPTS (2 fake but realistic-looking)
-- ============================================================
INSERT INTO video_transcripts (video_id, full_text, segments, language, model, duration_sec) VALUES
(@vid_safety,
 'Welcome to workplace safety fundamentals. In this video we will cover three core topics: fire safety, ergonomics, and emergency procedures. Let''s start with fire safety. Every floor has two designated exits marked with green signs. The primary exit is at the north end, the secondary at the south. If you discover a fire, pull the nearest alarm and exit immediately. Do not use the elevator. The assembly point is the parking lot across the street. Now ergonomics. Your monitor should be at eye level, about an arm''s length away. Feet flat on the floor. Take a five-minute break every hour to prevent eye strain and repetitive stress injuries. For emergencies, dial extension 911 from any internal phone. The first aid kit is located by the kitchen on each floor. AEDs are on the second and fourth floors near the elevators.',
 '[]',
 'en',
 'whisper-large-v3-turbo',
 480),
(@vid_sales,
 'Today we''re going to talk about the SPIN selling framework. SPIN stands for Situation, Problem, Implication, and Need-payoff. The biggest mistake new salespeople make is jumping straight to pitching the product. Instead, start with situation questions to understand the customer''s current state. Then ask problem questions to surface pain points. Implication questions help the customer realize the cost of not solving the problem. Finally, need-payoff questions get them to articulate why a solution matters. A typical discovery call should be eighty percent questions and twenty percent talking from your side. Take detailed notes. After the call, send a recap email within twenty-four hours summarizing what you learned and proposing next steps.',
 '[]',
 'en',
 'whisper-large-v3-turbo',
 540);

-- ============================================================
-- SUMMARIES
-- ============================================================
INSERT INTO video_summaries (video_id, summary, topics, model) VALUES
(@vid_safety,
 'Mandatory safety training covering fire evacuation routes, ergonomic workstation setup, and emergency response procedures. Key takeaways: know your two nearest exits, take hourly breaks, and dial extension 911 in emergencies.',
 '["Fire Safety","Ergonomics","Emergency Response","Workplace Wellness","Evacuation Procedures"]',
 'llama-3.1-8b-instant'),
(@vid_sales,
 'Introduction to the SPIN selling methodology for discovery calls. Emphasizes asking situation, problem, implication, and need-payoff questions instead of pitching upfront. Discovery calls should be 80% listening, 20% talking, followed by a same-day recap email.',
 '["SPIN Selling","Discovery Calls","Sales Methodology","Active Listening","Customer Pain Points"]',
 'llama-3.1-8b-instant');

-- ============================================================
-- VIDEO QUIZZES (checkpoint questions on the safety video)
-- ============================================================
INSERT INTO video_quizzes (video_id, trigger_time, group_label) VALUES
(@vid_safety, 60,  'Checkpoint 1'),
(@vid_safety, 180, 'Checkpoint 2'),
(@vid_safety, 360, 'Checkpoint 3');

SET @q1 := (SELECT id FROM video_quizzes WHERE video_id=@vid_safety AND trigger_time=60);
SET @q2 := (SELECT id FROM video_quizzes WHERE video_id=@vid_safety AND trigger_time=180);
SET @q3 := (SELECT id FROM video_quizzes WHERE video_id=@vid_safety AND trigger_time=360);

INSERT INTO quiz_options (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(@q1, 'What color are the exit signs on each floor?',           'Red',         'Green',  'Blue',        'Yellow',  'B'),
(@q2, 'How often should you take a break to prevent eye strain?', 'Every 15 min', 'Every 30 min', 'Every hour', 'Every 2 hours', 'C'),
(@q3, 'What extension do you dial for emergencies?',            '100',         '108',    '911',         '999',     'C');

SET @opt1 := (SELECT id FROM quiz_options WHERE quiz_id=@q1 LIMIT 1);
SET @opt2 := (SELECT id FROM quiz_options WHERE quiz_id=@q2 LIMIT 1);
SET @opt3 := (SELECT id FROM quiz_options WHERE quiz_id=@q3 LIMIT 1);

-- ============================================================
-- QUIZ RESPONSES (~30 across users, dates, and questions)
-- Mix correct/wrong so the analytics dashboard shows real-looking spread
-- ============================================================
INSERT INTO quiz_responses (video_id, quiz_id, option_id, user_name, group_name, selected_option, is_correct, answered_at) VALUES
-- Checkpoint 1 (easy) — most correct
(@vid_safety, @q1, @opt1, 'demo:sarah',  'Sales',       'B', 1, NOW() - INTERVAL 12 DAY),
(@vid_safety, @q1, @opt1, 'demo:rajesh', 'Engineering', 'B', 1, NOW() - INTERVAL 11 DAY),
(@vid_safety, @q1, @opt1, 'demo:emily',  'HR',          'A', 0, NOW() - INTERVAL 10 DAY),
(@vid_safety, @q1, @opt1, 'demo:marcus', 'Marketing',   'B', 1, NOW() - INTERVAL 9  DAY),
(@vid_safety, @q1, @opt1, 'demo:priya',  'Engineering', 'B', 1, NOW() - INTERVAL 8  DAY),
-- Checkpoint 2 (medium) — mixed
(@vid_safety, @q2, @opt2, 'demo:sarah',  'Sales',       'C', 1, NOW() - INTERVAL 12 DAY),
(@vid_safety, @q2, @opt2, 'demo:rajesh', 'Engineering', 'B', 0, NOW() - INTERVAL 11 DAY),
(@vid_safety, @q2, @opt2, 'demo:emily',  'HR',          'C', 1, NOW() - INTERVAL 10 DAY),
(@vid_safety, @q2, @opt2, 'demo:marcus', 'Marketing',   'D', 0, NOW() - INTERVAL 9  DAY),
(@vid_safety, @q2, @opt2, 'demo:priya',  'Engineering', 'C', 1, NOW() - INTERVAL 8  DAY),
-- Checkpoint 3 (hardest) — many wrong
(@vid_safety, @q3, @opt3, 'demo:sarah',  'Sales',       'A', 0, NOW() - INTERVAL 12 DAY),
(@vid_safety, @q3, @opt3, 'demo:rajesh', 'Engineering', 'C', 1, NOW() - INTERVAL 11 DAY),
(@vid_safety, @q3, @opt3, 'demo:emily',  'HR',          'B', 0, NOW() - INTERVAL 10 DAY),
(@vid_safety, @q3, @opt3, 'demo:marcus', 'Marketing',   'A', 0, NOW() - INTERVAL 9  DAY),
(@vid_safety, @q3, @opt3, 'demo:priya',  'Engineering', 'C', 1, NOW() - INTERVAL 8  DAY),
-- More recent rounds for the 14-day trend chart
(@vid_safety, @q1, @opt1, 'demo:sarah',  'Sales',       'B', 1, NOW() - INTERVAL 4 DAY),
(@vid_safety, @q1, @opt1, 'demo:rajesh', 'Engineering', 'B', 1, NOW() - INTERVAL 4 DAY),
(@vid_safety, @q2, @opt2, 'demo:sarah',  'Sales',       'C', 1, NOW() - INTERVAL 4 DAY),
(@vid_safety, @q2, @opt2, 'demo:emily',  'HR',          'C', 1, NOW() - INTERVAL 3 DAY),
(@vid_safety, @q3, @opt3, 'demo:rajesh', 'Engineering', 'C', 1, NOW() - INTERVAL 3 DAY),
(@vid_safety, @q3, @opt3, 'demo:marcus', 'Marketing',   'C', 1, NOW() - INTERVAL 2 DAY),
(@vid_safety, @q1, @opt1, 'demo:emily',  'HR',          'B', 1, NOW() - INTERVAL 2 DAY),
(@vid_safety, @q2, @opt2, 'demo:priya',  'Engineering', 'C', 1, NOW() - INTERVAL 1 DAY),
(@vid_safety, @q3, @opt3, 'demo:sarah',  'Sales',       'A', 0, NOW() - INTERVAL 1 DAY),
(@vid_safety, @q1, @opt1, 'demo:marcus', 'Marketing',   'B', 1, NOW()),
(@vid_safety, @q2, @opt2, 'demo:rajesh', 'Engineering', 'C', 1, NOW()),
(@vid_safety, @q3, @opt3, 'demo:emily',  'HR',          'C', 1, NOW());

-- ============================================================
-- ALBUMS (4 demo galleries)
-- ============================================================
INSERT INTO tbl_album (name, adesc, image, date, status) VALUES
('[demo] Q3 Team Offsite — Goa',     'Three days of strategy, beach, and team building.', 'demo_offsite_cover.jpg', NOW() - INTERVAL 30 DAY, 'process'),
('[demo] Diwali Celebration 2026',   'Lights, sweets, and the annual rangoli competition.', 'demo_diwali_cover.jpg', NOW() - INTERVAL 60 DAY, 'process'),
('[demo] New Hire Onboarding — Oct', 'Welcome to the team! Photos from the October cohort.', 'demo_onboard_cover.jpg', NOW() - INTERVAL 20 DAY, 'process'),
('[demo] Office Renovation Reveal',  'The new collaboration space opened this month.', 'demo_office_cover.jpg', NOW() - INTERVAL 7 DAY, 'process');

-- ============================================================
-- DOCUMENT FOLDERS + FILES
-- ============================================================
INSERT INTO folders (name, parent_folder_id) VALUES
('[demo] HR Policies',     NULL),
('[demo] Engineering Wiki', NULL),
('[demo] Sales Playbooks', NULL),
('[demo] Templates',       NULL);

SET @fol_hr   := (SELECT albumid FROM folders WHERE name='[demo] HR Policies');
SET @fol_eng  := (SELECT albumid FROM folders WHERE name='[demo] Engineering Wiki');
SET @fol_sale := (SELECT albumid FROM folders WHERE name='[demo] Sales Playbooks');
SET @fol_tpl  := (SELECT albumid FROM folders WHERE name='[demo] Templates');

INSERT INTO files (file_name, file_desc, folder_id) VALUES
('demo_employee_handbook.pdf', 'Employee Handbook 2026',                @fol_hr),
('demo_leave_policy.pdf',      'Leave & Time-Off Policy',               @fol_hr),
('demo_code_of_conduct.pdf',   'Code of Conduct',                       @fol_hr),
('demo_oncall_runbook.pdf',    'On-Call Runbook',                       @fol_eng),
('demo_deployment_guide.docx', 'Production Deployment Guide',           @fol_eng),
('demo_sales_playbook.pdf',    'Q4 Sales Playbook',                     @fol_sale),
('demo_proposal_template.docx', 'Client Proposal Template',             @fol_tpl),
('demo_invoice_template.xlsx', 'Invoice Template',                      @fol_tpl);

-- ============================================================
-- CONTINUE WATCHING — 3 "in-progress" videos for sarah (user_id)
-- ============================================================
SET @uid_sarah  := (SELECT id FROM users WHERE email='sarah@demo.medianest.test');
SET @uid_rajesh := (SELECT id FROM users WHERE email='rajesh@demo.medianest.test');

INSERT INTO video_progress (user_id, video_id, last_position, duration_sec, progress_pct, completed, last_watched_at) VALUES
(@uid_sarah, @vid_safety,   210, 480, 44, 0, NOW() - INTERVAL 2 HOUR),
(@uid_sarah, @vid_sales,    180, 540, 33, 0, NOW() - INTERVAL 1 DAY),
(@uid_sarah, @vid_security,  90, 420, 21, 0, NOW() - INTERVAL 3 DAY);

-- ============================================================
-- NOTIFICATIONS — 5 unread for sarah so bell shows "5"
-- ============================================================
INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at) VALUES
(@uid_sarah, 'video_new', '[demo] New video: Cybersecurity Awareness 2026', 'Phishing, password hygiene, and how to spot social engineering attacks.', '../Videos/video_player.php?id=3',  0, NOW() - INTERVAL 1 HOUR),
(@uid_sarah, 'album_new', '[demo] New album: Office Renovation Reveal',     'The new collaboration space opened this month.',                          '../Photo/gallery.php?id=4',         0, NOW() - INTERVAL 6 HOUR),
(@uid_sarah, 'doc_new',   '[demo] New document: Employee Handbook 2026',    'Updated handbook covering benefits, leave policy, and remote work.',     '../Documents/view_file.php?file_id=1', 0, NOW() - INTERVAL 1 DAY),
(@uid_sarah, 'doc_new',   '[demo] New document: Q4 Sales Playbook',         'The new sales methodology for Q4 — required reading for the team.',      '../Documents/view_file.php?file_id=6', 0, NOW() - INTERVAL 2 DAY),
(@uid_sarah, 'video_new', '[demo] New video: Q3 Sales Methodology',         'Learn the SPIN selling framework.',                                       '../Videos/video_player.php?id=2',  0, NOW() - INTERVAL 3 DAY);

-- ============================================================
-- BOOKMARKS — 3 for sarah
-- ============================================================
INSERT INTO bookmarks (user_id, item_type, item_id, created_at) VALUES
(@uid_sarah, 'video', @vid_safety,                                                       NOW() - INTERVAL 5 DAY),
(@uid_sarah, 'album', (SELECT albumid FROM tbl_album WHERE name='[demo] Diwali Celebration 2026'), NOW() - INTERVAL 3 DAY),
(@uid_sarah, 'file',  (SELECT file_id FROM files     WHERE file_name='demo_employee_handbook.pdf'), NOW() - INTERVAL 1 DAY);

-- ============================================================
-- DONE — Show what was added
-- ============================================================
SELECT 'Demo data inserted!' AS status,
       (SELECT COUNT(*) FROM users  WHERE email LIKE '%@demo.medianest.test') AS demo_users,
       (SELECT COUNT(*) FROM video  WHERE name  LIKE 'demo_%')                AS demo_videos,
       (SELECT COUNT(*) FROM tbl_album WHERE name LIKE '[demo]%')             AS demo_albums,
       (SELECT COUNT(*) FROM files  WHERE file_name LIKE 'demo_%')            AS demo_files,
       (SELECT COUNT(*) FROM quiz_responses WHERE user_name LIKE 'demo:%')    AS demo_responses,
       (SELECT COUNT(*) FROM notifications  WHERE title LIKE '[demo]%')       AS demo_notifs;


-- ============================================================
-- CLEANUP — uncomment to remove all demo data later
-- ============================================================
-- DELETE FROM quiz_responses    WHERE user_name LIKE 'demo:%';
-- DELETE FROM notifications     WHERE title LIKE '[demo]%';
-- DELETE FROM bookmarks         WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.medianest.test');
-- DELETE FROM video_progress    WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.medianest.test');
-- DELETE FROM video_summaries   WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
-- DELETE FROM video_transcripts WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
-- DELETE FROM quiz_options      WHERE quiz_id IN (SELECT id FROM video_quizzes WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%'));
-- DELETE FROM video_quizzes     WHERE video_id IN (SELECT id FROM video WHERE name LIKE 'demo_%');
-- DELETE FROM video             WHERE name LIKE 'demo_%';
-- DELETE FROM tbl_album         WHERE name LIKE '[demo]%';
-- DELETE FROM files             WHERE file_name LIKE 'demo_%';
-- DELETE FROM folders           WHERE name LIKE '[demo]%';
-- DELETE FROM video_categories  WHERE name LIKE '[demo]%';
-- DELETE FROM users             WHERE email LIKE '%@demo.medianest.test';