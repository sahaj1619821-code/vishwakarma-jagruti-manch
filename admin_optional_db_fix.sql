-- VJM Admin panel optional database fixes

-- Ensure users roles exist
INSERT INTO roles (role_name, role_key, status) VALUES
('Super Admin', 'super_admin', 'active'),
('Hostel Admin', 'hostel_admin', 'active'),
('Hostel Manager', 'hostel_manager', 'active'),
('Matrimonial Admin', 'matrimonial_admin', 'active'),
('eBook Admin', 'ebook_admin', 'active'),
('Gallery Admin', 'gallery_admin', 'active'),
('Temple Admin', 'temple_admin', 'active'),
('Address Book Admin', 'address_admin', 'active'),
('Enquiry Admin', 'enquiry_admin', 'active'),
('Village Surveyor', 'village_surveyor', 'active')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), status = VALUES(status);

-- Public matrimonial link fix if needed
ALTER TABLE matrimonial_users ADD COLUMN user_id INT NULL AFTER id;
