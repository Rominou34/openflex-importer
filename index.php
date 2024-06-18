<?php
/**
 * @package Import OpenFlex
 * @version 0.1
 */
/*
Plugin Name: Import OpenFlex
Plugin URI: https://romainarnaud.fr/
Description: Importe des véhicules depuis l'API OpenFlex
Author: Romain Arnaud
Version: 0.1
Author URI: https://romainarnaud.fr/
*/

namespace OpenFlexImporter;

require_once(__DIR__.'/config.inc.php');
require_once(__DIR__.'/autoloader.php');

function openflex_importer_add_admin() {
	$pluginDirPath = plugin_dir_path(__FILE__);
	require($pluginDirPath.'admin/index.php');
}
add_action('wp_loaded', 'OpenFlexImporter\\openflex_importer_add_admin');

/**
 * Creating the tables in the database
 */
function openflex_install() {
	global $wpdb;
	global $openflex_db_version;

	$charset_collate = $wpdb->get_charset_collate();
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	// Create imports table
	$import_name = $wpdb->prefix . 'openflex_import';

	$history_sql = "CREATE TABLE IF NOT EXISTS $import_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		status ENUM('inprogress', 'done', 'failed') DEFAULT 'failed',
		date_start datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		date_end datetime DEFAULT NULL,
		file_lines INT(4) NOT NULL,
		imported_lines INT(4) DEFAULT 0,
		partial_lines INT(4) DEFAULT 0,
		error_lines INT(4) DEFAULT 0,
		ignored_lines INT(4) DEFAULT 0,
		PRIMARY KEY (id)
	) $charset_collate;";
	dbDelta($history_sql);

	// Create errors / warnings table
	$import_errors_name = $wpdb->prefix . 'openflex_import_errors';

	$errors_sql = "CREATE TABLE IF NOT EXISTS $import_errors_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		id_import mediumint(9) NOT NULL,
		type ENUM('warning', 'error') DEFAULT 'error',
		date_error datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		error TEXT NOT NULL,
		original_content TEXT NOT NULL,
		PRIMARY KEY (id),
		KEY `id_import` (`id_import`),
		CONSTRAINT `import_ibfk` FOREIGN KEY (id_import) REFERENCES $import_name(id) ON DELETE CASCADE ON UPDATE CASCADE
	) $charset_collate;";
	dbDelta($errors_sql);

	// @TODO - Voir à quoi sert ce machin
	add_option('openflex_db_version', $openflex_db_version);
}

register_activation_hook(__FILE__, 'OpenFlexImporter\\openflex_install');

/* Démarrage de l'import via appel AJAX */
function start_import() {
	Import::startImport($_REQUEST);
}

\add_action('wp_ajax_openflex_import', 'OpenFlexImporter\start_import');
\add_action('wp_ajax_nopriv_openflex_import', 'OpenFlexImporter\start_import');