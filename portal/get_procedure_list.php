<?php

require_once("verify_session.php");
require_once('../library/options.inc.php');

$selects =
    "po.procedure_order_id, po.date_ordered, " .
    "pt1.procedure_type_id AS order_type_id, pc.procedure_name, pc.procedure_code";

$joins =
    "JOIN procedure_order_code AS pc ON pc.procedure_order_id = po.procedure_order_id " .
    "LEFT JOIN procedure_type AS pt1 ON pt1.lab_id = po.lab_id AND pt1.procedure_code = pc.procedure_code " .
    "LEFT JOIN procedure_report AS pr ON pr.procedure_order_id = po.procedure_order_id AND " .
    "pr.procedure_order_seq = pc.procedure_order_seq";

$orderby =
    "po.date_ordered, po.procedure_order_id";

$where = "1 = 1";

$rres = sqlStatement("SELECT $selects " .
    "FROM procedure_order AS po $joins " .
    "WHERE po.patient_id = ? AND $where " .
    "ORDER BY $orderby", array($pid));


//If procedures have been ordered for the patient, begin building the procedure pricing tables
if (sqlNumRows($rres) > 0) {
    $even = false;
    while ($row = sqlFetchArray($rres)) {
        
//Get the patient's zip/postal code
        $postcode2 = getPatientData($pid, "postal_code");


//Begin looping through procedures
        //$rres = $res;
        while ($rrow = sqlFetchArray($rres)) {
//color-style rows
            if ($even) {
                $class = "class1_even";
                $even = false;
            } else {
                $class = "class1_odd";
                $even = true;
            }

// Fetch data from the API for the given procedure
            $APIurl = 'https://ihi-api.dexals.com/get_costs?patient_zip=' . $postcode2['postal_code'] . '&procedure_code=' . $rrow['procedure_code'];
            $data = file_get_contents($APIurl);


// Check if data retrieval was successful
            if ($data !== false) {
                // Decode JSON response
                $result = json_decode($data, true);

                // Check if decoding was successful
                if ($result !== null) {
                    // Process the API result
                    //print_r($result);
                    // Access specific fields as needed, for example:
                    // echo "Procedure cost: " . $result['cost'] . "\n";
                } else {
                    echo "Failed to decode JSON response.";
                }
            } else {
                echo "Failed to fetch data from the API.";
            }

//create header row
            ?>
            <table class="table table-striped table-sm table-bordered">
            <tr class="header">
            <th><?php echo xlt('Order Date'); ?></th>
            <th><?php echo xlt('Procedure Name'); ?></th>
            </tr>
    <?php

//populate table rows with procedure info

            $date = explode('-', $row['date_ordered']);
            echo "<tr class='" . $class . "'>";
            echo "<td>" . text($date[1] . "/" . $date[2] . "/" . $date[0]) . "</td>";
            echo "<td>" . text($rrow['procedure_name']) . "</td>";
            echo "</tr>";


// Check if the 'costs' array exists and is not empty
            if (isset($result['costs']) && !empty($result['costs'])) {
                // Start building the table
                echo '<table border="1">
            <thead>
                <tr>
                    <th>Facility Name</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Zip Code</th>
                    <th>Distance</th>
                    <th>Cost</th>
                </tr>
            </thead>
            <tbody>';

                // Loop through each facility and create table rows
                foreach ($result['costs'] as $facility) {
                    $facilityData = $facility['facility'];
                    $cost = $facility['cost'];
                    echo '<tr>';
                    echo '<td>' . $facilityData['facility_name'] . '</td>';
                    echo '<td>' . $facilityData['facility_address'] . '</td>';
                    echo '<td>' . $facilityData['facility_city'] . '</td>';
                    echo '<td>' . $facilityData['facility_state'] . '</td>';
                    echo '<td>' . $facilityData['facility_zip'] . '</td>';
                    echo '<td>' . $facilityData['distance'] . '</td>';
                    echo '<td>$' . number_format($cost, 2) . '</td>'; // Format cost as currency
                    echo '</tr>';
                }

                // Close the table
                echo '</tbody></table>';
            } else {
                echo 'No data available';
            }

        }
    }

    echo "</table>";
    } else {
        echo xlt("No Results");
    }
    ?>

