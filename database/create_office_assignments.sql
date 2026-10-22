CREATE TABLE IF NOT EXISTS office_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auditor_id INT NOT NULL,
    office_id INT NOT NULL,
    activity ENUM('Internal Audit', 'External Audit', 'Follow-up Audit') NOT NULL DEFAULT 'Internal Audit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignment_auditor FOREIGN KEY (auditor_id) REFERENCES auditors(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_office FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    CONSTRAINT uq_auditor_office UNIQUE (auditor_id, office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
