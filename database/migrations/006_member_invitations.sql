CREATE TABLE IF NOT EXISTS member_invitations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (member_id) REFERENCES members(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_invite_member (member_id,expires_at)
) ENGINE=InnoDB;