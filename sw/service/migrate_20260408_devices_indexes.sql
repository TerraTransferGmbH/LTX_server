-- Fix indexes on devices table (for existing databases)
-- 1. Remove redundant INDEX on mac (UNIQUE already provides an index)
-- 2. Add INDEX on last_change (used by w_main.php poll filter)

ALTER TABLE devices DROP INDEX mac, ADD INDEX idx_last_change (last_change);
