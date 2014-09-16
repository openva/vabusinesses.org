<?php

class Businesses
{

	/*
	 * Declare the list of files.
	 */
	public $files = array(
		array(
			'name'	=> 'Inc. Registrations',
			'csv'	=> '2_corporate.csv',
			'json'	=> '2_corporate.json'),
		array(
			'name'	=> 'LP Registrations',
			'csv'	=> '3_lp.csv',
			'json'	=> '3_lp.json'),
		array(
			'name'	=> 'Inc./LP/LLC Amendments',
			'csv'	=> '4_amendments.csv',
			'json'	=> '4_amendments.json'),
		array(
			'name'	=> 'Corporate Officer',
			'csv'	=> '5_officers.csv',
			'json'	=> '5_officers.json'),
		array(
			'name'	=> 'Inc./LP/LLC Names',
			'csv'	=> '6_name.csv',
			'json'	=> '6_name.json'),
		array(
			'name'	=> 'Mergers',
			'csv'	=> '7_merger.csv',
			'json'	=> '7_merger.json'),
		array(
			'name'	=> 'Inc./LP/LLC Reserved/Registered Names',
			'csv'	=> '8_registered_names.csv',
			'json'	=> '8_registered_names.json'),
		array(
			'name'	=> 'LLC Registrations',
			'csv'	=> '9_llc.csv',
			'json'	=> '9_llc.json')
	);
	
	/*
	 * List the names, dates, and size of all CSV and JSON files.
	 */
	function list_files()
	{
		
		/*
		 * Iterate through all of the files to get their creation dates and sizes.
		 */
		foreach ($this->files as &$file)
		{
			$file['csv_size'] = filesize($file['csv']);
			$file['csv_date'] = filectime($file['csv']);
			$file['json_size'] = filesize($file['json']);
			$file['json_date'] = filectime($file['json']);
		}
		
		return TRUE;
		
	}

}
