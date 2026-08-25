-- Converted from giftly_db (4).sql (MySQL/MariaDB dump) to PostgreSQL
-- Target: Render PostgreSQL
--
-- Notes on the conversion:
--   * `int(11)`            -> INTEGER
--   * `decimal(10,2)`      -> NUMERIC(10,2)
--   * AUTO_INCREMENT `id`  -> SERIAL (Postgres creates an implicit sequence)
--   * MySQL ENUM columns   -> VARCHAR + CHECK constraint (portable, easy to alter later)
--   * `current_timestamp()`-> CURRENT_TIMESTAMP
--   * backtick identifiers -> unquoted (none of the names need quoting in Postgres)
--   * backslash-escaped quotes (\') in string literals -> doubled quotes ('') per SQL standard
--   * sequences are advanced with setval() to match the old AUTO_INCREMENT counters,
--     so ids assigned after this import continue where MySQL left off

BEGIN;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
  id           SERIAL PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  email        VARCHAR(100) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         VARCHAR(20) NOT NULL DEFAULT 'customer' CHECK (role IN ('admin','customer')),
  reset_token  VARCHAR(500),
  token_expiry TIMESTAMP,
  phone        VARCHAR(20) NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  profile_pic  VARCHAR(255)
);

INSERT INTO users (id, name, email, password, role, reset_token, token_expiry, phone, created_at, profile_pic) VALUES
(4, 'PEATZIE ARABELA', 'qpacosino@tip.edu.ph', '$2y$10$BKWVZiU30ToeP.eRJCVxW.3YjNUhafQ9n6DJ5I6iAHHa4n/fvOgHK', 'admin', NULL, NULL, '', '2026-08-20 10:55:25', ''),
(12, 'PEATZIE COSINO', 'cosino@tip.edu.ph', '$2y$10$OD900faxI12dHW/2ZPO4v.RKr11wgUzXhrALWQwM7/I2SZUKkBH.6', 'customer', NULL, NULL, '09817161712', '2026-08-20 10:55:25', NULL),
(13, 'Giftly Admin', 'admin@giftly.com', '$2y$10$gLbX7sHYEM/Cf5sueQXei.bUfQHkesApUavJfisZbwNe1tiR99JtG', 'admin', NULL, NULL, '01234567890', '2026-08-20 10:55:25', NULL),
(17, 'gela cosino', 'spilledmilk1324@gmail.com', '$2y$10$/AvEn2/i9ccRSP/XKEzt.eZhjy4NLYjgBpGBGQBwf3qJu5g5URz.y', 'customer', NULL, NULL, '09123456678', '2026-08-22 03:00:41', NULL),
(23, 'TEST TEST', 'TEST@TEST.COM', '$2y$10$NTEMuaiqeHCwSgzquy6T2O5tR6cXlfXBBuC9sIDKmUQdDeoiKYDr2', 'customer', NULL, NULL, '01292910291', '2026-08-24 09:23:52', NULL);

