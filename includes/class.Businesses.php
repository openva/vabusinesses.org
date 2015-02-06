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
	
	/**
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
	
	/**
	 * Export all records for a given place.
	 *
	 * Requires $this->params, which is an elasticsearch-php-ready set of Elasticsearch parameters.
	 * Returns TRUE/FALSE, echoing results directly to the browser.
	 *
	 * TO DO
	 * - Add CSV support.
	 */
	function export_results()
	{
		
		/*
		 * We require Elasticsearch parameters.
		 */
		if (!isset($this->params))
		{
			return FALSE;
		}
		
		/*
		 * Only supports JSON at the moment.
		 */
		if (!isset($this->format))
		{
			$this->format = 'json';
		}
		
		/*
		 * Normally we limit the number of Elasticsearch results to something small, but we want
		 * to be able to export a great many records here. So we set a high ceiling.
		 */
		$this->params['size'] = 100000;
		
		/*
		 * Bring the Memcached connection into the scope of this method.
		 */
		global $mc;
		
		/*
		 * Bring the Elasticsearch connection into the scope of this method.
		 */
		global $es;

		/*
		 * TO DO: Take the below out of this method. Both the table maps line, which should be its
		 * own nethod, and also the sort order code, which I guess should also be its own method.
		 */
		$tables = unserialize($mc->get('table-maps'));
		$sort_order = array();
		foreach($tables as $table_number => $fields)
		{
			foreach ($fields as $field)
			{
				$sort_order[$table_number][] = $field['alt_name'];
			}
		}
		
		/*
		 * Submit our query to Elasticsearch.
		 */
		$results = $es->search($this->params);

		if ( ($results === FALSE) || ($results['hits']['total'] == 0) )
		{
			return FALSE;
		}
		
		/*
		 * If JSON has been requested.
		 */
		if ($this->format == 'json')
		{
			header('Content-Type: application/json');
			echo '[';
		}
		
		/*
		 * Count loops, so that we don't append a comma after the final JSON element.
		 */
		$i=0;

		/*
		 * Walk through all of the results.
		 */
		foreach ($results['hits']['hits'] as $result)
		{
			
			/*
			 * Raise coordinates up a level in the array structure.
			 */
			if (isset($result['_source']['location']['coordinates']))
			{
				$result['_source']['coordinates'] = $result['_source']['location']['coordinates'];
				unset($result['_source']['location']);
			}
		
			/*
			 * Rearrange the fields per the prescribed key order for this type of record.
			 */
			if (isset($sort_order[$result{'_type'}]))
			{
		
				$ordered_result = array();
				foreach ($sort_order[$result{'_type'}] as $key)
				{
					$ordered_result[$key] = $result['_source'][$key];	
				}
			
				/*
				 * Replace the result with the newly ordered result.
				 */
				$result['_source'] = $ordered_result;
				unset($ordered_result);
			
			}
		
			if ($this->format == 'json')
			{
				echo json_encode($result['_source']);
				if ( ($i+1) < $results['hits']['total'])
				{

					echo ',';
				}
			}

			$i++;
			
		}
		
		/*
		 * If JSON has been requested.
		 */
		if ($this->format == 'json')
		{
			echo ']';
		}
			
		return TRUE;
	
	} // end export_results()

}
