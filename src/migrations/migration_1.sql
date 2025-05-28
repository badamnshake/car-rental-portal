-- Alter tblusers to add auth_type, oauth_token, and token_expiry
ALTER TABLE tblusers 
ADD COLUMN auth_type ENUM('googleoauth', 'legacy') NOT NULL DEFAULT 'legacy',
ADD COLUMN oauth_id TEXT NULL,
ADD COLUMN oauth_token TEXT NULL,
ADD COLUMN token_expiry DATETIME NULL;


-- Update existing users who have an OAuth token
UPDATE tblusers 
SET auth_type = 'googleoauth' 
WHERE oauth_token IS NOT NULL;

-- Ensure all other users are explicitly marked as 'legacy'
UPDATE tblusers 
SET auth_type = 'legacy' 
WHERE oauth_token IS NULL;

