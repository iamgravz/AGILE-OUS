-- Public submission abuse-control storage, no raw IP address retained.
CREATE TABLE IF NOT EXISTS public_submission_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_hash CHAR(64) NOT NULL,
 attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_public_attempts_source (source_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
