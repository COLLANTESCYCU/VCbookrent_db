-- Seed test users for development
-- Passwords are hashed using PASSWORD_DEFAULT (bcrypt)

-- Admin user: admin@test.com / admin123
INSERT INTO users (name, email, password_hash, role, status, contact, address) 
VALUES ('Admin User', 'admin@test.com', '$2y$10$RFCUEwTLBo.g9lWZdCo3GusZWgLKvL7Iq6q6DIxKHVN0tXEZaVVAe', 'admin', 'active', '09123456789', 'Admin Address')
ON DUPLICATE KEY UPDATE password_hash='$2y$10$RFCUEwTLBo.g9lWZdCo3GusZWgLKvL7Iq6q6DIxKHVN0tXEZaVVAe';

-- Staff user: staff@test.com / staff123
INSERT INTO users (name, email, password_hash, role, status, contact, address) 
VALUES ('Staff User', 'staff@test.com', '$2y$10$nPYRIk2X5c6r9sL3mQvV0eVmZjVLF6F9bN0cP7hJ1fW5dT2xK1LFK', 'staff', 'active', '09123456788', 'Staff Address')
ON DUPLICATE KEY UPDATE password_hash='$2y$10$nPYRIk2X5c6r9sL3mQvV0eVmZjVLF6F9bN0cP7hJ1fW5dT2xK1LFK';

-- Regular user: user@test.com / user123
INSERT INTO users (name, email, password_hash, role, status, contact, address) 
VALUES ('Regular User', 'user@test.com', '$2y$10$XHqYrBUn5xQzF2pGs8kVJOp7mD.9D7n4jJ5hK2wL1vM3oN6aR9sB.', 'user', 'active', '09123456787', 'User Address')
ON DUPLICATE KEY UPDATE password_hash='$2y$10$XHqYrBUn5xQzF2pGs8kVJOp7mD.9D7n4jJ5hK2wL1vM3oN6aR9sB.';
