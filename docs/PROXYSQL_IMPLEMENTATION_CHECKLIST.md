# ProxySQL Implementation Checklist – What to Change and Update

This document lists every change and update required to put ProxySQL in front of MySQL and point Laravel at it. No Laravel **code** changes are needed; only environment and infrastructure.

---

## 1. Infrastructure (Server)

### 1.1 Install ProxySQL

- **Where:** On the same host as the app, or on a dedicated host that the app can reach (e.g. same VPC).
- **How:** Depends on OS. Examples:
  - **Ubuntu/Debian:** `apt install proxysql` (or use official repo from ProxySQL).
  - **RHEL/CentOS:** `yum install proxysql` or repo from ProxySQL.
  - **Docker:** Use image `proxysql/proxysql` if you run MySQL/ProxySQL in containers.
- **Result:** ProxySQL binary and config (e.g. `/etc/proxysql.cnf` or `/etc/proxysql/proxysql.cnf`).

### 1.2 Decide Network Layout

Typical layout:

```
  Laravel (PHP-FPM + Artisan + Queue)  →  ProxySQL (host:port)  →  MySQL (host:port)
```

- **Option A – Same host:** ProxySQL listens on `127.0.0.1:6033` (default), MySQL on `127.0.0.1:3306`. Laravel will use `DB_HOST=127.0.0.1`, `DB_PORT=6033`.
- **Option B – ProxySQL on another host:** ProxySQL listens on `0.0.0.0:6033` (or a specific IP), MySQL on its own host. Laravel uses `DB_HOST=<proxysql_host>`, `DB_PORT=6033`. Ensure firewall allows app → ProxySQL and ProxySQL → MySQL.

Use the same host if possible to avoid extra network hops; use a separate host if you want to scale or isolate ProxySQL.

### 1.3 Configure ProxySQL

ProxySQL has an **admin interface** (port 6032 by default) and a **MySQL-compatible frontend** (port 6033) that Laravel will use.

**Backend (MySQL):** Tell ProxySQL where MySQL is and how to connect.

- Edit ProxySQL config or use the admin CLI (`mysql -u admin -p -h 127.0.0.1 -P 6032`).
- Add/update **mysql_servers** (replace with your real MySQL host/port if different):

```ini
# Example in proxysql.cnf or via ADMIN:

mysql_servers:
  (
    {
      address = "127.0.0.1"   # or your MySQL host
      port = 3306
      hostgroup = 0
      max_connections = 100   # max backend connections to this MySQL
    }
  )
```

- Add **mysql_user** so ProxySQL can log in to MySQL (use the **same** username/password Laravel uses, or a dedicated user for ProxySQL):

```ini
mysql_users:
  (
    {
      username = "your_db_user"      # same as DB_USERNAME in Laravel
      password = "your_db_password"  # same as DB_PASSWORD
      default_hostgroup = 0
      max_connections = 1000        # max frontend connections for this user (pooled into backend)
    }
  )
```

**Frontend (what Laravel connects to):** ProxySQL listens on port **6033** by default. No extra config needed for a single backend; just ensure the same `mysql_user` is loaded.

**Connection pool (important for “too many connections”):**

- **Backend:** `max_connections` per server (e.g. 50–100) caps how many real MySQL connections ProxySQL opens.
- **Frontend:** `max_connections` per user can be high (e.g. 1000); ProxySQL multiplexes these onto the backend pool.
- So: set **MySQL** `max_connections` high enough for ProxySQL’s backend pool (e.g. 200), and set **ProxySQL** backend `max_connections` to the value you want to cap at (e.g. 80). Laravel can open many “connections” to ProxySQL; MySQL only sees up to that cap.

**Load config and restart (example):**

```bash
# If using admin CLI:
LOAD MYSQL SERVERS TO RUNTIME; SAVE MYSQL SERVERS TO DISK;
LOAD MYSQL USERS TO RUNTIME; SAVE MYSQL USERS TO DISK;

# Restart ProxySQL if you changed config file:
systemctl restart proxysql
```

### 1.4 Firewall / Security

- Allow **app servers** to connect to ProxySQL **frontend port** (e.g. 6033).
- Allow **ProxySQL host** to connect to **MySQL** (e.g. 3306).
- Restrict **ProxySQL admin** (6032) to localhost or ops only; do not expose to the internet.

---

## 2. Laravel – What to Change

**No changes to PHP code or to `config/database.php`.** Laravel already reads `DB_HOST`, `DB_PORT`, etc. from the environment. You only change where that host/port point.

### 2.1 Environment (`.env`)

Update the **default MySQL connection** so it talks to ProxySQL instead of MySQL directly:

