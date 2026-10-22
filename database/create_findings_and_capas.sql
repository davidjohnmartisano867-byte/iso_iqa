DROP TABLE IF EXISTS capas;
DROP TABLE IF EXISTS findings;

CREATE TABLE IF NOT EXISTS findings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    document_id INT NULL DEFAULT NULL,
    finding_type VARCHAR(100) NOT NULL,
    findings TEXT NOT NULL,
    deadline DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_finding_office FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS capas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    finding_id INT NOT NULL,
    root_cause TEXT NULL,
    corrective_actions TEXT NOT NULL,
    deadline DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_capa_finding FOREIGN KEY (finding_id) REFERENCES findings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

