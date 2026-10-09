-- Phase 8: MFA for privileged accounts and private member appeal evidence.
-- Fail-closed authentication is implemented at the router, never only in the UI.
CREATE TABLE IF NOT EXISTS mfa_credentials (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  secret_ciphertext TEXT NOT NULL,
  confirmed_at DATETIME NULL,
  last_accepted_step BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mfa_recovery_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  CONSTRAINT fk_mfa_recovery_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uq_mfa_recovery_code (user_id,code_hash),
  INDEX idx_mfa_recovery_pending (user_id,used_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mfa_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mfa_attempt_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_mfa_attempt_window (user_id,attempted_at)
) ENGINE=InnoDB;

ALTER TABLE private_attachments
 MODIFY COLUMN owner_type ENUM(
  'membership_application','welfare_case','academic_verification','academic_review_request'
 ) NOT NULL;
