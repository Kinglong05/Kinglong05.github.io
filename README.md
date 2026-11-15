# Portfolio CMS

A dark & gold developer portfolio with a full admin panel, built with plain PHP 8 + MySQL (no build step).

## Features

**Public site**: hero with stats, about, skills grid, services, projects (featured + "view all"), experience timeline, testimonials carousel, contact form. Responsive, animated, SEO/Open Graph tags.

**Admin panel** (`/admin`):
- Dashboard with unread messages, stats and a launch checklist
- Site Settings: name, logo, hero, about, CV upload, contact info, SEO, section on/off switches
- Add / edit / delete / reorder / hide: Projects, Skills, Services, Experience, Testimonials, Social links
- Image uploads (projects, portrait, logos, avatars) and PDF CV upload
- Messages inbox: search, read/unread, bulk delete, reply by email
- Account page: change username, email, password

**Security**: hashed passwords (bcrypt), CSRF tokens on every form, prepared SQL statements, login rate-limiting (5 tries / 15 min), session idle timeout, upload type checking (real MIME, not file extension), no script execution in `uploads/`, and blocked web access to `config/`, `includes/`, `database/`. The contact form also has a spam honeypot and a rate limit.

## Run locally (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `config/config.php` and set `DB_USER` / `DB_PASS` to your MySQL login.
3. Visit **http://localhost/PORTFOLIO/install.php** and create your admin account.
   This creates the `portfolio_db` database and tables and loads starter content.
4. Sign in at **http://localhost/PORTFOLIO/admin/**.

Text tip: in headings, wrap words in `**double stars**` to make them gold.

## Go live

GitHub Pages **cannot** run PHP or MySQL, so use GitHub for your code and a PHP host for the site:

1. Push this folder to a GitHub repo. `config/config.php` and `uploads/` are git-ignored, so your passwords and uploads stay private.
2. Get PHP hosting with MySQL. Any shared host with cPanel works, or use credits from the GitHub Student Developer Pack (e.g. a free domain plus cloud credits; check education.github.com/pack for the current offers).
3. On the host, create a MySQL database and user (cPanel → MySQL Databases).
4. Upload the files (or `git clone`), copy `config/config.sample.php` to `config/config.php`, and fill in the host's database name, user and password. Set `APP_DEBUG` to `false`.
5. Visit `https://yourdomain/install.php`, create your admin, then **delete `install.php`** from the server.
6. Turn on HTTPS (free Let's Encrypt SSL is in most cPanels).

## Structure

```
index.php          public site          admin/          admin panel
contact.php        contact form API     includes/       core PHP (blocked from web)
install.php        one-time installer   config/         settings (blocked from web)
assets/            css, js, images      database/       schema + starter data
uploads/           your uploaded files
```

To add a new field to a section, add a column to the table and an entry in `includes/entities.php`. The admin form picks it up automatically.
