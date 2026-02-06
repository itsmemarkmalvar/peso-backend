-- Run this once on the DB host to allow other laptops to connect.
-- Option A: allow root from any machine on the network (dev only)
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
