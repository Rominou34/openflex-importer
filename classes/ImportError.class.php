<?php

namespace OpenFlexImporter;

class ImportError {

    const TABLE_NAME = "openflex_import_errors";

    protected $id;
    protected $id_import = null;
    protected $type = 'error';
    protected $date_error;
    protected $error;
    protected $original_content;

    private static function getTableName() {
        global $wpdb;
        return $wpdb->prefix.static::TABLE_NAME;
    }

    public function __construct($id_import, $type, $error, $original_content) {
        $this->id_import = $id_import;
        $this->type = $type;
        $this->date_error = date("Y-m-d H:i:s");
        $this->error = $error;
        $this->original_content = $original_content;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'id_import' => $this->id_import,
            'type' => $this->type,
            'date_error' => $this->date_error,
            'error' => $this->error,
            'original_content' => $this->original_content
        ];
    }

    /**
     * Saves the import informations into the database
     */
    public function save() {
        global $wpdb;
        $wpdb->replace(static::getTableName(), $this->toArray());
    }

    public static function getByImport($id_import) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::getTableName()." WHERE id_import = %s", $id_import), \ARRAY_A);
    }
}

?>