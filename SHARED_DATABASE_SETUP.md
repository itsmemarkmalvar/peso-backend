# Shared database setup for dev team (5 laptops on same WiFi)

## 1. Choose the database host

Pick **one laptop** to run MySQL (e.g. the one that already has XAMPP). That machine is the "DB server." The other 4 will connect to it over the network.

---

## 2. On the DB server laptop

### A. Get this laptop’s IP address

In PowerShell or CMD:

```bash
ipconfig
```

Find **IPv4 Address** under your WiFi adapter. For this team it is **`192.168.254.103`** — everyone else will use it as `DB_HOST`.

### B. Make MySQL accept connections from other PCs (XAMPP)

1. Open: `C:\xampp\mysql\bin\my.ini`
2. Find the line: `bind-address = 127.0.0.1`
3. Change it to: `bind-address = 0.0.0.0`  
   (or comment it out with `#`)
4. Save the file and **restart MySQL** from XAMPP Control Panel.

### C. Allow MySQL user to connect from other machines

By default, MySQL only allows `root` to connect from **this laptop** (localhost). To let the other 4 laptops connect, you must allow a user to connect from **any machine on the network** (that’s what `'%'` means).

**Where to run the SQL:** On the DB server laptop, open **XAMPP → MySQL → Shell**, or go to **http://localhost/phpmyadmin** and open the **SQL** tab. Then run **one** of the options below.

---

**Option A — Keep using root (simplest)**  
No password, same as your current setup. Run this:

```sql
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

- **On all 5 laptops** (including the DB server), keep in `.env`:  
  `DB_USERNAME=root` and `DB_PASSWORD=` (empty).  
- No other change needed.

---

**Option B — Use a separate dev user (more secure)**  
Only if you want a dedicated user for shared dev (e.g. not using root). Replace `your_password` with a real password, then run:

```sql
CREATE USER 'pesodev'@'%' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON peso.* TO 'pesodev'@'%';
FLUSH PRIVILEGES;
```

- **On all 5 laptops**, set in `.env`:  
  `DB_USERNAME=pesodev` and `DB_PASSWORD=your_password`.  
- Everyone must use the same password.

### C2. Fix phpMyAdmin "Host 'DESKTOP-...' is not allowed" on the server

**→ Full step-by-step guide:** see **`PHPMYADMIN_HOST_NOT_ALLOWED_FIX.md`** in this folder (use it when this error happens again).

When phpMyAdmin is set to connect to the server IP (`192.168.254.103`), opening it **on this same laptop** makes MariaDB see the client as the machine hostname (e.g. `DESKTOP-JQ5EEU2`). By default only `root`@'localhost' exists, so you get:

- **MySQL said: Cannot connect: invalid settings.**
- **Host 'DESKTOP-JQ5EEU2' is not allowed to connect to this MariaDB server.**

**Fix (run once on the DB server):**

1. Open **XAMPP Control Panel** → click **Shell** (or open CMD/PowerShell).
2. Run MySQL as root (no password):
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root
   ```
3. Run the project script (replace with your actual hostname if different):
   ```sql
   source C:/xampp/htdocs/PESO/peso-backend/setup_mysql_allow_server_hostname.sql
   ```
   Or copy-paste the contents of `setup_mysql_allow_server_hostname.sql` into the MySQL prompt.
4. Type `exit` and press Enter.
5. Open **http://localhost/phpmyadmin** again — it should connect.

The script adds `root`@'DESKTOP-JQ5EEU2' (so this laptop’s phpMyAdmin can connect) and `root`@'%' (so other team laptops can connect). If your PC hostname is different, edit the script and replace `DESKTOP-JQ5EEU2` with your hostname (see it in Windows: **Settings → System → About → Device name**).

### D. Windows Firewall

Allow MySQL so other laptops can reach port 3306:

1. **Windows Defender Firewall** → **Advanced settings**
2. **Inbound Rules** → **New Rule**
3. **Port** → **TCP** → **3306**
4. **Allow the connection** → apply to **Private** (and Domain if you use it)
5. Name it e.g. "MySQL dev"

Or in PowerShell **as Administrator**:

```powershell
New-NetFirewallRule -DisplayName "MySQL dev" -Direction Inbound -Protocol TCP -LocalPort 3306 -Action Allow -Profile Private
```

---

## 3. On the other 4 laptops (clients)

1. **Do not run MySQL** on these machines (or stop it so it doesn’t conflict).
2. In the project’s **`.env`**, set the database host to the **DB server’s IP**:

```env
DB_CONNECTION=mysql
DB_HOST=192.168.254.103
DB_PORT=3306
DB_DATABASE=peso
DB_USERNAME=root
DB_PASSWORD=
```

Use `192.168.254.103` (the DB server IP for this team).

3. Run:

```bash
php artisan config:clear
```

4. **Clear config cache** (required after changing `.env`):

```bash
php artisan config:clear
```

5. **Verify you're on the shared database** (run this before any migrate commands):

```bash
php artisan tinker --execute="echo 'DB Host: ' . config('database.connections.mysql.host') . PHP_EOL . 'DB Name: ' . config('database.connections.mysql.database');"
```

You must see **DB Host: 192.168.254.103** and **DB Name: peso**. If you see `127.0.0.1` or another host, you are still on a local database.

6. Test:

```bash
php artisan migrate:status
```

If that runs without errors, this laptop is using the shared database.

---

### Make phpMyAdmin show the same database on all laptops

