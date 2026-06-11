#IfNotTable patient_dashboard_settings
CREATE TABLE `patient_dashboard_settings` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_val` TEXT,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
#EndIf

#IfNotRow patient_dashboard_settings setting_key module_version
INSERT INTO `patient_dashboard_settings` (`setting_key`, `setting_val`)
VALUES ('module_version', '1.0.0');
#EndIf