SELECT setval(pg_get_serial_sequence('users', 'id'), 24, false);

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE categories (
  id   SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO categories (id, name) VALUES
(21, 'Chocolates'),
(11, 'Hair Accessories'),
(13, 'Keychains'),
(12, 'Phone Accessories'),
(10, 'Plushies');

SELECT setval(pg_get_serial_sequence('categories', 'id'), 28, false);

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE products (
  id          SERIAL PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  price       NUMERIC(10,2) NOT NULL,
  image       VARCHAR(255) NOT NULL,
  quantity    INTEGER NOT NULL DEFAULT 0,
  category_id INTEGER NOT NULL DEFAULT 1
);

INSERT INTO products (id, name, description, price, image, quantity, category_id) VALUES
(9, 'Pompompurin Plushie', 'A soft and cuddly Pompompurin plushie made with premium materials, featuring embroidered details and Pompompurin''s signature brown beret. Perfect for hugging, collecting, or gifting to Sanrio fans.', 499.00, 'product_1785892781.png', 0, 10),
(10, 'Korilakkuma Plushie', 'A soft and huggable Korilakkuma plushie with a fluffy finish and adorable embroidered details. Perfect for cuddling, displaying, or gifting to Rilakkuma fans.', 499.00, 'product_1785892813.png', 0, 10),
(16, 'Mofusand Bunny Cat Plushie', 'An adorable Mofusand plushie featuring a cute cat dressed in a fluffy bunny costume. Soft, cuddly, and perfect for collectors or as a charming gift.', 549.00, 'product_1785892848.png', 5, 10),
(17, 'Miffy Plushie', 'A soft and lovable Miffy plushie with a simple, timeless design. Perfect for cuddling, decorating your space, or gifting to Miffy fans.', 499.00, 'product_1785892884.png', 24, 10),
(19, 'Cinnamoroll Plushie', 'A fluffy Cinnamoroll plushie featuring its signature long ears and sweet smile. Perfect for cuddles, room décor, or adding to your Sanrio collection.', 499.00, 'product_1785892915.png', 25, 10),
(22, 'Sakura Hair Clips (Set of 2)', 'A pair of elegant sakura flower hair clips that add a cute and delicate touch to any hairstyle.', 129.00, 'product_1785893803.png', 25, 11),
(23, 'Korilakkuma Plush Hair Tie', 'A soft and fluffy Korilakkuma hair tie featuring an adorable plush design that adds a cute touch to ponytails and buns.', 169.00, 'product_1785893870.png', 24, 11),
(24, 'Pink Flower Hair Claw Clip', 'A stylish pink flower-shaped hair claw clip that''s perfect for everyday wear and effortless hairstyles.', 149.00, 'product_1785894126.png', 20, 11),
(25, 'My Melody Plush Headband', 'A cute and comfortable My Melody headband made with soft plush material, perfect for skincare, makeup, or casual wear.', 299.00, 'product_1785894158.png', 24, 11),
(26, 'Korilakkuma Bunny Plush Hair Clip', 'An adorable Korilakkuma plush hair clip featuring bunny ears, designed to add a playful and kawaii touch to your look.', 159.00, 'product_1785894184.png', 23, 11),
(27, 'White Beaded Phone Charm', 'A stylish white beaded phone charm that adds a simple and elegant touch to your phone or accessories.', 149.00, 'product_1785894749.png', 23, 12),
(28, 'Korilakkuma Phone Strap', 'A cute Korilakkuma phone strap featuring a charming design, perfect for decorating your phone, bag, or keys.', 179.00, 'product_1785894779.png', 25, 12),
(29, 'Hello Kitty Heart Plush Charm', 'An adorable Hello Kitty plush charm with a heart design, perfect for accessorizing your bag, keys, or pouch.', 199.00, 'product_1785894811.png', 25, 12),
(30, 'Strawberry Phone Strap', 'A cute strawberry-themed phone strap that adds a playful and colorful touch to your phone or accessories.', 148.97, 'product_1785894842.png', 25, 12),
(31, 'Hello Kitty Plush Phone Strap', 'A soft Hello Kitty phone strap featuring a mini plush charm, perfect for adding a kawaii touch to your phone or bag.', 199.00, 'product_1785894865.png', 25, 12),
(32, 'My Sweet Piano Plush Keychain', 'A sweet My Sweet Piano plush keychain in soft pink and brown tones, perfect for decorating your keys, bag, or pouch.', 199.00, 'product_1785895550.png', 25, 13),
(33, 'Blue Beaded Keychain', 'A stylish blue beaded keychain that adds a simple yet charming touch to your keys, bag, or accessories.', 149.00, 'product_1785895672.png', 25, 13),
(34, 'Cinnamoroll × Mofusand Keychain', 'An adorable keychain featuring a Cinnamoroll-inspired Mofusand design, perfect for fans of cute collectibles.', 198.97, 'product_1785895796.png', 25, 13),
(35, 'Pink Bear Plush Keychain', 'A soft pink bear plush keychain that''s perfect for accessorizing your keys, backpack, or handbag.', 179.00, 'product_1785895872.png', 25, 13),
(36, 'Chiikawa Keychain', 'A cute Chiikawa keychain featuring an adorable character design, ideal for decorating bags, keys, or pouches.', 179.00, 'product_1785895895.png', 25, 13),
(38, 'Strawberry & Milk Chocolate Hearts — 4 pcs', 'A delightful box of four heart-shaped chocolates featuring a sweet combination of strawberry and creamy milk chocolate flavors. Perfect as a simple yet thoughtful gift.', 299.00, 'product_1787026041.png', 17, 21),
(39, 'Ferrero Rocher Heart Box — 8 pcs', 'An elegant heart-shaped box filled with eight luxurious Ferrero Rocher chocolates, perfect for gifting on special occasions.', 599.00, 'product_1787026118.png', 17, 21),
(40, 'Dark Chocolate Heart Box — 8 pcs', 'Eight rich dark chocolate hearts presented in a beautiful red heart-shaped box, combining indulgent flavor with an elegant presentation.', 399.00, 'product_1787026152.png', 17, 21),
(41, 'Casa de Flores Dubai Chewy Cookies — 4 pcs', 'Four premium Dubai-style chewy cookies from Casa de Flores, offering a rich, indulgent texture and deliciously satisfying flavor.', 349.00, 'product_1787026190.png', 18, 21),
(42, 'The Grand Chocolate Collection — 16 pcs', 'A luxurious collection of 16 premium chocolates featuring an exquisite assortment of milk, dark, and specialty chocolate flavors, beautifully presented in an elegant gift box.', 899.00, 'product_1787026215.png', 18, 21),
(43, 'TEST', 't', 1.00, 'product_1787150621.png', 0, 21),
(44, 'burger', 'i am infatuated', 1.00, 'product_1787379310.jpg', 0, 10),
(45, 'jumbalia', 'krill', 192.00, 'product_1787379652.jpg', 25, 21);

SELECT setval(pg_get_serial_sequence('products', 'id'), 46, false);

-- --------------------------------------------------------
-- Table: addresses
-- --------------------------------------------------------
CREATE TABLE addresses (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  address    VARCHAR(255) NOT NULL,
  city       VARCHAR(100) NOT NULL,
  province   VARCHAR(100) NOT NULL,
  zip        VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  label      VARCHAR(50)
);

CREATE INDEX idx_addresses_user_id ON addresses(user_id);

INSERT INTO addresses (id, user_id, address, city, province, zip, created_at, label) VALUES
(6, 4, 'Blk 17 lot 3 Evening Glow Street Ridgemont Executive Village San Isidro', 'Taytay, Rizal', 'RIZAL', '1920', '2026-08-21 06:09:45', 'Home'),
(7, 4, '1', '1', '1', '1', '2026-08-21 06:15:35', 'test'),
(8, 4, '2', '2', '2', '2', '2026-08-21 06:15:52', 'test2'),
(10, 12, 'Blk 17 lot 3 Evening Glow Street Ridgemont Executive Village San Isidro', 'Taytay, Rizal', 'RIZAL', '1920', '2026-08-23 05:48:16', 'Home');

SELECT setval(pg_get_serial_sequence('addresses', 'id'), 11, false);

-- --------------------------------------------------------
-- Table: carts
-- --------------------------------------------------------
CREATE TABLE carts (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  quantity   INTEGER DEFAULT 1
);

INSERT INTO carts (id, user_id, product_id, quantity) VALUES
(165, 15, 10, 1),
(211, 13, 10, 2),
(216, 19, 23, 2),
(230, 12, 17, 1),
(231, 12, 16, 1),
(232, 12, 9, 1),
(278, 22, 16, 5),
(279, 22, 19, 1),
(281, 22, 17, 1);

SELECT setval(pg_get_serial_sequence('carts', 'id'), 330, false);

-- --------------------------------------------------------
-- Table: orders
-- --------------------------------------------------------
CREATE TABLE orders (
  id              SERIAL PRIMARY KEY,
  user_id         INTEGER NOT NULL,
  total_amount    NUMERIC(10,2) NOT NULL,
  status          VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','shipped','delivered','cancelled')),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fullname        VARCHAR(255) NOT NULL,
  address         VARCHAR(255) NOT NULL,
  city            VARCHAR(100) NOT NULL,
  recipient_name  VARCHAR(255) NOT NULL,
  payment_method  VARCHAR(50) NOT NULL DEFAULT 'cod',
  gift_message    TEXT NOT NULL,
  sender_phone    VARCHAR(20),
  recipient_phone VARCHAR(20),
  delivery_date   DATE,
  delivery_time   TIME
);

-- (no rows in the source dump)

-- --------------------------------------------------------
-- Table: order_items
-- --------------------------------------------------------
CREATE TABLE order_items (
  id         SERIAL PRIMARY KEY,
  order_id   INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  quantity   INTEGER NOT NULL,
  price      NUMERIC(10,2) NOT NULL
);

-- (no rows in the source dump)

-- --------------------------------------------------------
-- Table: wishlist
-- --------------------------------------------------------
CREATE TABLE wishlist (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, product_id)
);

CREATE INDEX idx_wishlist_product_id ON wishlist(product_id);

-- (no rows in the source dump)

SELECT setval(pg_get_serial_sequence('wishlist', 'id'), 73, false);

COMMIT;
