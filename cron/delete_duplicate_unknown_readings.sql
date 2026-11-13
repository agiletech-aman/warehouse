-- Remove duplicate "unknown" readings, keeping ONE (the latest) per sensor + key.
-- Unknown = no reading value, or no level, or level = 'unknown'
-- (same rule as Reading::normalizeLevel and the Reports "Unknown" filter).
--
-- Take a backup of the readings table before running this. The DELETE is permanent.
-- Run the whole file in one session (it uses a temporary table).

-- 1) Latest unknown row per sensor + key = the row that stays.
DROP TEMPORARY TABLE IF EXISTS unknown_keep;
CREATE TEMPORARY TABLE unknown_keep AS
SELECT sensor_device_id, `key`, MAX(id) AS keep_id
FROM readings
WHERE deleted_at IS NULL
  AND sensor_device_id IS NOT NULL AND sensor_device_id <> ''
  AND (reading_value IS NULL OR level IS NULL OR level = '' OR level = 'unknown')
GROUP BY sensor_device_id, `key`;

-- Preview: how many rows will be deleted.
SELECT COUNT(*) AS rows_to_delete
FROM readings r
JOIN unknown_keep k ON k.sensor_device_id = r.sensor_device_id AND k.`key` = r.`key`
WHERE r.deleted_at IS NULL
  AND (r.reading_value IS NULL OR r.level IS NULL OR r.level = '' OR r.level = 'unknown')
  AND r.id <> k.keep_id;

-- 2) Point alerts linked to a duplicate at the row that stays
--    (otherwise the FK would just set alerts.reading_id to NULL).
UPDATE alerts a
JOIN readings r ON r.id = a.reading_id
JOIN unknown_keep k ON k.sensor_device_id = r.sensor_device_id AND k.`key` = r.`key`
SET a.reading_id = k.keep_id
WHERE r.deleted_at IS NULL
  AND (r.reading_value IS NULL OR r.level IS NULL OR r.level = '' OR r.level = 'unknown')
  AND r.id <> k.keep_id;

-- 3) Delete the duplicates.
DELETE r
FROM readings r
JOIN unknown_keep k ON k.sensor_device_id = r.sensor_device_id AND k.`key` = r.`key`
WHERE r.deleted_at IS NULL
  AND (r.reading_value IS NULL OR r.level IS NULL OR r.level = '' OR r.level = 'unknown')
  AND r.id <> k.keep_id;

-- 4) Normalise the kept rows so later pushes find and update them.
UPDATE readings r
JOIN unknown_keep k ON k.keep_id = r.id
SET r.level = 'unknown';

DROP TEMPORARY TABLE IF EXISTS unknown_keep;
