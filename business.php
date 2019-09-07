<?php

$db = new SQLite3('data/vabusinesses.sqlite');

$result = $db->query('SELECT * FROM corp WHERE EntityID="0530258"');

$business = $result->fetchArray();

echo '<h1>' . $business['Name'] . '</h1>';
echo 'Incorporated ' . $business['IncorpDate'] . '</p>';
echo '<p>' . $business['Street1'] . '<br>';
if (!empty($business['Street2']))
{
    echo $business['Street2'] . '<br>';
}
echo $business['City'] . ', ' . $business['State'] . ' ' . $business['Zip'] . '</p>';

echo '<h2>Registered Agent</h2>';

echo '<p>' . $business['RA-Name'] . '</p>';
echo '<p>' . $business['RA-Street1'] . '<br>';
if (!empty($business['RA-Street2']))
{
    echo $business['RA-Street2'];
}
echo $business['RA-City'] . ', ' . $business['RA-State'] . ' ' . $business['RA-Zip'] . '</p>';
echo '<p>Effective ' . $business['RA-EffDate'] . '</p>';

