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
	 * - Get this to support indefinite numbers of records. It runs out of memory now, as a result
	 *   of elasticsearch-php json_decoding() all records en masse. (Use paging to get around this.)
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
		 * Else if CSV has been requested.
		 */
		elseif ($this->format == 'csv')
		{
		
			/*
			 * Use PHP's CSV functionality, but using its output-to-browser pseudo-file.
			 */
			header('Content-Type: text/csv');
			$fp = fopen("php://output", 'w');
			
			/*
			 * If this query is only of business registration records (tables 2, 3, and 9), then
			 * homogenize them so that they all share the same field names.
			 */
			if ($this->params['type'] == '2,3,9')
			{
				
				/*
				 * Only return these columns, in this order.
				 */
				$cols = array('id','name','status','status_date','expiration_date',
					'incorporation_date','state_formed','industry','street_1','street_2','city',
					'state','zip','coordinates','address_date','agent_name','agent_street_1',
					'agent_street_2','agent_city','agent_state','agent_zip','agent_date',
					'agent_status','agent_court_locality');
				
			}
			
			fputcsv($fp, $cols);
			
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
			
			/*
			 * If it's JSON, we only need to encode it and append a comma (except to the final line,
			 * because that would produce invalid JSON).
			 */
			if ($this->format == 'json')
			{
			
				echo json_encode($result['_source']);
				if ( ($i+1) < $results['hits']['total'])
				{

					echo ',';
				}
				
			}
			
			/*
			 * If we're producing CSV, we need to collapse the nested "coordinates" field into a
			 * single field before outputting the line.
			 */
			elseif ($this->format == 'csv')
			{
			
				$result['_source']['coordinates'] = $result['_source']['coordinates'][1] . ',' . $result['_source']['coordinates'][0];
				
				/*
				 * If this query is only of business registration records (tables 2, 3, and 9), then
				 * homogenize them so that they all share the same field names.
				 */
				if ($this->params['type'] == '2,3,9')
				{
					
					foreach($result['_source'] as $key => $value)
					{
						
						/*
						 * Eliminate any field that isn't common to all types of business
						 * registration records.
						 */
						if (array_search($key, $cols) === FALSE)
						{
							unset($result['_source'][$key]);
						}
						
					}
					
				}
				
				fputcsv($fp, $result['_source']);
				
			}
			
			/*
			 * Force PHP to send the data to the browser, rather than hold it in memory.
			 */
			flush();

			$i++;
			
		}
		
		if ($this->format == 'json')
		{
			echo ']';
		}
		
		elseif ($this->format == 'csv')
		{
			fclose($fp);
		}
			
		return TRUE;
	
	} // end export_results()

	/**
	 * Get a record for a single business.
	 *
	 * Requires $this->id, return TRUE or FALSE, sets $this->record.
	 */
	function get_record()
	{


		if (!isset($this->id))
		{
			return FALSE;
		}

		/*
		 * This is where we'll store our search parameters.
		 */
		$params = array();

		/*
		 * The name of our Elasticsearch index.
		 */
		$params['index'] = 'business';

		/*
		 * Search for this ID, limit our search to the business records (as opposed to shareholder
		 * records, officers, mergers, etc.)
		 */
		$params['type'] = '2,3,9';
		$params['body']['query']['match']['_id']['query'] = $this->id;
		$params['body']['query']['match']['_id']['operator'] = 'and';

		/*
		 * Execute the search.
		 */
		$results = $es->search($params);

		if ( ($results === FALSE) || ($results['hits']['total'] == 0) )
		{
			return FALSE;
		}

		/*
		 * We'll only have one result, so pull it out of Elasticsearch's array structure.
		 */
		$result = $results['hits']['hits'][0];
		
		/*
		 * Raise coordinates up a level in the array structure.
		 */
		if (isset($result['_source']['location']['coordinates']))
		{
			$result['_source']['coordinates'] = $result['_source']['location']['coordinates'];
			unset($result['_source']['location']);
		}

		/*
		 * Retrieve additional data about this business: amendments, officers, DBAs, etc.
		 */

		return TRUE;

	}

}
