<?php

namespace ckvsoft\data\us;

class State
{

    private static $_list = array(
        'AL' => "Alabama",
        'AK' => "Alaska",
        'AZ' => "Arizona",
        'AR' => "Arkansas",
        'CA' => "California",
        'CO' => "Colorado",
        'CT' => "Connecticut",
        'DE' => "Delaware",
        'DC' => "District Of Columbia",
        'FL' => "Florida",
        'GA' => "Georgia",
        'HI' => "Hawaii",
        'ID' => "Idaho",
        'IL' => "Illinois",
        'IN' => "Indiana",
        'IA' => "Iowa",
        'KS' => "Kansas",
        'KY' => "Kentucky",
        'LA' => "Louisiana",
        'ME' => "Maine",
        'MD' => "Maryland",
        'MA' => "Massachusetts",
        'MI' => "Michigan",
        'MN' => "Minnesota",
        'MS' => "Mississippi",
        'MO' => "Missouri",
        'MT' => "Montana",
        'NE' => "Nebraska",
        'NV' => "Nevada",
        'NH' => "New Hampshire",
        'NJ' => "New Jersey",
        'NM' => "New Mexico",
        'NY' => "New York",
        'NC' => "North Carolina",
        'ND' => "North Dakota",
        'OH' => "Ohio",
        'OK' => "Oklahoma",
        'OR' => "Oregon",
        'PA' => "Pennsylvania",
        'RI' => "Rhode Island",
        'SC' => "South Carolina",
        'SD' => "South Dakota",
        'TN' => "Tennessee",
        'TX' => "Texas",
        'UT' => "Utah",
        'VT' => "Vermont",
        'VA' => "Virginia",
        'WA' => "Washington",
        'WV' => "West Virginia",
        'WI' => "Wisconsin",
        'WY' => "Wyoming"
    );

    /**
     * Fetch a state based on its acronym
     * 
     * @param mixed $acronym (Optional) 
     * 
     * @return string|array
     */
    public static function fetch($acronym = null)
    {
        if ($acronym == null) {
            return self::$_list;
        }

        if (isset(self::$_list[$acronym]))
            return self::$_list[$acronym];
        else
            return false;
    }

    /**
     * Returns list of Option HTML formatted Values
     * You must wrap the <select> tags around the output
     * 
     * @param string $selected If one of the items is pre-selected
     * 
     * @return string
     */
    public static function fetch_html($selected = null)
    {
        $output = '';
        foreach (self::$_list as $key => $value) {

            if (strtolower($key) == strtolower($selected) && $selected != null) {
                $output .= "<option value='$key' selected='selected'>$value</option>";
            } else {
                $output .= "<option value='$key'>$key</option>";
            }
        }
        return $output;
    }
}
