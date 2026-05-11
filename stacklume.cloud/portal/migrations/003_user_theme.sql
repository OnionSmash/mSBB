-- Stack Compli — add per-user theme preference
ALTER TABLE users ADD COLUMN IF NOT EXISTS theme TEXT;
