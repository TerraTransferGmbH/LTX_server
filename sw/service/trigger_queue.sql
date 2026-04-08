CREATE TABLE IF NOT EXISTS trigger_queue (
  id bigint unsigned AUTO_INCREMENT,
  mac varchar(16) NOT NULL,
  reason tinyint unsigned DEFAULT NULL,
  vpnf tinyint unsigned DEFAULT 0,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;