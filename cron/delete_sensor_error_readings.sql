-- Delete sensor error readings: value 65000 or more (e.g. 65534 / 65535 from a
-- 16-bit sensor register). Same cut-off as Reading::SENSOR_ERROR_MIN_VALUE,
-- which the charts already leave out.
--
-- Take a backup of the readings table before running this. The DELETE is permanent.
-- Alerts linked to these rows keep existing; their reading_id becomes NULL (FK nullOnDelete).
--
-- After running, rebuild the current device status so no device still shows an
-- error value as its latest reading:
--   php artisan devices:rebuild-latest-status

-- Preview: how many rows will be deleted.
SELECT COUNT(*) AS rows_to_delete
FROM readings
WHERE reading_value >= 65000;

DELETE FROM readings
WHERE reading_value >= 65000;

-- Devices whose ONLY readings were error values have nothing left to rebuild
-- from; remove their stale current-status row (still showing the error value).
DELETE FROM device_latest_status
WHERE reading_value >= 65000
  AND sensor_device_id NOT IN (
      SELECT sensor_device_id FROM (
          SELECT DISTINCT sensor_device_id
          FROM readings
          WHERE deleted_at IS NULL AND sensor_device_id IS NOT NULL
      ) AS remaining
  );
