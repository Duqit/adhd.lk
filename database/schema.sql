-- ADHD.lk database schema (MySQL 5.7+ / MariaDB 10.3+). Structure only, no data.
--
-- Reconstructed from the queries in public_html/ (every column the code reads or writes).
-- Column sizes and indexes may differ slightly from the production database.
--
--   mysql -u root -e "CREATE DATABASE adhd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u root adhd < database/schema.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username         VARCHAR(50)  NOT NULL,
    email            VARCHAR(150) NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,          -- password_hash(); random for Google-only accounts
    role             ENUM('user','admin') NOT NULL DEFAULT 'user',
    xp               INT UNSIGNED NOT NULL DEFAULT 0,
    level            INT UNSIGNED NOT NULL DEFAULT 1,
    google_id        VARCHAR(64)  NULL,              -- Google "sub" claim
    data_imported_at DATETIME     NULL,              -- set once guest data is imported (api/import.php)
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed / throttled auth attempts (includes/ratelimit.php). Usernames may be
-- prefixed keys such as "reset:<email>" or "resetuse".
CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL,
    username     VARCHAR(255) NULL,
    attempted_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_ip_time (ip, attempted_at),
    KEY idx_attempts_user_time (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset links (forgot.php / reset.php). Only the SHA-256 of the token is stored.
CREATE TABLE password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resets_token (token_hash),
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tools (logged-in mode; guests keep the same data in localStorage)
-- Deleting a user cascades to all of their data (delete_account.php relies on this).
-- ---------------------------------------------------------------------------
CREATE TABLE tasks (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    title             VARCHAR(255) NOT NULL,
    estimated_minutes INT UNSIGNED NULL,
    is_completed      BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at        DATE         NOT NULL,         -- the planner day the task belongs to
    PRIMARY KEY (id),
    KEY idx_tasks_user_day (user_id, created_at),
    CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moods (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    mood_score TINYINT UNSIGNED NOT NULL,            -- 1 (😫) to 5 (😄)
    note       VARCHAR(500) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_moods_user_time (user_id, created_at),
    CONSTRAINT fk_moods_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    prompt     VARCHAR(255) NOT NULL,
    entry_text TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_journal_user_time (user_id, created_at),
    CONSTRAINT fk_journal_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE habits (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(120) NOT NULL,
    emoji      VARCHAR(16)  NOT NULL DEFAULT '✅',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_habits_user (user_id),
    CONSTRAINT fk_habits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE habit_logs (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    habit_id INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    log_date DATE         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_habit_day (habit_id, log_date),
    KEY idx_habit_logs_user (user_id, log_date),
    CONSTRAINT fk_habit_logs_habit FOREIGN KEY (habit_id) REFERENCES habits (id) ON DELETE CASCADE,
    CONSTRAINT fk_habit_logs_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE routines (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_routines_user (user_id),
    CONSTRAINT fk_routines_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE routine_steps (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    routine_id       INT UNSIGNED NOT NULL,
    title            VARCHAR(255) NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 1,
    step_order       INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_steps_routine (routine_id, step_order),
    CONSTRAINT fk_steps_routine FOREIGN KEY (routine_id) REFERENCES routines (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Articles (CMS: admin_articles.php)
-- ---------------------------------------------------------------------------
CREATE TABLE articles (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title             VARCHAR(255) NOT NULL,
    meta_title        VARCHAR(255) NULL,             -- optional SEO <title>, never sent to Gemini
    slug              VARCHAR(255) NULL,
    excerpt           TEXT         NULL,
    content           MEDIUMTEXT   NOT NULL,         -- trusted HTML written by admins
    author            VARCHAR(100) NOT NULL DEFAULT 'ADHD.lk Team',
    tags              VARCHAR(255) NULL,             -- comma-separated
    image_url         VARCHAR(500) NULL,
    lang              ENUM('en','si','ta') NOT NULL DEFAULT 'en',
    translation_group INT UNSIGNED NULL,             -- shared by the en/si/ta versions of one article
    polished_at       DATETIME     NULL,             -- last Gemini rewrite
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_articles_slug (slug),
    KEY idx_articles_lang_created (lang, created_at),
    KEY idx_articles_group (translation_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previous versions of an article, kept on every save (last 5 shown in the CMS).
CREATE TABLE article_revisions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    title      VARCHAR(255) NOT NULL,
    excerpt    TEXT         NULL,
    content    MEDIUMTEXT   NOT NULL,
    saved_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_revisions_article (article_id),
    CONSTRAINT fk_revisions_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_bookmarks (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bookmark (user_id, article_id),
    CONSTRAINT fk_bookmarks_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
