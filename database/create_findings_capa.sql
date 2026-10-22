CREATE TABLE IF NOT EXISTS findings_capa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    findings TEXT NOT NULL,
    corrective_actions TEXT NOT NULL,
    deadline DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_findings_office FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
