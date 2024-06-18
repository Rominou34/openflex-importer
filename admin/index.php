<?php

require_once(__DIR__."/main.php");
require_once(__DIR__."/import.php");
require_once(__DIR__.'/../autoloader.php');

// create custom plugin settings menu
add_action('admin_menu', 'openflex_importer_create_menu');

function openflex_importer_create_menu() {

	//create new top-level menu
	add_menu_page('Import de produits - Paramètres', 'Import OpenFlex', 'administrator', __FILE__, 'openflex_settings_router' , plugins_url('/images/icon.png', __FILE__));

    // @TODO - Ajout submenu
	//add_menu_page('My Top Level Menu Example', 'Top Level Menu', 'manage_options', 'myplugin/myplugin-admin-page.php', 'myplguin_admin_page', 'dashicons-tickets', 6 );
	//add_submenu_page('myplugin/myplugin-admin-page.php', 'My Sub Level Menu Example', 'Sub Level Menu', 'manage_options', 'myplugin/myplugin-admin-sub-page.php', 'myplguin_admin_sub_page');

	//call register settings function
	add_action('admin_init', 'register_openflex_importer_settings');
}

/**
 * @TODO - Compléter ou virer
 */
function register_openflex_importer_settings() {
	//register our settings
	register_setting('my-cool-plugin-settings-group', 'new_option_name');
	register_setting('my-cool-plugin-settings-group', 'some_other_option');
	register_setting('my-cool-plugin-settings-group', 'option_etc');
}

/**
 * Redirects to the import details page if there is an import id in the URL, else redirects to the main page
 * @TODO - Mettre ça dans une fonction de manière un peu plus propre
 */
function openflex_settings_router() {
?>
<div class="wrap" id="products-importer">
    <h1>Import de produits</h1>
<?php
	if(!empty($_GET['id_import'])) {
		prodimp_show_import($_GET['id_import']);
	} else {
		openflex_main_page();
	}
?>
</div>
<style>
	#wpfooter {
		position: initial;
	}
</style>
<?php
}

// Add custom style
add_action('admin_enqueue_scripts', 'openflex_enqueue_style');
function openflex_enqueue_style() {
	wp_enqueue_style('openflex_style', plugin_dir_url( __FILE__ ).'style.css', false, '0.1');
    wp_enqueue_script('openflex_scripts', plugin_dir_url(__FILE__) . 'scripts.js', false, '0.1');
}