<?php

function openflex_main_page() {
    $import_list = OpenFlexImporter\Import::getAll();
?>

<div class="wrap">
    <div class="list-header">
        <h2>Historique des imports</h2>
        <div class="fields-container">
            <label for="use_config">
                Ville :
            </label>
            <select id="use_config" name="use_config">
                <option value="0" selected="selected">
                    Albi
                </option>
                <option value="1">
                    Toulouse
                </option>
                <option value="2">
                    Agen
                </option>
            </select>
            <button type="button" class="button button-primary" onclick="newImport()">
                Démarrer un nouvel import
            </button>
        </div>
    </div>
    <table>
        <thead>
            <th>ID</th>
            <th>Date de début</th>
            <th>Date de fin</th>
            <th>Produits</th>
            <th>Statut</th>
            <th>Traités</th>
            <th>Ignorés</th>
            <th>Annulés</th>
            <th>Actions</th>
        </thead>
        <tbody>
            <?php if(empty($import_list)) { ?>
                <tr>
                    <td class="text-center" colspan="100">
                        Aucun import trouvé
                    </td>
                </tr>
            <?php }
            foreach($import_list as $import) {
                openflexRenderRow($import);
            } ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
    function newImport() {
        jQuery.ajax({
            type: "POST",
            url: '<?= get_site_url() ?>/wp-admin/admin-ajax.php',
            data: {
                action : 'openflex_importer_import',
                use_config: jQuery('#use_config').val() || 0
            },
            success: function (res) {
                if (res) {
                    alert("L'import a démarré");
                }
            }
        });
    }

    function forceRelaunch(evt, id_import, offset) {
        evt.preventDefault();

        jQuery.ajax({
            type: "POST",
            url: '<?= get_site_url() ?>/wp-admin/admin-ajax.php',
            data: {
                action : 'openflex_importer_import',
                id_import: id_import,
                offset: offset
            },
            success: function (res) {
                if (res) {
                    alert("L'import a repris");
                }
            }
        });
    }
</script>

<?php
}

function openflexRenderRow($import) {
    $plugin_path = 'admin.php?page=products-importer/admin/index.php';

    $ignored_lines = (int)($import['ignored_lines'] ?? 0);
    $ignored_lines_text = $ignored_lines;
    if($ignored_lines > 0) {
        $ignored_lines_text = "<span>$ignored_lines</span>";
    } else {
        $ignored_lines_text = "<span class='text-silver'>$ignored_lines</span>";
    }

    $error_lines = (int)($import['error_lines'] ?? 0);
    $error_lines_text = $error_lines;
    if($error_lines > 0) {
        $error_lines_text = "<span class='text-error bold'>$error_lines</span>";
    } else {
        $error_lines_text = "<span class='text-silver'>$error_lines</span>";
    }

    $status = "";
    switch($import['status']) {
        case "inprogress":
            $status = "<span class='text-warning bold'>En cours</span>";
            break;
        case "failed":
            $status = "<span class='text-error'>Échoué</span>";
            break;
        case "done":
            $status = "<span class='text-success bold'>Terminé</span>";
            break;
    }

    $parsed_lines = (int)($import['imported_lines'] ?? 0) + $ignored_lines + $error_lines;
?>
    <tr>
        <td>
            <?= $import['id']; ?>
        </td>
        <td>
            <?= $import['date_start']; ?>
        </td>
        <td>
            <?= $import['date_end']; ?>
        </td>
        <td>
            <?= $import['file_lines']; ?>
        </td>
        <td>
            <?= $status ?>
        </td>
        <td>
            <?= $import['imported_lines']; ?>
        </td>
        <td>
            <?= $ignored_lines_text ?>
        </td>
        <td>
            <?= $error_lines_text ?>
        </td>
        <td>
            <?php $import_url = admin_url($plugin_path."&id_import=".$import['id']) ?>
            <a href="<?= $import_url ?>">Voir</a>
        </td>
    </tr>
    <?php if($import['status'] == "inprogress") { ?>
        <tr class="infos-line">
            <td colspan="100">
                L'import est immobile depuis trop longtemps ? Relancez-le.
                <a href="#" onclick="forceRelaunch(event, <?= $import['id'] ?>, <?= $parsed_lines ?>)">
                    Relancer
                </a>
            </td>
        </tr>
    <?php } ?>
<?php
}

function openflex_backup_options_form() {
?>
<div class="wrap">
<h1>Products Importer</h1>

<form method="post" action="options.php">
    <?php settings_fields( 'my-cool-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'my-cool-plugin-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">New Option Name</th>
        <td><input type="text" name="new_option_name" value="<?php echo esc_attr( get_option('new_option_name') ); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Some Other Option</th>
        <td><input type="text" name="some_other_option" value="<?php echo esc_attr( get_option('some_other_option') ); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Options, Etc.</th>
        <td><input type="text" name="option_etc" value="<?php echo esc_attr( get_option('option_etc') ); ?>" /></td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php } ?>