-- Run this ONCE on the DB host (192.168.254.103) so phpMyAdmin on other laptops
-- can use the "control user" (pma) and the "Connection for controluser" error goes away.
-- Uses no password to match XAMPP's default config.

CREATE USER IF NOT EXISTS 'pma'@'%' IDENTIFIED BY '';
GRANT SELECT, INSERT, UPDATE, DELETE ON phpmyadmin.* TO 'pma'@'%';
FLUSH PRIVILEGES;
