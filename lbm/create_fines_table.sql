-- Create or update the fines table structure
-- Run this if the fines table doesn't exist or needs to be updated

CREATE TABLE IF NOT EXISTS fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    fine_amount DECIMAL(10, 2) NOT NULL,
    days_late INT NOT NULL,
    status ENUM('Pending', 'Paid') DEFAULT 'Pending',
    fine_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_date TIMESTAMP NULL,
    FOREIGN KEY (issue_id) REFERENCES issued_books(issue_id) ON DELETE CASCADE
);

-- If the table already exists but is missing columns, add them:
-- ALTER TABLE fines ADD COLUMN status ENUM('Pending', 'Paid') DEFAULT 'Pending';
-- ALTER TABLE fines ADD COLUMN fine_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE fines ADD COLUMN payment_date TIMESTAMP NULL;

-- Update existing records to have 'Pending' status if status column was just added
-- UPDATE fines SET status = 'Pending' WHERE status IS NULL;