By default, phpMyAdmin on each laptop connects to **that laptop’s local MySQL** (127.0.0.1), so you see different data in phpMyAdmin. To have **everyone’s phpMyAdmin** open the **shared** database (same as Laravel):

**On all 5 laptops** (including the host), edit phpMyAdmin’s config:

1. Open: **`C:\xampp\phpMyAdmin\config.inc.php`**
2. Find the line:  
   `$cfg['Servers'][$i]['host'] = '127.0.0.1';`
3. Change it to:  
   `$cfg['Servers'][$i]['host'] = '192.168.254.103';`
4. Save the file.

Then when anyone opens **http://localhost/phpmyadmin**, they will connect to the shared MySQL on the host and see the same databases (including `peso` with the same seeded data).

**Note:** The host laptop (192.168.254.103) is the one that must have MySQL running in XAMPP. The other 4 do not need MySQL running for phpMyAdmin to work—they will connect to the host.

**If you get "Access denied for user 'pma'@'...' / Connection for controluser failed":**  
You can either create the `pma` user on the host (see `setup_phpmyadmin_pma_remote.sql`) or **disable the control user** so the error goes away.

**Option A – Disable control user (recommended if SQL fails with Aria error):**  
On **each laptop** (all 5), edit **`C:\xampp\phpMyAdmin\config.inc.php`**. Comment out the block that starts with `$cfg['Servers'][$i]['controluser']` through `...['favorite']` (put `//` in front of each of those lines). Save. Then phpMyAdmin no longer uses the `pma` user; browsing, SQL, and export/import still work. The host’s config was already updated this way.

**Option B – Create pma@'%' on the host:**  
Run the SQL in `setup_phpmyadmin_pma_remote.sql` in phpMyAdmin → SQL. If you get error **#1030 "Read page with wrong checksum" (Aria)**, use Option A instead.

---

### Troubleshooting: "I changed DB_HOST and ran config:clear but migrate still uses my local DB"

On the **client laptop** (the one that should connect to the host), run these in **Command Prompt or PowerShell** from the **`peso-backend`** folder (the folder that contains `.env` and `artisan`):

1. **Confirm you're in the right folder** (must contain `.env` and `artisan`):

   ```powershell
   cd path\to\PESO\peso-backend
   dir .env
   ```

2. **Remove any cached config** (in case something re-cached it):

   ```powershell
   php artisan config:clear
   if (Test-Path bootstrap\cache\config.php) { Remove-Item bootstrap\cache\config.php }
   ```

3. **See what Laravel is actually using** (copy the full output and share it):

   ```powershell
   php artisan tinker --execute="print_r(['host'=>config('database.connections.mysql.host'), 'database'=>config('database.connections.mysql.database')]);"
   ```

   You **must** see `host => 192.168.254.103`. If you see `127.0.0.1`, Laravel is still not reading your `.env` (wrong folder, or `.env` not saved, or another `.env` is being used).

4. **On client laptops, stop local MySQL** (XAMPP → Stop MySQL) so only the host runs MySQL. That avoids confusion and ensures connections go to the host.

---

### Proof test: “Am I really on the shared database?”

After the host runs `migrate:fresh --seed`, everyone should see the **same** data. If a teammate still sees different or old data, their app is still using their **local** database.

**Step 1 – Run the DB check route on each laptop**

On each laptop (host and all 4 teammates):

1. From the **`peso-backend`** folder, run: `php artisan serve`
2. In the browser open: **http://127.0.0.1:8000/db-check**

You should see JSON like:

```json
{
  "DB_HOST": "192.168.254.103",
  "DB_DATABASE": "peso",
  "users_count": 5,
  "expected_for_shared": "YES - connected to shared host"
}
```

- **If `DB_HOST` is `192.168.254.103`** and **`expected_for_shared` is YES** → that laptop’s Laravel is using the shared DB. After you run `migrate:fresh --seed` on the host, this laptop will see the new seeded data.
- **If `DB_HOST` is `127.0.0.1`** (or anything else) → that laptop is still on its **local** database. Fix: on that laptop, set `DB_HOST=192.168.254.103` in **`peso-backend\.env`**, run `php artisan config:clear`, **stop local MySQL** in XAMPP, then open **http://127.0.0.1:8000/db-check** again. It must show `192.168.254.103`.

**Step 2 – Compare counts after migrate:fresh --seed**

1. On the **host**, run: `php artisan migrate:fresh --seed`
2. On the host, open **http://127.0.0.1:8000/db-check** and note **`users_count`** (e.g. 5).
3. On each **teammate** laptop, open **http://127.0.0.1:8000/db-check** (with their own `php artisan serve` running).

If they are on the shared DB, **`users_count`** and **`DB_HOST`** must be the same as on the host. If a teammate has a different count or `DB_HOST` = `127.0.0.1`, that teammate is still on their local DB — fix `.env` and config on that laptop as above.

---

## 4. Optional: avoid committing the server IP

- Add a line in `.env.example`:

```env
# For shared dev DB, use the DB server’s IP (same WiFi)
DB_HOST=192.168.254.103
```

- Keep `.env` in `.gitignore` (Laravel default). Each dev copies `.env.example` to `.env` and sets `DB_HOST` to the agreed server IP.

---

## 5. Tips

- **DB server must be on and XAMPP MySQL running** whenever someone needs the DB.
- If the server’s IP changes (e.g. after reconnecting to WiFi), run `ipconfig` again and update `DB_HOST` on all client laptops.
- For a stable IP, you can set a **static IP** or **DHCP reservation** for the DB server in your router.
