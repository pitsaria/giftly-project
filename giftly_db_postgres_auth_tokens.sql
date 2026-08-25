-- Adds Bearer-token auth support for the Ionic/Angular mobile app.
-- The website keeps using PHP session cookies; this is purely additive.

CREATE TABLE auth_tokens (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token      VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_auth_tokens_token ON auth_tokens(token);
