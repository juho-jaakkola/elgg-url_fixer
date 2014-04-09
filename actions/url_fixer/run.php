<?php

$fixer = new UrlFixer();
$fixer->setSimulation(FALSE);
$fixer->run();

system_message(elgg_echo('url_fixer:finished'));

forward(REFERER);
