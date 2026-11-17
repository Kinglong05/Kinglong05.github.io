<?php
declare(strict_types=1);

/**
 * Database upgrades. Each runs once, in order, and records the new version in
 * settings.schema_version. They run automatically on the next page load, so an
 * existing (or live) site upgrades itself when you upload new code.
 *
 * Content updates only replace text that still matches the original starter text,
 * so anything you've already edited in the admin is left alone.
 */

const SCHEMA_VERSION = 7;

function run_migrations(): void
{
    $current = (int) (settings()['schema_version'] ?? 1);
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    $pdo = db();
    // Prevent two simultaneous requests from running the same upgrade
    $got = (int) $pdo->query("SELECT GET_LOCK('pf_migrate', 10)")->fetchColumn();
    if ($got !== 1) return;
    try {
        $current = (int) ($pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version'")->fetchColumn() ?: 1);
        for ($v = $current + 1; $v <= SCHEMA_VERSION; $v++) {
            $fn = 'migration_' . $v;
            $fn($pdo);
            save_setting('schema_version', (string) $v);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('pf_migrate')");
        settings(true);
    }
}

function mig_column_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
}

function mig_set_if_default(string $key, string $old, string $new): void
{
    $cur = settings()[$key] ?? null;
    if ($cur === null || $cur === '' || str_replace("\r\n", "\n", $cur) === $old) {
        save_setting($key, $new);
    }
}

function mig_insert_if_missing(PDO $pdo, string $table, string $keyCol, array $rows): void
{
    $check = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$keyCol` = ?");
    $max = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM `$table`")->fetchColumn();
    foreach ($rows as $row) {
        $check->execute([$row[$keyCol]]);
        if ((int) $check->fetchColumn() > 0) continue;
        $row['sort_order'] = ++$max;
        $cols = array_keys($row);
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($row));
    }
}

/** v2: photographer / videographer / drone pilot + gallery */
function migration_2(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'photography',
        media_type VARCHAR(10) NOT NULL DEFAULT 'photo',
        image VARCHAR(255) NULL,
        video_url VARCHAR(255) NULL,
        location VARCHAR(150) NULL,
        description TEXT NULL,
        layout VARCHAR(10) NOT NULL DEFAULT 'normal',
        sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cat (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!mig_column_exists($pdo, 'messages', 'service')) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN service VARCHAR(150) NULL AFTER email');
    }

    // --- Settings ---
    save_setting('show_gallery', settings()['show_gallery'] ?? '1');
    mig_set_if_default('hero_roles', '', "Web Developer\nPhotographer\nVideographer\nDrone Pilot");
    mig_set_if_default('hero_title', "I Build Modern\nWeb **Experiences**", "I Build, Shoot &\nFly **Stories**");
    mig_set_if_default('hero_subtitle',
        'Full-stack developer crafting beautiful, fast and scalable digital products that solve real-world problems.',
        'Full-stack developer, photographer, videographer and drone pilot crafting fast websites and cinematic visuals, from the ground up and from the sky.');
    mig_set_if_default('stat2_label', 'Projects Completed', 'Projects & Shoots');
    mig_set_if_default('about_title', "Building solutions\nwith code & **creativity.**", "Building websites &\ncapturing **stories.**");
    mig_set_if_default('about_text',
        "I'm a passionate Full-Stack Developer who loves building web applications and digital products. I enjoy turning ideas into reality through clean code and user-centered design.",
        "I'm a full-stack developer and visual creator. I build fast, modern websites, and I shoot photos, films and aerial drone footage that tell a brand's story. One person for your website and the content that fills it.");
    mig_set_if_default('about_points',
        "Problem solver by nature\nClean, scalable and maintainable code\nPassionate about performance & UX",
        "Clean, scalable and maintainable code\nCinematic photo & video storytelling\nAerial shots from a whole new angle");
    mig_set_if_default('meta_title', 'Elrich Madrileno | Full-Stack Web Developer', 'Elrich Madrileno | Web Developer, Photographer, Videographer & Drone Pilot');
    mig_set_if_default('meta_description',
        'Portfolio of Elrich Madrileno, a full-stack developer building modern, fast and scalable web experiences.',
        'Portfolio of Elrich Madrileno: full-stack web developer, photographer, videographer and drone pilot based in Iloilo, Philippines.');
    mig_set_if_default('skills_note', 'Always learning new technologies', 'Code, camera & sky: always learning');
    save_setting('sample_notice', '1');

    // --- Services ---
    mig_insert_if_missing($pdo, 'services', 'title', [
        ['title' => 'Photography', 'description' => 'Portraits, events, products and brand photos, professionally shot and edited.', 'icon' => 'fa-solid fa-camera'],
        ['title' => 'Videography', 'description' => 'Event coverage, promos and social media reels, from filming to final edit.', 'icon' => 'fa-solid fa-video'],
        ['title' => 'Drone Photo & Video', 'description' => 'Aerial photos and cinematic drone footage for real estate, events, tourism and brands.', 'icon' => 'icon-drone'],
    ]);
    // Merge "Performance Optimization" into web dev to keep the grid tidy (only if untouched)
    $pdo->prepare("UPDATE services SET is_visible = 0 WHERE title = 'Performance Optimization' AND description = ?")
        ->execute(['Improving speed, SEO and overall performance for a better user experience.']);

    // --- Skills / tools ---
    mig_insert_if_missing($pdo, 'skills', 'name', [
        ['name' => 'Photoshop', 'icon_class' => 'devicon-photoshop-plain colored'],
        ['name' => 'Lightroom', 'icon_class' => 'fa-solid fa-sliders pf-lr'],
        ['name' => 'Premiere Pro', 'icon_class' => 'devicon-premierepro-plain colored'],
        ['name' => 'After Effects', 'icon_class' => 'devicon-aftereffects-plain colored'],
        ['name' => 'DaVinci Resolve', 'icon_class' => 'fa-solid fa-film pf-gold'],
        ['name' => 'Drone (DJI)', 'icon_class' => 'icon-drone pf-gold'],
    ]);
    // Hide one niche web skill (if untouched) so the grid stays an even 5×4 (20 tiles)
    $pdo->exec("UPDATE skills SET is_visible = 0 WHERE name = 'GraphQL' AND icon_class = 'devicon-graphql-plain colored'");

    // --- Experience (only replaces the untouched sample rows) ---
    $pdo->prepare("UPDATE experiences SET role = 'Photographer & Videographer', company = 'Freelance', period = '2022 - Present',
        description = 'Shooting portraits, events and brand content, and editing photo and video for clients and social media.', icon = 'fa-solid fa-camera'
        WHERE role = 'Web Developer' AND company = 'Sample Company'")->execute();
    $pdo->prepare("UPDATE experiences SET role = 'Drone Pilot', company = 'Freelance', period = '2023 - Present',
        description = 'Flying aerial photo and video missions for real estate, events and tourism content.', icon = 'icon-drone'
        WHERE role = 'Frontend Developer' AND company = 'Sample Studio'")->execute();

    // --- Sample gallery items (replace with your own work in Admin > Gallery) ---
    if ((int) $pdo->query('SELECT COUNT(*) FROM gallery')->fetchColumn() === 0) {
        $items = [
            ['Golden Hour Portraits', 'photography', 'photo', 'big', 'Iloilo City'],
            ['Coastline From Above', 'drone', 'photo', 'wide', 'Guimaras'],
            ['Event Highlight Reel', 'videography', 'video', 'normal', ''],
            ['Street Series', 'photography', 'photo', 'normal', ''],
            ['Rice Terraces Aerial', 'drone', 'photo', 'tall', ''],
            ['Brand Promo Film', 'videography', 'video', 'normal', ''],
            ['Product Shoot', 'photography', 'photo', 'normal', ''],
            ['Sunset Flyover', 'drone', 'video', 'wide', ''],
        ];
        $st = $pdo->prepare('INSERT INTO gallery (title, category, media_type, layout, location, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $i => [$t, $c, $m, $l, $loc]) {
            $st->execute([$t, $c, $m, $l, $loc, 'Sample item: upload your own photo or video link in the admin panel.', $i + 1]);
        }
    }
}

/** v3: longer media links (Facebook URLs can be long) */
function migration_3(PDO $pdo): void
{
    $pdo->exec('ALTER TABLE gallery MODIFY video_url VARCHAR(500) NULL');
}

/** v4: photo albums (many photos per gallery item) */
function migration_4(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_photos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gallery_id INT UNSIGNED NOT NULL,
        image VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gallery (gallery_id, sort_order),
        CONSTRAINT fk_gallery_photos_gallery FOREIGN KEY (gallery_id) REFERENCES gallery (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Clear the leftover sample caption on items that now hold real content
    $pdo->exec("UPDATE gallery SET description = '' WHERE description = 'Sample item: upload your own photo or video link in the admin panel.'
        AND ((image IS NOT NULL AND image <> '') OR (video_url IS NOT NULL AND video_url <> ''))");
}

/** v5: self-hosted video files + remember when Facebook blocks embedding */
function migration_5(PDO $pdo): void
{
    if (!mig_column_exists($pdo, 'gallery', 'video_file')) {
        $pdo->exec('ALTER TABLE gallery ADD COLUMN video_file VARCHAR(255) NULL AFTER video_url');
    }
    if (!mig_column_exists($pdo, 'gallery', 'embed_status')) {
        $pdo->exec('ALTER TABLE gallery ADD COLUMN embed_status VARCHAR(10) NULL AFTER video_file');
    }
    $pdo->exec("UPDATE gallery SET description = '' WHERE description = 'Sample item: upload your own photo or video link in the admin panel.'
        AND ((image IS NOT NULL AND image <> '') OR (video_url IS NOT NULL AND video_url <> ''))");
}

/** v6: packages & prices, before/after, certificates, gear, stories (blog) + extras */
function migration_6(PDO $pdo): void
{
    $common = "sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        service VARCHAR(150) NULL,
        price VARCHAR(60) NULL,
        price_note VARCHAR(100) NULL,
        description TEXT NULL,
        features TEXT NULL,
        icon VARCHAR(100) NULL,
        is_popular TINYINT(1) NOT NULL DEFAULT 0,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comparisons (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        before_image VARCHAR(255) NULL,
        after_image VARCHAR(255) NULL,
        caption VARCHAR(300) NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        issuer VARCHAR(150) NULL,
        issued VARCHAR(60) NULL,
        image VARCHAR(255) NULL,
        link VARCHAR(500) NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gear (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'camera',
        note VARCHAR(200) NULL,
        image VARCHAR(255) NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        published_on DATE NULL,
        cover VARCHAR(255) NULL,
        excerpt VARCHAR(400) NULL,
        body MEDIUMTEXT NULL,
        UNIQUE KEY uq_slug (slug),
        $common");

    foreach (['show_packages', 'show_comparisons', 'show_certificates', 'show_gear', 'show_posts', 'show_instagram', 'show_theme_toggle', 'show_lang_toggle'] as $k) {
        if (!isset(settings()[$k])) save_setting($k, '1');
    }
    if (!isset(settings()['packages_note'])) {
        save_setting('packages_note', 'Starting rates. Your final quote depends on location, hours and deliverables. Message me for a custom package.');
    }
    if (!isset(settings()['showreel_label'])) save_setting('showreel_label', 'Watch Showreel');
    if (empty(settings()['ga_property_id'])) save_setting('ga_property_id', '534156941');
}


/** v7: clients, how-I-work, FAQ, proofing galleries, booking details, case studies, 2-step login */
function migration_7(PDO $pdo): void
{
    $common = "sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        logo VARCHAR(255) NULL,
        url VARCHAR(500) NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS process_steps (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(120) NOT NULL,
        description VARCHAR(400) NULL,
        icon VARCHAR(100) NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(250) NOT NULL,
        answer TEXT NULL,
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS proofs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        client_name VARCHAR(150) NULL,
        slug VARCHAR(80) NOT NULL,
        access_code VARCHAR(60) NOT NULL,
        message VARCHAR(500) NULL,
        expires_on DATE NULL,
        allow_download TINYINT(1) NOT NULL DEFAULT 1,
        cover VARCHAR(255) NULL,
        UNIQUE KEY uq_proof_slug (slug),
        $common");

    $pdo->exec("CREATE TABLE IF NOT EXISTS proof_photos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proof_id INT UNSIGNED NOT NULL,
        image VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_proof (proof_id, sort_order),
        CONSTRAINT fk_proof_photos_proof FOREIGN KEY (proof_id) REFERENCES proofs (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach (['event_date' => 'DATE NULL', 'event_location' => 'VARCHAR(200) NULL', 'budget' => 'VARCHAR(60) NULL'] as $col => $def) {
        if (!mig_column_exists($pdo, 'messages', $col)) $pdo->exec("ALTER TABLE messages ADD COLUMN $col $def AFTER subject");
    }
    if (!mig_column_exists($pdo, 'projects', 'case_slug')) $pdo->exec('ALTER TABLE projects ADD COLUMN case_slug VARCHAR(120) NULL AFTER repo_url');
    if (!mig_column_exists($pdo, 'projects', 'case_study')) $pdo->exec('ALTER TABLE projects ADD COLUMN case_study MEDIUMTEXT NULL AFTER case_slug');
    if (!mig_column_exists($pdo, 'admins', 'totp_secret')) $pdo->exec('ALTER TABLE admins ADD COLUMN totp_secret VARCHAR(64) NULL AFTER password_hash');

    // Generic "How I work" steps (edit them in the admin)
    if ((int) $pdo->query('SELECT COUNT(*) FROM process_steps')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO process_steps (title, description, icon, sort_order) VALUES (?, ?, ?, ?)');
        foreach ([
            ['Inquire', 'Send a message with your date, location and what you need. I reply with options and a quote.', 'fa-regular fa-comments'],
            ['Plan', 'We agree on the shot list, schedule and deliverables so there are no surprises on the day.', 'fa-solid fa-clipboard-list'],
            ['Create', 'I shoot, fly or build, then edit everything with care: color, sound and detail.', 'fa-solid fa-wand-magic-sparkles'],
            ['Deliver', 'You receive your files through an online gallery or link, ready to post, print or launch.', 'fa-solid fa-paper-plane'],
        ] as $i => [$t, $d, $ic]) {
            $st->execute([$t, $d, $ic, $i + 1]);
        }
    }
    // FAQ: questions only, HIDDEN until you write your own answers
    if ((int) $pdo->query('SELECT COUNT(*) FROM faqs')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO faqs (question, answer, sort_order, is_visible) VALUES (?, ?, ?, 0)');
        foreach ([
            'How much do you charge?',
            'Do you travel outside my city? Is there a travel fee?',
            'How long until I receive my photos or video?',
            'Do you give the raw / unedited files?',
            'How do I book and how much is the down payment?',
            'Is the drone allowed everywhere?',
        ] as $i => $q) {
            $st->execute([$q, '', $i + 1]);
        }
    }
    foreach (['show_intro' => '1', 'show_cursor' => '1', 'show_clients' => '1', 'show_process' => '1', 'show_faq' => '1',
              'show_mobile_bar' => '1', 'show_booking_fields' => '1', 'messenger_username' => '', 'links_title' => ''] as $k => $v) {
        if (!isset(settings()[$k])) save_setting($k, $v);
    }
}
