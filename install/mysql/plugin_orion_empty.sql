DROP TABLE IF EXISTS `glpi_plugin_orion_tasks`;
CREATE TABLE IF NOT EXISTS `glpi_plugin_orion_tasks` (
  `id`                        int(11)                                           NOT NULL DEFAULT '0',
  `filename`                  varchar(255)                                      DEFAULT NULL,
  `status`                    enum('pending','sent', 'running','done','failed') NOT NULL DEFAULT 'pending',
  `remote_id`                 varchar(255)                                      DEFAULT NULL,
  `report_file`               varchar(255)                                      DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


