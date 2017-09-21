DROP TABLE IF EXISTS `glpi_plugin_orion_tasks`;
CREATE TABLE IF NOT EXISTS `glpi_plugin_orion_tasks` (
  `id`                        int(11)                                           NOT NULL DEFAULT '0',
  `status`                    enum('pending','running','done','failed')         NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


