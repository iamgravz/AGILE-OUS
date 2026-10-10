-- Phase 5B additive integrity index only.
-- 005_vacancies_notifications.sql already created year, semester,
-- recruitment_position_id, its foreign key, term unique index and outbox.
-- Do not recreate them here: doing so breaks fresh installs.
-- Back up and inspect populated environments before applying migrations.
ALTER TABLE membership_applications
  ADD INDEX idx_application_position_status (recruitment_position_id,status);
