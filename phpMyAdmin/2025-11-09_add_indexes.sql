USE `rentallanka`;

-- Properties: FULLTEXT(title, description)
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'properties'
    AND index_name = 'idx_ft_properties_title_desc'
);
SET @sql := IF(@idx = 0,
  'CREATE FULLTEXT INDEX idx_ft_properties_title_desc ON properties (title, description)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Properties: INDEX(status, price_per_month)
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'properties'
    AND index_name = 'idx_props_status_price'
);
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_props_status_price ON properties (status, price_per_month)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rooms: FULLTEXT(title, description)
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rooms'
    AND index_name = 'idx_ft_rooms_title_desc'
);
SET @sql := IF(@idx = 0,
  'CREATE FULLTEXT INDEX idx_ft_rooms_title_desc ON rooms (title, description)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Locations: INDEX(property_id, province_id, district_id)
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'locations'
    AND index_name = 'idx_locations_prop_prov_dist'
);
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_locations_prop_prov_dist ON locations (property_id, province_id, district_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