| Variable    | Before (direct MySQL) | After (via ProxySQL)     |
|------------|------------------------|--------------------------|
| `DB_HOST`  | MySQL host (e.g. `127.0.0.1` or `db.example.com`) | **ProxySQL host** (e.g. `127.0.0.1` or `proxysql.example.com`) |
| `DB_PORT`  | `3306`                | **`6033`** (ProxySQL frontend port) |
| `DB_DATABASE` | (unchanged)        | (unchanged)              |
| `DB_USERNAME` | (unchanged)        | Same user as configured in ProxySQL `mysql_users` |
| `DB_PASSWORD` | (unchanged)        | Same password as in ProxySQL `mysql_users` |

**Summary:** Only **two** values normally change: **`DB_HOST`** → ProxySQL host, **`DB_PORT`** → `6033`. The rest stay the same (and must match ProxySQL’s `mysql_users`).

### 2.2 If You Use a Second Connection (e.g. `master`)

Your `config/database.php` has a `master` connection using `MASTER_DB_HOST`, `MASTER_DB_PORT`, etc. If that connection also hits the same MySQL and you want it pooled:

- Point it at ProxySQL as well:
  - `MASTER_DB_HOST` → same ProxySQL host as `DB_HOST`
  - `MASTER_DB_PORT` → `6033`
- Ensure the user in ProxySQL `mysql_users` matches `MASTER_DB_USERNAME` / `MASTER_DB_PASSWORD`, or add a second user in ProxySQL for the master connection.

If `master` points at a **different** MySQL (e.g. another region), you can either put a second ProxySQL in front of that MySQL and point `MASTER_DB_*` to it, or leave `MASTER_DB_*` pointing at the other MySQL directly (no ProxySQL).

### 2.3 Optional: Document in `.env.example`

So future deployments know ProxySQL is an option, you can add a short comment and example in `.env.example`:

```env
DB_CONNECTION=mysql
# Direct MySQL (default):
# DB_HOST=127.0.0.1
# DB_PORT=3306
# Via ProxySQL (connection pooling):
# DB_HOST=127.0.0.1
# DB_PORT=6033
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

No code change; this is documentation only.

### 2.4 What Does **Not** Need to Change

- **`config/database.php`** – no edits. It already uses `env('DB_HOST')`, `env('DB_PORT')`, etc.
- **Queue workers, scheduler, Artisan** – no config change besides using the same `.env` (they already use the default DB connection).
- **Other env vars** – no change unless you use a second DB connection (see 2.2).

---

## 3. Deployment / Apply Steps

1. **Install and configure ProxySQL** (section 1). Verify from the app server:  
   `mysql -h <proxysql_host> -P 6033 -u <DB_USERNAME> -p<DB_PASSWORD> -e "SELECT 1"`  
   (or use a small PHP script that uses the same credentials as Laravel.)
2. **Update `.env`**: set `DB_HOST` and `DB_PORT=6033` (and optionally `MASTER_DB_*` if applicable).
3. **Restart PHP-FPM** (so web requests use new env): e.g. `systemctl restart php8.2-fpm` (adjust version).
4. **Restart queue workers and scheduler** (PM2 or systemd): e.g. `pm2 restart all` so they reload `.env` and use ProxySQL.
5. **Smoke test:** Open the app in the browser, run a few actions, run `php artisan migrate:status` (or a non-destructive Artisan command). Check ProxySQL stats (e.g. `SELECT * FROM stats_mysql_connection_pool;` on admin) to see connections.

---

## 4. Rollback (If You Need to Revert)

1. In `.env`, set **`DB_HOST`** back to the **MySQL** host and **`DB_PORT`** back to **`3306`** (and `MASTER_DB_*` if you changed them).
2. Restart PHP-FPM and PM2 (or whatever runs queue/scheduler).
3. No code or config file changes needed. Optionally stop or leave ProxySQL running; Laravel will no longer use it once env points to MySQL again.

---

## 5. Quick Reference – What Needs to Change

| Layer        | What to change |
|-------------|----------------|
| **Infrastructure** | Install ProxySQL; configure backend (MySQL address, port, user); configure frontend (port 6033); set connection pool limits; load config and start ProxySQL; firewall for 6033 (and 6032 admin). |
| **Laravel .env**   | `DB_HOST` → ProxySQL host; `DB_PORT` → `6033`. Optionally `MASTER_DB_HOST` / `MASTER_DB_PORT` if you pool the master connection. |
| **Laravel code**   | **Nothing.** |
| **Restart**        | PHP-FPM, queue workers, scheduler so they pick up new `.env`. |

After this, Laravel (web, Artisan, queue) will open connections to ProxySQL; ProxySQL will pool them and use a capped number of connections to MySQL, which is the right lever for “too many connections” during sync.
