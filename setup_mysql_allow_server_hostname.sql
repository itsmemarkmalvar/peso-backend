-- Run this ONCE on the DB server laptop when phpMyAdmin shows:
-- "Host 'DESKTOP-JQ5EEU2' is not allowed to connect to this MariaDB server"
--
-- Use XAMPP MySQL Shell (or: C:\xampp\mysql\bin\mysql.exe -u root)
-- then: source C:\xampp\htdocs\PESO\peso-backend\setup_mysql_allow_server_hostname.sql
-- Or copy-paste the lines below into the MySQL shell.

-- Allow root from this machine's hostname (so phpMyAdmin on the server can connect to 192.168.254.103)
CREATE USER IF NOT EXISTS 'root'@'DESKTOP-JQ5EEU2' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'DESKTOP-JQ5EEU2' WITH GRANT OPTION;

-- Allow root from any host (for other dev laptops connecting to this server)
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;
