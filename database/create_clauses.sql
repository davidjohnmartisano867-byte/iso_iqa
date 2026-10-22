USE iso_iqa;

CREATE TABLE IF NOT EXISTS clauses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clause_number INT NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO clauses (clause_number, title) VALUES
(1, 'Scope'),
(2, 'Normative References'),
(3, 'Terms & Definitions'),
(4, 'Context of the Organization'),
(5, 'Leadership'),
(6, 'Planning'),
(7, 'Support'),
(8, 'Operation'),
(9, 'Performance Evaluation'),
(10, 'Improvement');

ALTER TABLE documents ADD CONSTRAINT fk_document_clause FOREIGN KEY (clause) REFERENCES clauses(clause_number) ON DELETE RESTRICT;
