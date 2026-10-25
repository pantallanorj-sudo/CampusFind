
-- CampusFind Seed Data
-- Run after database/schema.sql has been executed.
-- Development and testing data only.

PRAGMA foreign_keys = ON;

BEGIN TRANSACTION;

-- =========================================
-- 1. SAMPLE USERS
-- Password for all accounts: CampusFind123!
-- The password_hash values were generated using PHP password_hash().
-- =========================================

INSERT OR IGNORE INTO users
    (id, full_name, email, password_hash, role, created_at)
VALUES
    (1, 'Alex Student', 'student@campusfind.test',
     '$2y$12$NC5PTpkQZvrzFRSDm4DufOGObbvy.1678zNoVws4aVJvkAeM.wgQ6',
     'student', CURRENT_TIMESTAMP),

    (2, 'Taylor Staff', 'staff@campusfind.test',
     '$2y$12$NC5PTpkQZvrzFRSDm4DufOGObbvy.1678zNoVws4aVJvkAeM.wgQ6',
     'staff', CURRENT_TIMESTAMP),

    (3, 'Jordan Admin', 'admin@campusfind.test',
     '$2y$12$NC5PTpkQZvrzFRSDm4DufOGObbvy.1678zNoVws4aVJvkAeM.wgQ6',
     'admin', CURRENT_TIMESTAMP);


-- =========================================
-- 2. ITEM CATEGORIES
-- =========================================

INSERT OR IGNORE INTO categories
    (id, name, description, created_at)
VALUES
    (1, 'Electronics', 'Phones, chargers, earphones, and other electronic devices.',
     CURRENT_TIMESTAMP),

    (2, 'Identification', 'Student IDs and other identification cards.',
     CURRENT_TIMESTAMP),

    (3, 'School Supplies', 'Notebooks, pens, calculators, and school materials.',
     CURRENT_TIMESTAMP),

    (4, 'Bags and Accessories', 'Bags, wallets, umbrellas, and personal accessories.',
     CURRENT_TIMESTAMP),

    (5, 'Clothing', 'Jackets, uniforms, and other clothing items.',
     CURRENT_TIMESTAMP),

    (6, 'Others', 'Items that do not fit the other categories.',
     CURRENT_TIMESTAMP);


-- =========================================
-- 3. SAMPLE ITEM REPORTS
-- =========================================

INSERT OR IGNORE INTO item_reports
    (
        id,
        user_id,
        category_id,
        report_type,
        item_name,
        description,
        location,
        date_occurred,
        image_path,
        status,
        created_at,
        updated_at,
        archived_at,
        archived_by
    )
VALUES
    (
        1, 1, 1, 'lost',
        'Black Wireless Earbuds',
        'A small black earbuds case. The case has a scratch on the lid.',
        'Library, second floor',
        '2026-10-20',
        NULL,
        'open',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP,
        NULL,
        NULL
    ),

    (
        2, 2, 2, 'found',
        'Student Identification Card',
        'A student ID card was found near the campus cafeteria.',
        'Campus cafeteria',
        '2026-10-21',
        NULL,
        'open',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP,
        NULL,
        NULL
    ),

    (
        3, 1, 3, 'lost',
        'Blue Scientific Calculator',
        'A blue scientific calculator with a transparent protective cover.',
        'Engineering building, Room 204',
        '2026-10-22',
        NULL,
        'open',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP,
        NULL,
        NULL
    ),

    (
        4, 2, 4, 'found',
        'Brown Wallet',
        'A brown wallet was found on a bench. The owner must identify its contents.',
        'Main campus courtyard',
        '2026-10-23',
        NULL,
        'open',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP,
        NULL,
        NULL
    ),

    (
        5, 1, 5, 'lost',
        'Gray University Jacket',
        'A gray jacket possibly left after a class.',
        'Student lounge',
        '2026-10-24',
        NULL,
        'open',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP,
        NULL,
        NULL
    );

COMMIT;
