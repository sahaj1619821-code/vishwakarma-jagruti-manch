-- Optional SQL check/fix for public matrimonial flow
-- Run only if needed in phpMyAdmin

ALTER TABLE matrimonial_users ADD COLUMN user_id INT NULL AFTER id;

-- Example: link public login user_id 2 to matrimonial profile id 4
-- UPDATE matrimonial_users SET user_id = 2, status='approved', verification_status='admin_approved', verified=1 WHERE id = 4;

-- Check
-- SELECT id, user_id, full_name, mobile, status, verification_status, verified FROM matrimonial_users ORDER BY id DESC;
