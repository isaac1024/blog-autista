-- doctrine.yaml appends "_test" to the database name in the test environment,
-- so the test database must exist alongside the development one.
CREATE DATABASE IF NOT EXISTS `app_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `app_test`.* TO 'app'@'%';

FLUSH PRIVILEGES;
