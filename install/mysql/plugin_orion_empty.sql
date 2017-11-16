DROP TABLE IF EXISTS `glpi_plugin_orion_reports`;
CREATE TABLE IF NOT EXISTS `glpi_plugin_orion_reports` (
  `id`                        int(11)                                           NOT NULL AUTO_INCREMENT,
  `name`                      varchar(255)                                      DEFAULT NULL, 
  `itemtype`                  varchar(255)                                      DEFAULT NULL,
  `items_id`                  int(11)                                           NOT NULL DEFAULT '0',   
  `filename`                  varchar(255)                                      DEFAULT NULL,
  `status`                    enum('pending','sent', 'running','done','failed') NOT NULL DEFAULT 'pending',
  `remote_id`                 varchar(255)                                      DEFAULT NULL,
  `sha256`                    varchar(255)                                      DEFAULT NULL,
  `report`                    text                                              ,
  `date_report`               datetime                                          DEFAULT NULL,
  `evaluation`                enum('n/a','low', 'medium','high')                NOT NULL DEFAULT 'n/a',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
