<?php

/**
 * The bulk data files offered for download.
 *
 * Defined here rather than in a page, because both the home page and /data/
 * list them. Keys are filenames within data/; values describe the contents and
 * are what a reader sees.
 *
 * Files absent from a given build are skipped by the pages that render this, so
 * adding an entry ahead of the data existing is safe.
 */
function data_files()
{
    return array(

        /*
         * Entity records, one file per type.
         */
        'corp.csv' => array(
            'label' => 'Corporations',
            'about' => 'Every corporation registered in Virginia.',
            'group' => 'Businesses',
        ),
        'llc.csv' => array(
            'label' => 'Limited liability companies',
            'about' => 'Every LLC registered in Virginia.',
            'group' => 'Businesses',
        ),
        'lp.csv' => array(
            'label' => 'Limited partnerships',
            'about' => 'Every limited partnership registered in Virginia.',
            'group' => 'Businesses',
        ),
        'gp.csv' => array(
            'label' => 'General partnerships',
            'about' => 'Every general partnership registered in Virginia.',
            'group' => 'Businesses',
        ),
        'bt.csv' => array(
            'label' => 'Business trusts',
            'about' => 'Every business trust registered in Virginia.',
            'group' => 'Businesses',
        ),
        'psa.csv' => array(
            'label' => 'Public service authorities',
            'about' => 'Water, sewer and other public service authorities.',
            'group' => 'Businesses',
        ),

        /*
         * Records about those entities.
         */
        'officer.csv' => array(
            'label' => 'Officers and directors',
            'about' => 'The people listed as officers or directors of each entity.',
            'group' => 'Related records',
        ),
        'amendment.csv' => array(
            'label' => 'Amendments',
            'about' => 'Changes filed against an entity’s registration.',
            'group' => 'Related records',
        ),
        'merger.csv' => array(
            'label' => 'Mergers',
            'about' => 'Mergers and consolidations between entities.',
            'group' => 'Related records',
        ),
        'name_history.csv' => array(
            'label' => 'Name history',
            'about' => 'Former and fictitious names used by an entity.',
            'group' => 'Related records',
        ),
        'reserved_name.csv' => array(
            'label' => 'Reserved names',
            'about' => 'Names reserved for future use.',
            'group' => 'Related records',
        ),

        /*
         * Reference data and the assembled database.
         */
        'tables.csv' => array(
            'label' => 'Descriptive tables',
            'about' => 'The codes used throughout the data, and what they mean.',
            'group' => 'Reference',
        ),
        'vabusinesses.sqlite' => array(
            'label' => 'Everything, as SQLite',
            'about' => 'All of the above, loaded into a single SQLite database.',
            'group' => 'Reference',
        ),

    );
}
