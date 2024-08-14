<?php
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
	OLT_PLUGIN_UPDATE_URL,
	OLT_PLUGIN_FILE,
	'wp-outbound-link-tracker'
);