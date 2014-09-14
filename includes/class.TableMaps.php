<?php

/**
 * The Table Maps class, which deals with the content and structure of the YAML table maps.
 *
 * PHP version 5
 *
 * @license		http://opensource.org/licenses/MIT MIT
 *
 */

class TableMaps
{

	function import_files()
	{
	
		/*
		 * Import all YAML table maps and turn them into PHP arrays.
		 */
		$dir = '../crump/table_maps/';
		$files = scandir($dir);
		foreach ($files as $file)
		{

			if ( ($file == '.') || ($file == '..') || ($file == '1_tables.yaml') )
			{
				continue;
			}
			$file_number = $file[0];
			$tables[$file_number] = yaml_parse_file($dir . $file);
	
		}

		$tables_js = '<script>tables = \'' . json_encode($tables) . '\'</script>';

		/*
		 * Iterate through every field in every table map and use them to establish the proper sort order
		 * for field names and a list of all valid field names (which we use for input sanitation).
		 */
		$sort_order = array();
		$valid_fields = array();
		foreach($tables as $table_number => $fields)
		{

			foreach ($fields as $field)
			{
		
				$sort_order[$table_number][] = $field['alt_name'];
		
				/*
				 * Create a list of every valid field name.
				 */
				$valid_fields[] = $field['alt_name'];
		
			}
	
		}

	} // end import_files()

} // end TableMaps
