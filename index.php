<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Virginia Businesses</title>
	<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootswatch/3.1.1/united/bootstrap.min.css" />
</head>
<body>

<a href="https://github.com/openva/business.openva.com"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/365986a132ccd6a44c23a9169022c0b5c890c387/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f7265645f6161303030302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png"></a>

<h1>Virginia Businesses</h1>

<p>Data <a href="https://www.scc.virginia.gov/clk/purch.aspx">purchased from the Virginia State
Corporation Commission</a> and parsed with <a href="https://github.com/openva/crump/">Crump</a>.</p>

<table>
	<thead>
		<tr>
			<th>File</th>
			<th colspan="2">Download</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>Table</td>
			<td><a href="1_tables.csv">CSV</a></td>
			<td><a href="1_tables.json">JSON</a></td>
		</tr>
		<tr>
			<td>Corporate</td>
			<td><a href="2_corporate.csv">CSV</a></td>
			<td><a href="2_corporate.json">JSON</a></td>
		</tr>
		<tr>
			<td>Limited Partnership</td>
			<td><a href="3_lp.csv">CSV</a></td>
			<td><a href="3_lp.json">JSON</a></td>
		</tr>
		<tr>
			<td>Corporate/Limited Partnership/Limited Liability Company</td>
			<td><a href="4_amendments.csv">CSV</a></td>
			<td><a href="4_amendments.json">JSON</a></td>
		</tr>
		<tr>
			<td>Corporate Officer</td>
			<td><a href="5_officers.csv">CSV</a></td>
			<td><a href="5_officers.json">JSON</a></td>
		</tr>
		<tr>
			<td>Corporate/Limited Partnership/Limited Liability Company Name</td>
			<td><a href="6_name.csv">CSV</a></td>
			<td><a href="6_name.json">JSON</a></td>
		</tr>
		<tr>
			<td>Merger</td>
			<td><a href="7_merger.csv">CSV</a></td>
			<td><a href="7_merger.json">JSON</a></td>
		</tr>
		<tr>
			<td>Corporate/Limited Partnership/Limited Liability Company Reserved/Registered Name</td>
			<td><a href="8_registered_names.csv">CSV</a></td>
			<td><a href="8_registered_names.json">JSON</a></td>
		</tr>
		<tr>
			<td>Limited Liability Company</td>
			<td><a href="9_llc.csv">CSV</a></td>
			<td><a href="9_llc.json">JSON</a></td>
		</tr>
	</tbody>
</table>

<p><em>Last updated <?php echo date('F d, Y H:i', filectime('1_tables.csv') ); ?>.</em></p>

</body>
</html>
