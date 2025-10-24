<?php

namespace ckvsoft;

/**
 * Class Json
 * Converts JSON data into an HTML table.
 * Buttons for "undo" and "delete" are included if the row contains an 'id'.
 * Inline CSS removed — styling should come from external mobile CSS.
 */
class Json
{

    private $jsonString;

    /**
     * Constructor
     *
     * @param string $jsonString JSON string to convert
     */
    public function __construct(string $jsonString)
    {
        $this->jsonString = $jsonString;
    }

    /**
     * Create an HTML table from JSON data
     *
     * @param bool $buttons Include undo/delete buttons if true
     * @return string HTML table
     * @throws JsonTableCreatorException
     */
    public function createTableFromJsonFile($buttons = true): string
    {
        if ($this->jsonString === false) {
            throw new JsonTableCreatorException("Error reading JSON: $this->jsonString");
        }

        $data = json_decode($this->jsonString, true);
        if ($data === null) {
            throw new JsonTableCreatorException("Error parsing JSON: " . json_last_error_msg());
        }

        $html = '<table>';
        $html .= '<thead><tr>';

        // Add table headers based on first row keys
        if (count($data) > 0 && isset($data[0])) {
            foreach ($data[0] as $key => $value) {
                $html .= '<th>' . ucfirst($key) . '</th>';
            }
        } else {
            $buttons = false;
            $html .= '<th>No data available</th>';
        }

        if ($buttons) {
            $html .= '<th></th>'; // Empty header for buttons
        }

        $html .= '</tr></thead>';
        $html .= '<tbody>';

        // Populate table rows
        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            $html .= '<tr' . (($rowCount % 2 == 0) ? ' class="even-row"' : '') . '>';

            // Table cells
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }

            // Optional buttons for rows with 'id'
            if ($buttons) {
                $html .= '<td style="text-align:right;">';
                if (isset($row['id'])) {
                    $html .= '<button title="Undo" onclick="undoRow(\'' . $row['id'] . '\')">&#x21b6;</button>';
                    $html .= '<button title="Delete" onclick="deleteRow(\'' . $row['id'] . '\')">&#x2716;</button>';
                }
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}

/**
 * Custom exception for JSON table errors
 */
class JsonTableCreatorException extends \ckvsoft\CkvException
{

}
