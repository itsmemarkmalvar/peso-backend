# phpMyAdmin: "Host 'DESKTOP-...' is not allowed to connect" — Step-by-step fix

Use this guide when phpMyAdmin shows an **Access denied** or **Host not allowed** error after the DB server laptop is set up for shared development (phpMyAdmin connecting to the server IP).

---

## 1. When to use this guide

You see one or more of these when opening **http://localhost/phpmyadmin** on the **DB server laptop** (the machine that runs XAMPP MySQL for the team):

- **"MySQL said: Cannot connect: invalid settings."**
- **"mysqli::real_connect(): (HY000/1130): Host 'DESKTOP-XXXXX' is not allowed to connect to this MariaDB server."**
- **"Access denied!"** (phpMyAdmin page title)

The hostname (e.g. `DESKTOP-JQ5EEU2`) is your Windows **Device name**. This happens because phpMyAdmin is configured to connect to the server IP (e.g. `192.168.254.103`). When you open phpMyAdmin on that same machine, MariaDB sees the client as your PC’s hostname, not `localhost`, and only `root`@'localhost' exists by default.

---

## 2. Prerequisites

- **XAMPP MySQL must be running** (green in XAMPP Control Panel).
- You are on the **DB server laptop** (the one that runs MySQL for the team).
- You have the project path (e.g. `C:\xampp\htdocs\PESO\peso-backend`).

---

## 3. Step-by-step fix

### Step 1: Find your Windows hostname (if you need to customize the script)

1. Press **Win + I** to open **Settings**.
2. Go to **System** → **About**.
3. Under **Device name**, note the value (e.g. `DESKTOP-JQ5EEU2`).

If it matches the hostname in the error message, you can use the project’s SQL script as-is. If not, you’ll use your hostname in Step 4 (Option B or C).

---

### Step 2: Open a terminal

- **Option A:** In XAMPP Control Panel, click **Shell**.
- **Option B:** Open **PowerShell** or **Command Prompt** (no need to run as Administrator).

---

### Step 3: Confirm MySQL is reachable

Run:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT 1;"
```

- If you see a table with `1`, MySQL is running and you can proceed.
- If you get "Access denied" or "Can't connect", fix MySQL (start it from XAMPP or check password) before continuing.

---

### Step 4: Allow `root` from your hostname and from any host

Use **one** of the options below.

#### Option A — Run the project SQL script (recommended)

In the same terminal (PowerShell or CMD):

```powershell
Get-Content "C:\xampp\htdocs\PESO\peso-backend\setup_mysql_allow_server_hostname.sql" | C:\xampp\mysql\bin\mysql.exe -u root
```

If your project is not under `C:\xampp\htdocs\PESO\peso-backend`, change the path to where `setup_mysql_allow_server_hostname.sql` lives.

**If you see an error only on `FLUSH PRIVILEGES`** (e.g. "Read page with wrong checksum" from storage engine Aria), the **CREATE USER** and **GRANT** commands have usually already run. Skip to Step 5 and try phpMyAdmin; if it still fails, do Step 5b (restart MySQL).

---

#### Option B — One-line command (replace hostname if needed)

In **PowerShell**:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE USER IF NOT EXISTS 'root'@'DESKTOP-JQ5EEU2' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'DESKTOP-JQ5EEU2' WITH GRANT OPTION; CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;"
```

Replace **`DESKTOP-JQ5EEU2`** with your actual Device name from Step 1 if it is different.

---

#### Option C — MySQL Shell and script (or copy-paste)

1. Start the MySQL client:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root
   ```

2. At the `MariaDB [(none)]>` prompt, either run the script:

   ```sql
   source C:/xampp/htdocs/PESO/peso-backend/setup_mysql_allow_server_hostname.sql
   ```

   Or copy-paste and run these (replace `DESKTOP-JQ5EEU2` with your hostname if different):

   ```sql
   CREATE USER IF NOT EXISTS 'root'@'DESKTOP-JQ5EEU2' IDENTIFIED BY '';
   GRANT ALL PRIVILEGES ON *.* TO 'root'@'DESKTOP-JQ5EEU2' WITH GRANT OPTION;
   CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
   GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
   FLUSH PRIVILEGES;
   ```

3. Type `exit` and press Enter.

---

### Step 5: Verify phpMyAdmin

1. Open a browser and go to: **http://localhost/phpmyadmin**
2. You should see the phpMyAdmin interface and your databases (e.g. `peso`) without the "Host ... is not allowed" error.

---

### Step 5b: If phpMyAdmin still fails or FLUSH PRIVILEGES errored

1. **Restart MySQL** in XAMPP Control Panel: click **Stop** for MySQL, then **Start**.
2. Try **http://localhost/phpmyadmin** again.

If it still fails, double-check that the hostname in the SQL matches your Device name (Step 1) and that you ran the commands on the **DB server** machine (where MySQL is running).

---

## 4. If your hostname is different

The project script `setup_mysql_allow_server_hostname.sql` uses **`DESKTOP-JQ5EEU2`**. If your PC’s Device name is different:

1. Open `peso-backend\setup_mysql_allow_server_hostname.sql` in an editor.
2. Replace every **`DESKTOP-JQ5EEU2`** with your **Device name** (from Settings → System → About).
3. Save the file, then run Option A or C again.

---

## 5. What these commands do

| Command | Purpose |
|--------|--------|
| `CREATE USER 'root'@'DESKTOP-JQ5EEU2'` | Allows `root` when connecting from this laptop (so phpMyAdmin on the server can connect to the server IP). |
| `CREATE USER 'root'@'%'` | Allows `root` from any host (so other dev laptops can connect to this MySQL server). |
| `GRANT ... WITH GRANT OPTION` | Gives full privileges to those users. |
| `FLUSH PRIVILEGES` | Reloads privileges (optional if you get an Aria error; restarting MySQL can help instead). |

---

## 6. Related files and docs

- **SQL script:** `peso-backend\setup_mysql_allow_server_hostname.sql`
- **Shared DB setup:** `peso-backend\SHARED_DATABASE_SETUP.md` (section "C2. Fix phpMyAdmin 'Host DESKTOP-...' is not allowed")
- **Remote MySQL user (other laptops):** `peso-backend\setup_remote_mysql_user.sql`

---

## 7. Quick reference (copy-paste)

**PowerShell — run script:**

```powershell
Get-Content "C:\xampp\htdocs\PESO\peso-backend\setup_mysql_allow_server_hostname.sql" | C:\xampp\mysql\bin\mysql.exe -u root
```

**PowerShell — one-liner (change hostname if needed):**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE USER IF NOT EXISTS 'root'@'DESKTOP-JQ5EEU2' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'DESKTOP-JQ5EEU2' WITH GRANT OPTION; CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;"
```

Then open **http://localhost/phpmyadmin**. If it still fails, restart MySQL in XAMPP and try again.
