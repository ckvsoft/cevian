<?php

namespace ckvsoft;

/**
 * Description of json
 *
 * @author chris
 */
class Json
{

    private $jsonString;

    public function __construct(string $jsonString)
    {
        $this->jsonString = $jsonString;
    }

    public function createTableFromJsonFile($buttons = true): string
    {

        if ($this->jsonString === false) {
            throw new JsonTableCreatorException("Fehler beim Lesen der JSON-Datei: $this->jsonString");
        }

        $data = json_decode($this->jsonString, true);

        if ($data === null) {
            throw new JsonTableCreatorException("Fehler beim Parsen des JSON-Strings: " . json_last_error_msg());
        }

        $html = '<table>';
        $html .= '<thead>';
        $html .= '<tr>';

        if (count($data) > 0 && isset($data[0])) {
            foreach ($data[0] as $key => $value) {
                $html .= '<th>' . ucfirst($key) . '</th>';
            }
        } else {
            $buttons = false;
            $html .= '<th>Keine Datensätze vorhanden</th>';
        }

        if ($buttons)
            $html .= '<th></th>'; // Add an empty header cell for the buttons

        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            $html .= '<tr' . (($rowCount % 2 == 0) ? ' class="even-row"' : '') . '>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }

            if ($buttons) {
                $html .= '<td style="text-align:right;">';
                if (isset($row['id'])) {

                    $html .= '<button title="Rückgängig" onclick="undoRow(\'' . $row['id'] . '\')">&#x21b6;</button>';
                    $html .= '<button title="Löschen" onclick="deleteRow(\'' . $row['id'] . '\')">&#x2716;</button>';
                }
            }

            $html .= '</td>';
        }
        $html .= '</tr>';

        $html .= '</tbody>';
        $html .= '</table>';

        $html = '<style>
table {
  border-collapse: collapse;
  width: 100%;
}

th {
  background-color: #333;
  color: #fff;
  text-transform: capitalize;
}

td, th {
  border: 1px solid #ddd;
  padding: 8px;
}

.even-row {
  background-color: #f2f2f2;
}
</style>' . $html;

        return $html;
    }
}

class JsonTableCreatorException extends \ckvsoft\CkvException
{
    
}
