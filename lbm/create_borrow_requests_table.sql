-- Create borrow_requests table for managing book borrow requests
CREATE TABLE IF NOT EXISTS borrow_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    book_id INT NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    admin_notes TEXT NULL,
    processed_by INT NULL,
    processed_date TIMESTAMP NULL,
    priority ENUM('Normal', 'High', 'Urgent') DEFAULT 'Normal',
    requested_due_date DATE NULL,
    FOREIGN KEY (member_id) REFERENCES member_db(member_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES member_db(member_id) ON DELETE SET NULL,
    INDEX idx_member_id (member_id),
    INDEX idx_book_id (book_id),
    INDEX idx_status (status),
    INDEX idx_request_date (request_date)
);

-- Add some sample data if needed
-- INSERT INTO borrow_requests (member_id, book_id, priority, requested_due_date) 
-- VALUES (1, 1, 'Normal', DATE_ADD(CURDATE(), INTERVAL 14 DAY));