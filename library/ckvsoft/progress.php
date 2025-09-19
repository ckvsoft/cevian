<?php

/*
  CREATE TABLE `progress_bars` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `percent` int(11) NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
  ALTER TABLE `progress_bars` ADD PRIMARY KEY (`id`);
  ALTER TABLE `progress_bars` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
 */

namespace ckvsoft;

class Progress
{

    private $current = 0;
    private $total = 0;
    private $progress_id = null;
    private $db = null;
    private $table = "progress_bars";

    public function __construct($total, $progress_id, $db)
    {
        $this->total = $total;
        $this->progress_id = $progress_id;
        $this->db = $db;
    }

    public function increment()
    {
        $this->current++;
        $this->updateProgress();
    }

    public function addToCurrent($current)
    {
        $this->current += $current;
        $this->updateProgress();
    }

    private function updateProgress()
    {
        $percent = round($this->current / $this->total * 100);
        if ($percent > 100)
            $percent = 100;
        error_log("percent: $percent");
        $data = array('percent' => $percent, 'id' => $this->progress_id);
        $this->db->insertUpdate($this->table, $data);
    }
}
