-- ============================================================
--  Build-a-Box + Occasion Boxes / Baskets — schema
--  Safe to run more than once. The application also creates
--  these automatically on first use (see build_a_box_lib.php
--  and catalog_lib.php), so running this by hand is optional.
-- ============================================================

-- Occasion Boxes / Baskets are products with a type tag.
ALTER TABLE products ADD COLUMN IF NOT EXISTS product_type VARCHAR(20) NOT NULL DEFAULT 'catalog';

CREATE TABLE IF NOT EXISTS box_sizes (
    id         SERIAL PRIMARY KEY,
    code       VARCHAR(20)   NOT NULL UNIQUE,
    name       VARCHAR(50)   NOT NULL,
    max_items  INTEGER       NOT NULL,
    price      NUMERIC(10,2) NOT NULL DEFAULT 0,
    sort_order INTEGER       NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS product_box_sizes (
    product_id  INTEGER NOT NULL REFERENCES products(id)  ON DELETE CASCADE,
    box_size_id INTEGER NOT NULL REFERENCES box_sizes(id) ON DELETE CASCADE,
    PRIMARY KEY (product_id, box_size_id)
);

CREATE TABLE IF NOT EXISTS boxes (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER NOT NULL,
    box_size_id INTEGER NOT NULL REFERENCES box_sizes(id),
    letter      TEXT NOT NULL DEFAULT '',
    card_style  VARCHAR(30) NOT NULL DEFAULT 'simple',
    status      VARCHAR(20) NOT NULL DEFAULT 'saved'
                CHECK (status IN ('saved','in_cart','ordered')),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE boxes ADD COLUMN IF NOT EXISTS card_style VARCHAR(30) NOT NULL DEFAULT 'simple';

CREATE TABLE IF NOT EXISTS box_items (
    id         SERIAL PRIMARY KEY,
    box_id     INTEGER NOT NULL REFERENCES boxes(id)    ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    quantity   INTEGER NOT NULL DEFAULT 1
);

-- Seed the three box sizes (no packaging fee -> price 0)
INSERT INTO box_sizes (code, name, max_items, price, sort_order) VALUES
    ('small',  'Small Box',  5,  0, 1),
    ('medium', 'Medium Box', 10, 0, 2),
    ('large',  'Large Box',  15, 0, 3)
ON CONFLICT (code) DO NOTHING;

-- Backfill: every existing product is allowed in every box size.
-- Admins can then narrow this per product in the product editor.
INSERT INTO product_box_sizes (product_id, box_size_id)
SELECT p.id, b.id FROM products p CROSS JOIN box_sizes b
ON CONFLICT DO NOTHING;

-- ============================================================
--  Order cancellation requests (admin approval flow)
-- ============================================================
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_status VARCHAR(20) NOT NULL DEFAULT 'none';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_reason TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_requested_at TIMESTAMP;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_reviewed_at TIMESTAMP;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_admin_note TEXT;

-- ============================================================
--  Contact form submissions
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(160) NOT NULL,
    subject    VARCHAR(160) NOT NULL DEFAULT '',
    message    TEXT NOT NULL,
    is_read    BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN NOT NULL DEFAULT FALSE;
