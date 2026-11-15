-- Fixture til beviset for backup-øvelsen (S5.12) — køres mod en midlertidig MySQL i CI.
--
-- Datasættet er lille nok til at køre på under et minut, men bærer de fire ting øvelsen
-- skal kunne håndtere, og som et tomt skema ikke beviser noget om:
--
--   1. **Rækker i alle tabeller** — 3 527 i alt, så paritet og RTO måles på rigtigt data.
--   2. **Et gammelt WordPress-prefix** (`clk4219fc96b5_`) som øvelsen skal *udelade* af
--      dumpet; det er den fælde der ellers overskriver forældede tabeller.
--   3. **En visning** under det levende prefix, som skal droppes med `DROP VIEW` — ikke
--      `DROP TABLE` — ellers står den tilbage efter oprydningen.
--   4. **En trigger** (dumpes med `--triggers`) og **utf8mb4-indhold** med æøå og emoji,
--      så dumpet ikke kan bestå ved at være ren ASCII.
--
-- Prefixet `fx7c1a9b2e_` er bevidst et andet end produktionens, så kørslen også beviser at
-- `DB_PREFIX` faktisk bliver brugt, og at et forkert prefix giver en rød dom.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS fx7c1a9b2e_seq;
DROP TABLE IF EXISTS fx7c1a9b2e_postmeta;
DROP TABLE IF EXISTS fx7c1a9b2e_posts;
DROP TABLE IF EXISTS fx7c1a9b2e_options;
DROP TABLE IF EXISTS fx7c1a9b2e_users;
DROP VIEW IF EXISTS fx7c1a9b2e_recent;
DROP TABLE IF EXISTS clk4219fc96b5_options;

-- Hjælpetabel til at generere rækker. Den kan ikke være TEMPORARY: MySQL svarer
-- »Can't reopen table: 'a'« (ERROR 1137) når den samme midlertidige tabel bruges to gange
-- i ét krydsprodukt. Den oprettes derfor som en rigtig tabel og droppes igen til sidst, så
-- den hverken tælles med i pariteten eller kommer i et dump.
CREATE TABLE fx7c1a9b2e_seq (n INT PRIMARY KEY);
INSERT INTO fx7c1a9b2e_seq (n) VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

CREATE TABLE fx7c1a9b2e_options (
  option_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  option_name VARCHAR(191) NOT NULL,
  option_value LONGTEXT NOT NULL,
  autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
  PRIMARY KEY (option_id),
  UNIQUE KEY option_name (option_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fx7c1a9b2e_options (option_name, option_value, autoload) VALUES
  ('siteurl', 'https://fixture.invalid', 'yes'),
  ('blogname', 'Steamgears fixture — æøå', 'yes'),
  ('blogdescription', 'Et dump uden emoji er ikke et dump: 🎩⚙️', 'yes'),
  ('prefix_reference', 'tabellen fx7c1a9b2e_posts bærer prefixet i sin værdi', 'no'),
  ('admin_email', 'fixture@example.invalid', 'yes'),
  ('posts_per_page', '10', 'yes'),
  ('timezone_string', 'Europe/Copenhagen', 'yes'),
  ('date_format', 'j. F Y', 'yes'),
  ('start_of_week', '1', 'yes'),
  ('use_smilies', '1', 'yes'),
  ('default_category', '1', 'yes'),
  ('blog_public', '1', 'yes'),
  ('show_on_front', 'posts', 'yes'),
  ('template', 'steamgears', 'yes'),
  ('stylesheet', 'steamgears', 'yes'),
  ('woocommerce_currency', 'DKK', 'yes'),
  ('woocommerce_price_thousand_sep', '.', 'yes'),
  ('woocommerce_price_decimal_sep', ',', 'yes'),
  ('sgap_full_auto_enabled', 'no', 'yes'),
  ('sgap_alert_email', '', 'yes');

CREATE TABLE fx7c1a9b2e_posts (
  ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_title VARCHAR(255) NOT NULL,
  post_content LONGTEXT NOT NULL,
  post_status VARCHAR(20) NOT NULL DEFAULT 'publish',
  post_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ID),
  KEY post_status (post_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fx7c1a9b2e_posts (post_title, post_content, post_status)
SELECT CONCAT('Guide ', n, ' — steampunk i æøå'),
       CONCAT(REPEAT('Indhold med æøå, emoji 🎩 og en reference til fx7c1a9b2e_posts. ', 12), n),
       IF(n % 7 = 0, 'draft', 'publish')
FROM (SELECT a.n + 10 * b.n + 100 * c.n + 1 AS n FROM fx7c1a9b2e_seq a, fx7c1a9b2e_seq b, fx7c1a9b2e_seq c ORDER BY 1 LIMIT 500) AS tal;

CREATE TABLE fx7c1a9b2e_postmeta (
  meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  meta_key VARCHAR(255) DEFAULT NULL,
  meta_value LONGTEXT,
  PRIMARY KEY (meta_id),
  KEY post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fx7c1a9b2e_postmeta (post_id, meta_key, meta_value)
SELECT 1 + (n % 500), CONCAT('_sgap_key_', n % 37), REPEAT('værdi 🎩 ', 4)
FROM (SELECT a.n + 10 * b.n + 100 * c.n + 1000 * d.n + 1 AS n FROM fx7c1a9b2e_seq a, fx7c1a9b2e_seq b, fx7c1a9b2e_seq c, fx7c1a9b2e_seq d ORDER BY 1 LIMIT 3000) AS tal;

CREATE TABLE fx7c1a9b2e_users (
  ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_login VARCHAR(60) NOT NULL,
  user_email VARCHAR(100) NOT NULL,
  user_registered DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ID),
  UNIQUE KEY user_login (user_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fx7c1a9b2e_users (user_login, user_email) VALUES
  ('admin', 'admin@example.invalid'),
  ('redaktion', 'redaktion@example.invalid'),
  ('kundeservice', 'support@example.invalid'),
  ('lager', 'lager@example.invalid'),
  ('bogholderi', 'bogholderi@example.invalid'),
  ('marketing', 'marketing@example.invalid'),
  ('gaest', 'gaest@example.invalid');

-- Visningen skal droppes med DROP VIEW: DROP TABLE fejler på den.
CREATE ALGORITHM=UNDEFINED DEFINER=CURRENT_USER SQL SECURITY DEFINER VIEW fx7c1a9b2e_recent AS
  SELECT ID, post_title FROM fx7c1a9b2e_posts WHERE post_status = 'publish';

-- Triggeren dumpes med --triggers og skal kunne genskabes i scratch-prefixet.
CREATE TRIGGER fx7c1a9b2e_postmeta_key
  BEFORE INSERT ON fx7c1a9b2e_postmeta
  FOR EACH ROW SET NEW.meta_key = COALESCE(NEW.meta_key, '_sgap_ukendt');

-- Forældet prefix: må ikke dumpes og må ikke røres. 3 rækker, som CI tjekker bagefter.
CREATE TABLE clk4219fc96b5_options (
  option_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  option_name VARCHAR(191) NOT NULL,
  option_value LONGTEXT NOT NULL,
  PRIMARY KEY (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO clk4219fc96b5_options (option_name, option_value) VALUES
  ('siteurl', 'https://gammel.invalid'),
  ('blogname', 'Forældet installation'),
  ('active_plugins', 'a:0:{}');

DROP TABLE fx7c1a9b2e_seq;
