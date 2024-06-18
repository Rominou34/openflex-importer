<?php

function openflex_show_import($id_import) {
    $import = Products_Importer\Import::getById($id_import);

    // Loading the errors and warnings
    $warnings = [];
    $errors = [];
    $import_errors = Products_Importer\ImportError::getByImport($id_import);

    foreach($import_errors as $error) {
        if($error['type'] == "warning") {
            $warnings[] = $error;
        } else {
            $errors[] = $error;
        }
    }

    // If the import was not found in the history
    if(empty($import) || empty($import['id'])) {
        echo "L'import n'a pas été trouvé";
        return;
    }

    $ignored_lines = (int)$import['ignored_lines'];
    if($ignored_lines > 0) {
        $ignored_lines = "<span>$ignored_lines</span>";
    } else {
        $ignored_lines = "<span class='text-silver'>$ignored_lines</span>";
    }

    $error_lines = (int)$import['error_lines'];
    if($error_lines > 0) {
        $error_lines = "<span class='text-error bold'>$error_lines</span>";
    } else {
        $error_lines = "<span class='text-silver'>$error_lines</span>";
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
?>

<h2>
    Détail de l'import
</h2>
<div id="import-infos">
    <table>
        <thead>
            <th>
                ID import
            </th>
            <th>
                Date de début
            </th>
            <th>
                Date de fin
            </th>
            <th>
                Produits
            </th>
            <th>
                Statut
            </th>
            <th>
                Traités
            </th>
            <th>
                Ignorés
            </th>
            <th>
                Annulés
            </th>
        </thead>
        <tbody>
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
                    <?= $import['file_lines'] ?>
                </td>
                <td>
                    <?= $status ?>
                </td>
                <td>
                    <?= $import['imported_lines']; ?>
                </td>
                <td>
                    <?= $ignored_lines ?>
                </td>
                <td>
                    <?= $error_lines ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
    openflexRenderErrors($warnings, "avertissement");
    openflexRenderErrors($errors, "erreur");
}

function openflexRenderErrors($errors_list, $error_name) {
?>
    <h2>
        <?= ucfirst($error_name."s") ?>
        (<?= count($errors_list) ?>)
    </h2>
    <div class="error-list">
        <?php foreach($errors_list as $error) {
            $error_content = json_decode($error['error'], true); ?>
            <div class="error-block" data-id="<?= $error['id'] ?>">
                <div class="message">
                    <?= $error_content['message'] ?? "Pas de message d'erreur" ?>
                    <a href="javascript:showError(<?= $error['id'] ?>)" class="show-error">
                        Afficher
                    </a>
                    <a href="javascript:hideError(<?= $error['id'] ?>)" class="hide-error" style="display: none">
                        Masquer
                    </a>
                </div>
                <div class="error-details" style="display: none">
                    <!-- Exception trace !-->
                    <h3>Trace de l'erreur</h3>
                    <?php if(!empty($error_content)) { ?>
                        <pre><?= json_encode(str_replace("\n", "<br>", $error_content), JSON_PRETTY_PRINT) ?></pre>
                    <?php } else {
                        echo "Pas de trace";
                    } ?>
                    <!-- CSV content !-->
                    <h3>Contenu original du CSV</h3>
                    <?php if(!empty($error['original_content'])) { ?>
                        <pre><?= json_encode(json_decode($error['original_content'] ?? "[]"), JSON_PRETTY_PRINT) ?></pre>
                    <?php } else {
                        echo "Contenu original non trouvé";
                    } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php }