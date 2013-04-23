<?php
class ModelModuleMailrelay extends Model {
    
    public function createMailrelayTable() {
        $query = $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mailrelay` (`id` int(11) NOT NULL AUTO_INCREMENT, `username` char(50) NOT NULL, `password` char(50) NOT NULL, `hostname` varchar(255) NOT NULL, `key` varchar(255) NOT NULL, `last_group` char(20) DEFAULT NULL, PRIMARY KEY (`id`));");
    }

}
