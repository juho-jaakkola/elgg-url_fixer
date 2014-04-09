<?php

$fixer = new UrlFixer();
$report = $fixer->simulate();

$count = count($report);
echo "<p>Found $count items</p>";

if ($report) {
	foreach ($report as $url) {
		$old = $url['old'];
		$new = $url['new'];
	
		$url_link = elgg_view('output/url', array(
			'href' => $old,
			'text' => $old,
		));
	
		$new_url_link = elgg_view('output/url', array(
			'href' => $new,
			'text' => $new,
		));
	
		echo "<p><pre>$url_link<br />$new_url_link</pre></p>";
	}
	
	echo elgg_view('output/confirmlink', array(
		'href' => 'action/url_fixer/run',
		'text' => elgg_echo('url_fixer:submit'),
		'confirm' => elgg_echo('url_fixer:confirm'),
		'class' => 'elgg-button elgg-button-action',
	));
} else {
	echo elgg_echo('url_fixer:none');
}
