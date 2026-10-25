PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE UNIQUE,
 password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('student','staff','admin')),
 is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1)), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS categories (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE COLLATE NOCASE,
 description TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS item_reports (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id),
 category_id INTEGER NOT NULL REFERENCES categories(id), report_type TEXT NOT NULL CHECK(report_type IN ('lost','found')),
 item_name TEXT NOT NULL, description TEXT NOT NULL, location TEXT NOT NULL, date_occurred TEXT NOT NULL,
 image_path TEXT, status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','claim_pending','returned','closed','archived')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 archived_at TEXT, archived_by INTEGER REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS claim_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT, item_report_id INTEGER NOT NULL REFERENCES item_reports(id),
 claimant_id INTEGER NOT NULL REFERENCES users(id), claim_details TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','under_review','needs_info','approved','rejected','returned','cancelled')),
 reviewed_by INTEGER REFERENCES users(id), reviewed_at TEXT, staff_notes TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(item_report_id, claimant_id)
);
CREATE TABLE IF NOT EXISTS claim_evidence (
 id INTEGER PRIMARY KEY AUTOINCREMENT, claim_id INTEGER NOT NULL REFERENCES claim_requests(id) ON DELETE CASCADE,
 user_id INTEGER NOT NULL REFERENCES users(id), evidence_type TEXT NOT NULL, evidence_path TEXT,
 evidence_description TEXT, reviewed_by INTEGER REFERENCES users(id), review_status TEXT NOT NULL DEFAULT 'pending'
 CHECK(review_status IN ('pending','accepted','rejected')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS item_images (
 id INTEGER PRIMARY KEY AUTOINCREMENT, item_report_id INTEGER NOT NULL REFERENCES item_reports(id) ON DELETE CASCADE,
 uploaded_by INTEGER NOT NULL REFERENCES users(id), image_path TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS handover_records (
 id INTEGER PRIMARY KEY AUTOINCREMENT, claim_id INTEGER NOT NULL UNIQUE REFERENCES claim_requests(id),
 recipient_id INTEGER NOT NULL REFERENCES users(id), confirmed_by INTEGER NOT NULL REFERENCES users(id),
 handed_over_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, handover_notes TEXT
);
CREATE TABLE IF NOT EXISTS status_history (
 id INTEGER PRIMARY KEY AUTOINCREMENT, item_report_id INTEGER NOT NULL REFERENCES item_reports(id),
 changed_by INTEGER REFERENCES users(id), old_status TEXT, new_status TEXT NOT NULL, notes TEXT,
 changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS claim_status_history (
 id INTEGER PRIMARY KEY AUTOINCREMENT, claim_id INTEGER NOT NULL REFERENCES claim_requests(id) ON DELETE CASCADE,
 changed_by INTEGER REFERENCES users(id), old_status TEXT, new_status TEXT NOT NULL, notes TEXT,
 changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS notifications (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 type TEXT NOT NULL, message TEXT NOT NULL, related_type TEXT, related_id INTEGER,
 read_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS system_settings (
 id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL UNIQUE, setting_value TEXT NOT NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_reports_status_type ON item_reports(status, report_type);
CREATE INDEX IF NOT EXISTS idx_reports_category ON item_reports(category_id);
CREATE INDEX IF NOT EXISTS idx_claims_status ON claim_requests(status);
CREATE INDEX IF NOT EXISTS idx_claims_claimant ON claim_requests(claimant_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read_at);
INSERT OR IGNORE INTO categories(name, description) VALUES
 ('Electronics','Phones, chargers, earbuds, laptops and similar items'),
 ('ID / Cards','Student IDs and cards'), ('Bags','Backpacks, pouches and wallets'),
 ('Clothing','Jackets, uniforms and clothing'), ('Books / School Supplies','Books, notebooks and supplies'),
 ('Keys','Keys and keychains'), ('Other','Items that do not fit another category');
INSERT OR IGNORE INTO system_settings(setting_key, setting_value) VALUES ('unclaimed_retention_days','30');
