<?php

function get_content($url)
{

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $string = curl_exec($ch);
    curl_close($ch);

    if (empty($string))
    {
        return FALSE;
    }

    return $string;

}

/*
 * Define the PCRE to match all entity IDs
 */
$entity_id_pcre = '/(F|S|T|L|M|[0-9])([0-9]{6})/';

/*
 * If no business ID has been passed in the URL
 */
if (!isset($_GET['id']))
{
    header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
    exit();
}

/*
 * If the business ID has an invalid format
 */
elseif ( preg_match($entity_id_pcre, $_GET['id'] == 0) )
{
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    exit();
}

$id = $_GET['id'];

/*
 * Query our own API 
 */
$api_url = 'https://vabusinesses.org/api/business/' . $id;
$business_json = get_content($api_url);

$business = json_decode($business_json);
if ($business === FALSE)
{
    header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error", true, 500);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Virginia Businesses</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/mini-default.min.css" />
	</head>
	
	<body>

		<h1>Virginia Businesses</h1>

		<article>
            <table>
                <thead>
                    <tr>
                        <th scope="col"></th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>

<?php

/*
 * Display a table of all field values
 */
foreach ($business as $field_name => $field_value)
{
    echo '<tr><td>' . $field_name . '</td><td>' . $field_value . '</td></tr>';
}

?>

                </tbody>
            </table>

        </article>
		<footer class="wrapper">
			All business records are created by the Virginia State Corporation Commission, and
			are thus without copyright protection, so may be reused and reproduced freely,
			without seeking any form of permission from anybody. All other website content is
			published under <a href="http://opensource.org/licenses/MIT">the MIT license</a>.
			This website is an independent, private effort, created and run as a hobby, and is
			in no way affiliated with the Commonwealth of Virginia or the State Corporation
			Commission. <a href="https://github.com/openva/vabusinesses.org">All site source
			code is on GitHub</a>—pull requests welcome.
		</footer>

	</body>
</html>
