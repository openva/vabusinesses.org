<?php

require 'includes/header.php';
require 'includes/data-files.php';

$template = new Smarty;

$browser_title = 'Download the data — Virginia Businesses';
$page_title = 'Download the data';
$page_summary = 'Everything this site knows, as the files it was built from.';

$page_body = '
    <p>These are the bulk data files published by the Virginia State Corporation
    Commission, cleaned up and reassembled. They are refreshed weekly. Because
    they are public records created by the Commonwealth, they carry no copyright
    and may be reused freely.</p>';

/*
 * Group the files, so a long list reads as a few short ones. Files that are not
 * present in this build are skipped rather than offered as dead links.
 */
$groups = array();

foreach (data_files() as $filename => $file)
{
    $path = __DIR__ . '/data/' . $filename;

    if (!is_readable($path))
    {
        continue;
    }

    $file['filename'] = $filename;
    $file['size'] = human_filesize($path);
    $file['modified'] = filemtime($path);

    $groups[$file['group']][] = $file;
}

foreach ($groups as $group => $files)
{
    $page_body .= '
    <section>
        <h2>' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</h2>
        <table>
            <caption class="visually-hidden">' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</caption>
            <thead>
                <tr>
                    <th scope="col">File</th>
                    <th scope="col">Contents</th>
                    <th scope="col" class="numeric">Size</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($files as $file)
    {
        $page_body .= '
                <tr>
                    <td data-label="File"><a href="/data/' . rawurlencode($file['filename']) . '">'
                        . htmlspecialchars($file['label'], ENT_QUOTES, 'UTF-8') . '</a></td>
                    <td data-label="Contents">' . htmlspecialchars($file['about'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td data-label="Size" class="numeric">' . htmlspecialchars($file['size'], ENT_QUOTES, 'UTF-8') . '</td>
                </tr>';
    }

    $page_body .= '
            </tbody>
        </table>
    </section>';
}

if (empty($groups))
{
    $page_body .= '
    <p>No data files are available at the moment. They are rebuilt weekly; please
    try again later.</p>';
}

/*
 * When the files were last rebuilt, taken from the newest of them.
 */
$newest = 0;

foreach ($groups as $files)
{
    foreach ($files as $file)
    {
        $newest = max($newest, $file['modified']);
    }
}

if ($newest > 0)
{
    $page_body .= '
    <p class="meta">Last updated ' . date('F j, Y', $newest) . '.</p>';
}

$page_body .= '
    <section>
        <h2>The original data</h2>
        <p>The Commission publishes its own bulk download, which is the source
        for everything here. It is a single ZIP file, and the formatting is
        rougher: fields are padded, encodings are inconsistent, and some files
        have changed shape over the years.</p>
        <p><a href="https://cis.scc.virginia.gov/DataSales/DownloadBEDataSalesFile">Download
        it from the SCC</a>.</p>
    </section>';

$template->assign('needs_map', FALSE);
$template->assign('needs_statewide_map', FALSE);
$template->assign('page_body', $page_body);
$template->assign('page_title', $page_title);
$template->assign('page_summary', $page_summary);
$template->assign('browser_title', $browser_title);

$template->display('includes/templates/simple.tpl');
