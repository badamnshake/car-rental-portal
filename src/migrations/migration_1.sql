-- Alter tblusers to add auth_type, oauth_token, and token_expiry
ALTER TABLE tblusers 
ADD COLUMN auth_type ENUM('googleoauth', 'legacy') NOT NULL DEFAULT 'legacy',
ADD COLUMN oauth_id VARCHAR(255) NULL,
ADD COLUMN oauth_token VARCHAR(255) NULL,
ADD COLUMN token_expiry DATETIME NULL;


-- Update existing users who have an OAuth token
UPDATE tblusers 
SET auth_type = 'googleoauth' 
WHERE oauth_token IS NOT NULL;

-- Ensure all other users are explicitly marked as 'legacy'
UPDATE tblusers 
SET auth_type = 'legacy' 
WHERE oauth_token IS NULL;

-- Create passport_verification table for PassportEye verified fields
  CREATE TABLE passport_verification (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      passport_number VARCHAR(20) NOT NULL,
      full_name VARCHAR(255) NOT NULL,
      date_of_birth DATE NOT NULL,
      nationality VARCHAR(100) NOT NULL,
      passport_expiry DATE NOT NULL,
      verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES tblusers(id) ON DELETE CASCADE
  );

