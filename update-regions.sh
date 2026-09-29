#!/bin/bash

mysql -u co2ph3_user -p't(2(1w3RD8g3' co2ph3 <<'SQL'

UPDATE readings
SET region = 'LUCKNOW',
    region_code = 'RE-LUC'
WHERE warehouse_code = 'WH178'
  AND warehouse = 'BALLIA';

UPDATE device_latest_status
SET region = 'LUCKNOW',
    region_code = 'RE-LUC'
WHERE warehouse_code = 'WH178'
  AND warehouse = 'BALLIA';

UPDATE readings
SET region = 'CHANDIGARH',
    region_code = 'RE-CHA'
WHERE warehouse_code = 'WH047'
  AND warehouse = 'BATHINDA (PEG)';

UPDATE device_latest_status
SET region = 'CHANDIGARH',
    region_code = 'RE-CHA'
WHERE warehouse_code = 'WH047'
  AND warehouse = 'BATHINDA (PEG)';

SQL
