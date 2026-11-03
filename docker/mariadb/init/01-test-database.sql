-- doctrine.yaml appends "_test" to the database name in the test environment,
-- so the test database must exist alongside the development one.
CREATE DATABASE IF NOT EXISTS `app_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Parallel runs (ParaTest workers, Infection threads) set TEST_TOKEN and use
-- one database each: app_test1, app_test2... tests/bootstrap.php creates and
-- migrates them on demand, so the app user needs rights on the whole family.
GRANT ALL PRIVILEGES ON `app\_test%`.* TO 'app'@'%';

FLUSH PRIVILEGES;
