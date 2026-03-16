-- routepermissions / IssabelPBX 5
CREATE TABLE IF NOT EXISTS routepermissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exten VARCHAR(20) NOT NULL,
  route_id INT NOT NULL DEFAULT 0,
  routename VARCHAR(100) NOT NULL DEFAULT '',
  allowed ENUM('YES','NO','INHERIT') NOT NULL DEFAULT 'INHERIT',
  prefix VARCHAR(32) NOT NULL DEFAULT '',
  faildest VARCHAR(255) NOT NULL DEFAULT '',
  notes VARCHAR(255) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ext_route (exten, route_id),
  KEY idx_route (route_id),
  KEY idx_exten (exten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO routepermissions (exten, route_id, routename, allowed, prefix, faildest, notes)
SELECT '-1', -1, '__GLOBAL__', 'INHERIT', '', '', 'Meta global defaults'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM routepermissions WHERE exten='-1' AND route_id=-1
);
