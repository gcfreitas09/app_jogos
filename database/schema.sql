CREATE DATABASE IF NOT EXISTS app_jogos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE app_jogos;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    avatar_url VARCHAR(255) NULL,
    preferred_sports VARCHAR(255) NULL,
    default_radius_km INT UNSIGNED NOT NULL DEFAULT 5,
    allow_location TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNSIGNED NOT NULL,
    sport VARCHAR(60) NOT NULL,
    level ENUM('iniciante', 'intermediario', 'avancado') NOT NULL DEFAULT 'intermediario',
    starts_at DATETIME NOT NULL,
    location_name VARCHAR(190) NOT NULL,
    address VARCHAR(255) NOT NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    max_players INT UNSIGNED NOT NULL,
    privacy ENUM('public', 'private') NOT NULL DEFAULT 'public',
    description TEXT NULL,
    price DECIMAL(10,2) NULL,
    rules_text TEXT NULL,
    status ENUM('open', 'full') NOT NULL DEFAULT 'open',
    completed_notified_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invites_creator
        FOREIGN KEY (creator_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_invites_sport ON invites (sport);
CREATE INDEX idx_invites_starts_at ON invites (starts_at);
CREATE INDEX idx_invites_geo ON invites (lat, lng);
CREATE INDEX idx_invites_status ON invites (status);
CREATE INDEX idx_invites_privacy ON invites (privacy);
CREATE INDEX idx_invites_completed_notified_at ON invites (completed_notified_at);

CREATE TABLE IF NOT EXISTS invite_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invite_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('player', 'waitlist') NOT NULL,
    position INT UNSIGNED NULL,
    status ENUM('active', 'left', 'removed') NOT NULL DEFAULT 'active',
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invite_members_invite
        FOREIGN KEY (invite_id) REFERENCES invites (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_invite_members_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT uq_invite_member UNIQUE (invite_id, user_id)
) ENGINE=InnoDB;

CREATE INDEX idx_invite_members_lookup ON invite_members (invite_id, role, status, position);
CREATE INDEX idx_invite_members_user ON invite_members (user_id, status);
