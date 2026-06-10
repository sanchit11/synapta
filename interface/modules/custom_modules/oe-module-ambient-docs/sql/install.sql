-- ============================================================
-- Ambient Clinical Documentation Module
-- Database Tables
-- Run this once to set up all required tables
-- ============================================================

-- ── Table 1: Recording Sessions ──────────────────────────────
-- Tracks each recording session (one per encounter visit)
CREATE TABLE IF NOT EXISTS `ambient_recording_sessions` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `encounter_id`    INT NOT NULL              COMMENT 'OpenEMR encounter ID',
    `patient_id`      INT NOT NULL              COMMENT 'OpenEMR patient ID (pid)',
    `provider_id`     INT NOT NULL              COMMENT 'OpenEMR provider user ID',
    `started_at`      DATETIME NOT NULL         COMMENT 'When recording started',
    `ended_at`        DATETIME DEFAULT NULL     COMMENT 'When recording stopped',
    `duration_seconds` INT DEFAULT NULL         COMMENT 'Total recording duration',
    `status`          ENUM(
                        'recording',
                        'processing',
                        'complete',
                        'error'
                      ) DEFAULT 'recording'    COMMENT 'Current session status',
    `error_message`   TEXT DEFAULT NULL         COMMENT 'Error details if status=error',
    `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_encounter`  (`encounter_id`),
    INDEX `idx_patient`    (`patient_id`),
    INDEX `idx_provider`   (`provider_id`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='One row per ambient recording session';


-- ── Table 2: AI Generated Notes ──────────────────────────────
-- Stores the raw transcript + AI-generated SOAP note
-- PHI is re-inserted AFTER LLM call — this stores the final version
CREATE TABLE IF NOT EXISTS `ambient_ai_notes` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`           INT UNSIGNED NOT NULL,
    `encounter_id`         INT NOT NULL,
    `raw_transcript`       LONGTEXT DEFAULT NULL  COMMENT 'Full transcript text',
    `diarized_transcript`  LONGTEXT DEFAULT NULL  COMMENT 'JSON: [{speaker,text,start_ms,end_ms}]',
    `ai_soap_note`         LONGTEXT DEFAULT NULL  COMMENT 'JSON: full SOAP structure',
    `confidence_scores`    TEXT DEFAULT NULL      COMMENT 'JSON: {section: 0.0-1.0}',
    `snomed_codes`         TEXT DEFAULT NULL      COMMENT 'JSON: extracted SNOMED codes',
    `icd10_codes`          TEXT DEFAULT NULL      COMMENT 'JSON: extracted ICD-10 codes',
    `model_version`        VARCHAR(50) DEFAULT NULL COMMENT 'e.g. azure-gpt-4o',
    `prompt_hash`          VARCHAR(64) DEFAULT NULL COMMENT 'SHA256 of system+user prompt',
    `processing_ms`        INT DEFAULT NULL       COMMENT 'AI processing time in ms',
    `created_at`           DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`session_id`)
        REFERENCES `ambient_recording_sessions`(`id`)
        ON DELETE CASCADE,
    INDEX `idx_encounter`  (`encounter_id`),
    INDEX `idx_session`    (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AI-generated SOAP notes before clinician edits';


-- ── Table 3: Clinician Final Notes ───────────────────────────
-- Stores what the clinician actually accepted/edited/rejected
CREATE TABLE IF NOT EXISTS `ambient_clinician_notes` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ai_note_id`       INT UNSIGNED NOT NULL,
    `encounter_id`     INT NOT NULL,
    `clinician_id`     INT NOT NULL           COMMENT 'OpenEMR user ID of clinician',
    `final_note`       LONGTEXT DEFAULT NULL  COMMENT 'JSON: final accepted SOAP note',
    `action`           ENUM(
                         'accept_all',
                         'partial_edit',
                         'reject'
                       ) NOT NULL             COMMENT 'What clinician did overall',
    `section_actions`  TEXT DEFAULT NULL      COMMENT 'JSON: {section: accept|modify|reject}',
    `edit_distance`    INT DEFAULT NULL       COMMENT 'Levenshtein distance from AI original',
    `edit_percent`     DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage of text changed',
    `saved_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`ai_note_id`)
        REFERENCES `ambient_ai_notes`(`id`)
        ON DELETE CASCADE,
    INDEX `idx_encounter`  (`encounter_id`),
    INDEX `idx_clinician`  (`clinician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clinician final decisions on AI-generated notes';


-- ── Table 4: Audit Log ────────────────────────────────────────
-- HIPAA-required: every AI inference + clinician action logged
-- This table should NEVER be deleted or truncated
CREATE TABLE IF NOT EXISTS `ambient_audit_log` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`       INT UNSIGNED DEFAULT NULL,
    `encounter_id`     INT DEFAULT NULL,
    `event_type`       VARCHAR(50) NOT NULL   COMMENT 'recording_start|stt_complete|note_generated|clinician_action|error',
    `event_details`    TEXT DEFAULT NULL      COMMENT 'JSON: any extra context',
    `model_version`    VARCHAR(50) DEFAULT NULL,
    `prompt_hash`      VARCHAR(64) DEFAULT NULL,
    `clinician_action` VARCHAR(20) DEFAULT NULL COMMENT 'accept|modify|reject|accept_all',
    `edit_distance`    INT DEFAULT NULL,
    `duration_ms`      INT DEFAULT NULL,
    `ip_address`       VARCHAR(45) DEFAULT NULL,
    `user_id`          INT DEFAULT NULL,
    `user_name`        VARCHAR(100) DEFAULT NULL,
    `logged_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_session`    (`session_id`),
    INDEX `idx_encounter`  (`encounter_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_user`       (`user_id`),
    INDEX `idx_logged_at`  (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='HIPAA audit trail - never delete rows from this table';