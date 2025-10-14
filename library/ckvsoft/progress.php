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

    /**
     * Constructor for writing progress updates.
     * @param int $total The total number of steps.
     * @param int $progress_id The ID of the progress entry.
     * @param object $db The database instance.
     */
    public function __construct($total, $progress_id, $db)
    {
        $this->total = $total;
        $this->progress_id = $progress_id;
        $this->db = $db;
    }

    /**
     * Increments the current counter by 1 and updates the DB entry.
     */
    public function increment()
    {
        $this->current++;
        $this->updateProgress();
    }

    /**
     * Adds an arbitrary number to the current counter and updates the DB entry.
     * @param int $current
     */
    public function addToCurrent($current)
    {
        $this->current += $current;
        $this->updateProgress();
    }

    /**
     * Calculates the percentage and saves it to the DB.
     * @param int|null $percent Optional, to set the value directly (e.g., to 100).
     */
    public function updateProgress(?int $percent = null)
    {
        if ($percent === null) {
            if ($this->total === 0) {
                $percent = 0; // Avoid division by zero
            } else {
                $percent = round($this->current / $this->total * 100);
                if ($percent > 100)
                    $percent = 100;
            }
        }

        $data = array('percent' => $percent, 'id' => $this->progress_id);

        $this->db->insertUpdate($this->table, $data);
    }

    public function getCurrent()
    {
        return $this->current;
    }

    // ------------------------------------------------------------------
    // STATIC METHOD FOR READING (FOR POLLING)
    // ------------------------------------------------------------------

    /**
     * Static method to fetch the progress status from the DB (for polling).
     * ...
     */
    public static function getStatus(int $progressId, $db): array
    {
        $tableName = "progress_bars";

        $data = $db->selectOne(
                "SELECT percent, modified FROM {$tableName} WHERE id = :id",
                ['id' => $progressId]
        );

        return $data ?: [];
    }
}
