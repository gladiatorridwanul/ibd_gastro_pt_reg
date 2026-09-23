-- =============================================
-- Patient Attachments Table Installation Script
-- =============================================

-- Check if table exists and alter if needed
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alter_patient_attachments`$$

CREATE PROCEDURE `sp_alter_patient_attachments`()
BEGIN
    -- Create table if not exists
    CREATE TABLE IF NOT EXISTS `patient_attachments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `patient_id` int(11) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `original_name` varchar(255) NOT NULL,
        `file_path` varchar(500) NOT NULL,
        `file_size` int(11) NOT NULL,
        `file_type` varchar(50) NOT NULL,
        `mime_type` varchar(100) DEFAULT NULL,
        `description` text,
        `uploaded_by` int(11) NOT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    -- Add indexes if they don't exist
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND INDEX_NAME = 'idx_patient_id') THEN
        ALTER TABLE `patient_attachments` ADD INDEX `idx_patient_id` (`patient_id`);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND INDEX_NAME = 'idx_uploaded_by') THEN
        ALTER TABLE `patient_attachments` ADD INDEX `idx_uploaded_by` (`uploaded_by`);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND INDEX_NAME = 'idx_uploaded_at') THEN
        ALTER TABLE `patient_attachments` ADD INDEX `idx_uploaded_at` (`uploaded_at`);
    END IF;
    
    -- Add foreign key constraints if they don't exist
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND CONSTRAINT_NAME = 'fk_patient_attachments_patient') THEN
        ALTER TABLE `patient_attachments` 
        ADD CONSTRAINT `fk_patient_attachments_patient` 
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND CONSTRAINT_NAME = 'fk_patient_attachments_user') THEN
        ALTER TABLE `patient_attachments` 
        ADD CONSTRAINT `fk_patient_attachments_user` 
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
    END IF;
    
    -- Add new columns if they don't exist (for future upgrades)
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND COLUMN_NAME = 'is_deleted') THEN
        ALTER TABLE `patient_attachments` 
        ADD COLUMN `is_deleted` tinyint(1) NOT NULL DEFAULT 0 AFTER `uploaded_at`,
        ADD INDEX `idx_is_deleted` (`is_deleted`);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND COLUMN_NAME = 'deleted_at') THEN
        ALTER TABLE `patient_attachments` 
        ADD COLUMN `deleted_at` timestamp NULL DEFAULT NULL AFTER `is_deleted`;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'patient_attachments' 
                   AND COLUMN_NAME = 'version') THEN
        ALTER TABLE `patient_attachments` 
        ADD COLUMN `version` int(11) NOT NULL DEFAULT 1 AFTER `deleted_at`;
    END IF;
    
    -- Update table comment
    ALTER TABLE `patient_attachments` 
    COMMENT = 'Patient medical documents and attachments v2.0';
    
END$$

DELIMITER ;

-- Execute the alter procedure
CALL sp_alter_patient_attachments();

-- Drop the procedure after execution
DROP PROCEDURE IF EXISTS `sp_alter_patient_attachments`;

-- Insert default permissions if they don't exist
INSERT IGNORE INTO `permissions` (`name`, `module`, `description`) VALUES
('button.module.add', 'documents', 'Permission to upload documents'),
('button.module.view', 'documents', 'Permission to view documents'),
('button.module.edit', 'documents', 'Permission to edit documents'),
('button.module.delete', 'documents', 'Permission to delete documents'),
('button.module.download', 'documents', 'Permission to download documents');

-- Insert sample data for testing (optional)
-- INSERT INTO `patient_attachments` 
-- (`patient_id`, `file_name`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `description`, `uploaded_by`) 
-- SELECT p.id, 'sample.pdf', 'sample.pdf', 'uploads/patient_' + p.id + '/sample.pdf', 1024, 'pdf', 'application/pdf', 'Sample document', u.id
-- FROM patients p, users u WHERE u.username = 'admin' LIMIT 1;