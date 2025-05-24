
-- Alter tblusers to add verification-related columns
ALTER TABLE tblusers 
ADD COLUMN is_verified BOOL DEFAULT 0,
ADD COLUMN verification_pending BOOL DEFAULT 0,
ADD COLUMN verification_attempts INT DEFAULT 0;

-- Create user_photos table
CREATE TABLE tbluserphotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    photo_base64 LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_user_photos_user
        FOREIGN KEY (user_id)
        REFERENCES tblusers(id)
        ON DELETE CASCADE
);


ALTER TABLE tbluserphotos
ADD COLUMN aes_key TEXT,
ADD COLUMN iv TEXT;



