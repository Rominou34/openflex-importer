<?php

namespace OpenFlexImporter;

use DateTime;

class Import {

    const TABLE_NAME = "openflex_import";
    // Throws an error if one of those fields is empty
    const REQUIRED_FIELDS = [
        'name',
        'regular_price'
    ];
    const REQUIRED_POST_FIELD = [
        //'post_title'
    ];
    // All the basic fields with their setter functions
    const BASIC_FIELDS = [
        'sku' => 'set_sku',
        'name' => 'set_name',
        'description' => 'set_description',
        'short_description' => 'set_short_description',
        'regular_price' => 'set_regular_price',
        'tax_class' => 'set_tax_class'
    ];

    const REFERENCE_COLUMN = "internalNumber"; // ou entityId ?
    const REFERENCE_ATTRIBUTE = "sku";

    const ACF_FIELDS = [        
        'price' => 'price',
        'taxHorsepower' => 'fiscal_power',
        'displacement' => 'volume',
        'mileage' => 'km',
        'externalColorWording' => 'color',
        '5' => 'dealer_name',
        'length' => 'longueur',
        'width' => 'largeur',
        'wheelbase' => 'empattement',
        'height' => 'hauteur',
        'co2Emission' => 'co2',
        '11' => 'guarantee',
        'internalNumber' => 'reference'
    ];

    // Complex fields with longer import functions
    const COMPLEX_FIELDS = [
        'image_url' => 'importImage',
        'thumbnail_url' => 'importThumbnail',
        'categories_tree' => 'importCategoriesTree',
        'stock_quantity' => 'setStockQuantity'
    ];
    // Fields used on posts (not Woocommerce products)
    const POST_FIELDS = [
        'post_title',
        'post_name',
        'post_status'
    ];
    // Default values for all products
    const DEFAULT_VALUES = [];

    protected $id;
    protected $type;
    protected $status = 'inprogress';
    protected $date_start;
    protected $date_end;
    protected $file_lines = 0;
    protected $imported_lines = 0;
    protected $partial_lines = 0;
    protected $error_lines = 0;
    protected $ignored_lines = 0;
    public $use_config = 0;

    protected $filename;
    protected $importer;
    protected $counter;
    protected $categories_list;
    protected $is_custom_type = false;

    const STATUS_INPROGRESS = "inprogress";
    const STATUS_DONE = "done";
    const STATUS_FAILED = "failed";

    // Tableau associatif stockant, pour les taxonomies des équipements,
    // les id de chaque terme associé à sa référence
    private $_taxonomy_terms = [];

    private static function getTableName() {
        global $wpdb;
        return $wpdb->prefix.static::TABLE_NAME;
    }

    public function __construct($use_config = 0) {
        $this->date_start = date("Y-m-d H:i:s");
        $this->status = static::STATUS_INPROGRESS;
        $this->use_config = $use_config;

        $this->importer = new OpenFlexClient();
    }

    public static function load($id_import, $use_config = 0) {
        $import = static::getById($id_import);
        if(empty($import['id'])) {
            return new Import(null);
        } else {
            // @TODO - Enregistrer filename en bdd
            //$import_instance = new Import($import['filename']);
            $import_instance = new Import(\ABSPATH.OPENFLEX_CSV_PATH);
            $import_instance->id = $import['id'];
            $import_instance->status = $import['status'];
            $import_instance->date_start = $import['date_start'];
            $import_instance->date_end = $import['date_end'];
            $import_instance->file_lines = $import['file_lines'];
            $import_instance->imported_lines = $import['imported_lines'];
            $import_instance->partial_lines = $import['partial_lines'];
            $import_instance->error_lines = $import['error_lines'];
            $import_instance->ignored_lines = $import['ignored_lines'];
            $import_instance->use_config = $use_config;

            return $import_instance;
        }
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'file_lines' => $this->file_lines,
            'imported_lines' => $this->imported_lines,
            'partial_lines' => $this->partial_lines,
            'error_lines' => $this->error_lines,
            'ignored_lines' => $this->ignored_lines
        ];
    }

    /**
     * Saves the import informations into the database
     */
    public function save() {
        global $wpdb;
        if($this->id) {
            $wpdb->update(static::getTableName(), $this->toArray(), ['id' => $this->id]);
        } else {
            $wpdb->insert(static::getTableName(), $this->toArray());
            $this->id = $wpdb->insert_id;
        }
    }

    function basicWarning($message, $data) {
        $warning = new ImportError($this->id, 'warning', json_encode(['message' => $message]), json_encode($data));
        $warning->save();
    }

    /**
     * Handles the minor exceptions (i.e. product description is empty)
     * 
     * 
     * @param Throwable $error - The thrown error
     * @param array $data - The original line data
     */
    function handleWarning($error, $data) {
        $error_detail = [
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ];
        $warning = new ImportError($this->id, 'warning', json_encode($error_detail), json_encode($data));
        $warning->save();
    }

    /**
     * Handles the line major exceptions, which do not create / update the product
     * (i.e. product price is null or zero)
     * 
     * @param Throwable $error - The thrown error
     * @param array $data - The original line data
     */
    function handleError($error, $data) {
        $error_detail = [
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ];
        $error = new ImportError($this->id, 'error', json_encode($error_detail), json_encode($data));
        $error->save();
    }

    public static function getAll() {
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM ".self::getTableName()." ORDER BY date_start DESC", \ARRAY_A);
    }

    public static function getById($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM ".self::getTableName()." WHERE id = %s", $id), \ARRAY_A);
    }

    /**
     * Fonction statique permettant de démarrer l'import depuis un appel AJAX
     * ou un script. On vérifie la présence d'un id pour savoir si on doit
     * reprendre un import existant ou en créer un nouveau
     */
    public static function startImport($request = []) {
        try {
            file_put_contents(plugin_dir_path(__FILE__)."ajax_calls.log", "Called now : ".date("Y-m-d H:i:s")."\n", FILE_APPEND);
            file_put_contents(plugin_dir_path(__FILE__)."ajax_calls.log", json_encode($request, JSON_PRETTY_PRINT)."\n", FILE_APPEND);

            // If id_import is sent, we load the import with this id, else we create a new one
            if(!empty($request['id_import'])) {
                $import = static::load($request['id_import'], $request['use_config'] ?? 0);
                $import->importer = new OpenFlexClient();
            } else {
                $import = static::newImport($request['use_config'] ?? 0);
            }

            $offset = 0;
            if(!empty($request['offset_import'])) {
                $offset = (int)$request['offset_import'];
            }

            // Lancement de la fonction beforeImport
            if(!$import->id) {
                try {
                    static::beforeImport($import);
                } catch(\Exception $e) {
                    $import->handleError($e, []);
                }
            }

            // Empêche les boucles infinies via cURL
            if(!in_array($import->status, [static::STATUS_DONE, static::STATUS_FAILED])) {
                $import->import($offset);
            }

            try {
                if(in_array($import->status, [static::STATUS_DONE])) {
                    static::afterImport($import);
                }
            } catch(\Exception $e) {
                $import->handleError($e, []);
            }
        } catch(\Throwable $e) {
            // @TODO - Logger les grosses erreurs en bdd dans une table openflex_errors
            // Les erreurs concernées seront celles qui font planter l'import avant même qu'il commence
            // Par ex. timeout FTP, fichier pas trouvé, plus de Ram, etc.
            $logfile = plugin_dir_path(__FILE__)."errors.log";
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getMessage()."\n", FILE_APPEND);
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getFile()." : Line ".$e->getLine()."\n", FILE_APPEND);
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getTraceAsString()."\n", FILE_APPEND);
            var_dump($e);
        }
    }

    /**
     * Launches a new import
     */
    public static function newImport($use_config = 0) {
        try {
            $import = new Import($use_config);
            $import->save();
            return $import;
        } catch(\Throwable $e) {
            // @TODO - Logger les grosses erreurs en bdd dans une table openflex_errors
            // Les erreurs concernées seront celles qui font planter l'import avant même qu'il commence
            // Par ex. timeout FTP, fichier pas trouvé, plus de Ram, etc.
            $logfile = plugin_dir_path(__FILE__)."errors.log";
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getMessage()."\n", FILE_APPEND);
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getFile()." : Line ".$e->getLine()."\n", FILE_APPEND);
            file_put_contents($logfile, date("Y-m-d H:i:s")." - ".$e->getTraceAsString()."\n", FILE_APPEND);
            var_dump($e);
        }
    }


    public function import($offset = 0) {
        $imported = $this->imported_lines;
        $partial = $this->partial_lines;
        $errors = $this->error_lines;
        $ignored = $this->ignored_lines;

        // If we are not importing Woocommerce products, but posts of a custom type
        if(!in_array(OPENFLEX_POST_TYPE, ["product", "simple", "variable"])) {
            $this->is_custom_type = true;
        }

        $data = $this->importer->getVehicles(OPENFLEX_MAX_LINES_LOOP, $offset);
        var_dump($data);
        foreach($data as $row_data) {
            try {
                // @TODO - Rendre dynamique pour chaque produit
                $type = 'bike';

                $product_data = $row_data;

                // @TODO - Faire fonctionner pour les voitures
                if($type == 'bike') {
                    $result = $this->importBike($product_data['regular'], $row_data);
                } else {
                    $result = $this->importProduct($product_data['regular'], $row_data);
                }
                if(!empty($result) && is_array($result) && !empty($result['status'])) {
                    if($result['status'] == "ok") {
                        $imported++;
                        // Import after-save
                        if(!empty($result['id'])) {
                            if($type == 'bike') {
                                $this->persistBike($product_data['after'] ?? [], $row_data, $result['id']);
                            } else {
                                $this->importProduct($product_data['after'] ?? [], $row_data, $result['id']);
                            }
                        }
                    } elseif($result['status'] == "ignored") {
                        $ignored++;
                    }
                } else {
                    $errors++;
                }
                // @TODO - Supprimer
                //$created_product = \wc_get_product($product_id);
            } catch(\Throwable $e) {
                $errors++;
                $this->handleError($e, $row_data);
            }
        }

        $this->imported_lines = $imported;
        $this->error_lines = $errors;
        $this->ignored_lines = $ignored;
        $this->save();

        // Si on n'a pas fini d'importer, on fait un appel cURL pour continuer l'import
        file_put_contents(plugin_dir_path(__FILE__)."test.log", "-----\n", FILE_APPEND);
        file_put_contents(plugin_dir_path(__FILE__)."test.log", date('Y-m-d H:i:s')."\n", FILE_APPEND);
        file_put_contents(plugin_dir_path(__FILE__)."test.log", "imported : ".$imported."\n", FILE_APPEND);
        file_put_contents(plugin_dir_path(__FILE__)."test.log", "errors : ".$errors."\n", FILE_APPEND);
        file_put_contents(plugin_dir_path(__FILE__)."test.log", "ignored : ".$ignored."\n", FILE_APPEND);
        file_put_contents(plugin_dir_path(__FILE__)."test.log", "this->file_lines : ".$this->file_lines."\n", FILE_APPEND);

        // Si on est en mode autoloop et qu'il reste des lignes à importer
        $parsed_lines = $imported + $errors + $ignored + $offset;
        if(OPENFLEX_AUTOLOOP && ($parsed_lines < $this->file_lines)) {
            $url = \get_site_url(null, 'wp-admin/admin-ajax.php');
            file_put_contents(plugin_dir_path(__FILE__)."test.log", "url : ".$url."\n", FILE_APPEND);
            $id_import = $this->id ?? false;

            // On ne fait l'appel cURL que si l'id_import est renseignée sinon on a une boucle infinie
            if($id_import) {
                $args = [
                    'blocking' => false,
                    'body' => [
                        'action' => 'products_importer_import',
                        'id_import' => $this->id,
                        'offset_import' => $parsed_lines,
                        'use_config' => $this->use_config
                    ]
                ];
                \wp_remote_post($url, $args);
            }
            // $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_HEADER, TRUE);
            // curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            // $head = curl_exec($ch);
            // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close($ch);
        } else {
            $this->date_end = date("Y-m-d H:i:s");
            $this->status = static::STATUS_DONE;
            $this->save();

            // @TODO - Faire fonctionner. Permet de réindexer FacetWP et clean le cache RocketWP
            // Choppé dans le fichier d'import de Choosit
            if(false) {
                FWP()->indexer->index();

                \WP_CLI::log('Clean Rocket WP cache');
                if (function_exists('rocket_clean_domain')) {
                    rocket_clean_domain();
                }
            }
        }
    }

    /* 
     * Custom function for product creation (For Woocommerce 3+ only)
     * Copied from here : https://stackoverflow.com/a/52941994/4840882
     *
     * @param array $args - Data to import
     * @param array $original_data - Original data (as in the CSV file)
     * @param int $product_id - The product ID (null on the original import, not null in the after import)
     */
    private function importProduct($args, $original_data, $product_id = null) {
        $reference_column = OPENFLEX_REFERENCE_COLUMN ?? "sku";
        $product_ref = null;
        $compare = '=';
        if(is_array($reference_column)) {
            $compare = 'IN';
            $product_ref = [];
            foreach($reference_column as $col) {
                $product_ref[] = $original_data[$col];
            }
            if(!preg_match('/acf\:.*/', static::REFERENCE_ATTRIBUTE)) {
                $product_ref = reset($product_ref);
            }
        } else {
            $product_ref = $original_data[$reference_column];
        }
        if(empty($product_ref)) {
            throw new \Exception("Référence produit vide");
        }

        if(!$this->is_custom_type) {
            if(empty($product_id)) {
                // Create or update product based on config
                if(OPENFLEX_ACTION != "CREATE") {
                    if(static::REFERENCE_ATTRIBUTE == "id") {
                        $product_id = $product_ref;
                    } elseif (preg_match('/acf\:.*/', static::REFERENCE_ATTRIBUTE)) {
                        $acf_field = substr(static::REFERENCE_ATTRIBUTE, 4);
                        $query_args = array(
                            'numberposts'	=> 1,
                            'post_type'		=> 'product',
                            'meta_key'		=> $acf_field,
                            'meta_value'	=> $product_ref,
                            'compare'       => $compare
                        );

                        $the_query = new \WP_Query($query_args);
                        $posts = $the_query->posts ?? [];
                        $product = !empty($posts) ? reset($posts) : null;

                        $product_id = $product ? $product->ID : null;
                    } else {
                        // @TODO - Utiliser static::REFERENCE_ATTRIBUTE pour recherche sur le bon paramètre
                        $product_id = \wc_get_product_id_by_sku($product_ref);
                    }

                    if($product_id) {
                        $product = \wc_get_product($product_id);
                    } elseif(OPENFLEX_ACTION == "UPDATE_OR_CREATE") {
                        $product = $this->getProductObjectType($args['type']);
                    }
                } else {
                    // Get an empty instance of the product object (defining it's type)
                    $product = $this->getProductObjectType($args['type']);
                }

                // @TODO - Améliorer la levée d'erreurs
                if(!$product) {
                    if(OPENFLEX_ACTION == "UPDATE") {
                        return ['status' => 'ignored'];
                    } else {
                        try {
                            throw new \Exception("Le produit n'a pas été trouvé et n'a pas pu être créé");
                        } catch(\Exception $e) {
                            $this->handleError($e, $original_data);
                            return ['status' => 'error'];
                        }
                    }
                    return ['status' => 'error'];
                }
            } else {
                $product = \wc_get_product($product_id);
            }
        } else {
            $post_infos = [
                'post_type' => OPENFLEX_POST_TYPE
            ];

            if(empty($product_id)) {
                // Create or update product based on config
                if(OPENFLEX_ACTION != "CREATE") {
                    if(static::REFERENCE_ATTRIBUTE == "id") {
                        $post = get_post($product_ref);
                    } elseif (preg_match('/acf\:.*/', static::REFERENCE_ATTRIBUTE)) {
                        $acf_field = substr(static::REFERENCE_ATTRIBUTE, 4);
                        $query_args = array(
                            'numberposts'	=> 1,
                            'post_type'		=> OPENFLEX_POST_TYPE,
                            'meta_key'		=> $acf_field,
                            'meta_value'	=> $product_ref,
                            'compare'       => $compare
                        );

                        $the_query = new \WP_Query($query_args);
                        $posts = $the_query->posts ?? [];
                        $post = !empty($posts) ? reset($posts) : null;

                        $product_id = $post ? $post->ID : null;
                    }
                    // @TODO - Faire les autres colonnes

                    if(!empty($post)) {
                        $post_infos = [
                            'ID' => $product_id,
                            'post_type' => OPENFLEX_POST_TYPE
                        ];
                    } elseif(OPENFLEX_ACTION == "UPDATE_OR_CREATE") {
                        $post_infos = [
                            'post_type' => OPENFLEX_POST_TYPE
                        ];
                    }
                } else {
                    // Get an empty instance of the product object (defining it's type)
                    $post_infos = [
                        'post_type' => OPENFLEX_POST_TYPE
                    ];
                }

                // @TODO - Améliorer la levée d'erreurs
                if(!$post_infos) {
                    if(OPENFLEX_ACTION == "UPDATE") {
                        return ['status' => 'ignored'];
                    } else {
                        try {
                            throw new \Exception("Le produit n'a pas été trouvé et n'a pas pu être créé");
                        } catch(\Exception $e) {
                            $this->handleError($e, $original_data);
                            return ['status' => 'error'];
                        }
                    }
                    return ['status' => 'error'];
                }
            } else {
                $post_infos = [
                    'ID' => $product_id,
                    'post_type' => OPENFLEX_POST_TYPE
                ];
            }
        }

        // // Product name (Title) and slug
        // $product->set_name($args['name']); // Name (title).

        // // Prices
        // $product->set_regular_price($args['regular_price']);
        // $product->set_sale_price(isset($args['sale_price']) ? $args['sale_price'] : '');
        // $product->set_price(isset($args['sale_price']) ? $args['sale_price'] :  $args['regular_price']);
        // if(isset($args['sale_price'])){
        //     $product->set_date_on_sale_from(isset($args['sale_from']) ? $args['sale_from'] : '');
        //     $product->set_date_on_sale_to(isset($args['sale_to']) ? $args['sale_to'] : '');
        // }

        // Ajout du libellé
        $vehicle_name = $this->getVehicleName($original_data);
        $post_infos['post_title'] = $vehicle_name;

        /*
         * We loop through all the fields and use the corresponding function
         */
        foreach($args as $column => $value) {
            // On ignore les colonnes ignore
            if(strpos($column, "ignore")) {
                continue;
            }
            try {
                if(empty($value)) {
                    if(OPENFLEX_VALUE_IF_EMPTY && !empty(OPENFLEX_VALUE_IF_EMPTY) && isset(OPENFLEX_VALUE_IF_EMPTY[$column])) {
                        $value = OPENFLEX_VALUE_IF_EMPTY[$column];
                    } else {
                        throw new \Exception("La colonne $column est vide");
                    }
                }

                // For each column, we use the corresponding function to correctly import the value
                if(!empty($function = static::BASIC_FIELDS[$column] ?? false)) {
                    $product->$function($value);
                } elseif (!empty($complex_function = static::COMPLEX_FIELDS[$column] ?? false)) {
                    $this->$complex_function($product, $value);
                } elseif ($this->is_custom_type && in_array($column, static::POST_FIELDS)) {
                    $post_infos[$column] = $value;
                } elseif (preg_match('/acf\:.*/', $column)) {
                    $acf_field = substr($column, 4);
                    if(function_exists('update_field')) {
                        $updated = update_field($acf_field, $value, $product_id);
                    }
                } else if(preg_match('/taxonomy\:.*/', $column)) {
                    $taxonomy = substr($column, 9);
                    $term = get_term_by('slug', $value, $taxonomy);
                    if(!empty($term) && !empty($term->term_id)) {
                        wp_set_post_terms($product_id, [(int)$term->term_id], $taxonomy);
                    }
                }
            } catch(\Throwable $e) {
                if(in_array($column, static::REQUIRED_FIELDS)) {
                    $this->handleError($e, $original_data);
                    return ['status' => 'error'];
                } else {
                    $this->handleWarning($e, $original_data);
                }
            }
        }

        // We complete empty values and do other stuff
        if(!$this->is_custom_type) {
            $this->fillMiscData($product, $args);
        }

        ## --- SAVE PRODUCT --- ##
        if($this->is_custom_type) {
            var_dump(">>>>>>>>>>>>>>> POST INFOS");
            var_dump($post_infos);
            if($product_id) {
                wp_update_post($post_infos);
                var_dump("UPDATE XXXXXXXXXXX");
            } else {
                $product_id = wp_insert_post($post_infos);
                var_dump("INSERT XXXXXXXXXXX");
            }
        } else {
            $product_id = $product->save();
        }

        return ['status' => 'ok', 'id' => $product_id];
    }

    private function importBike($args, $original_data, $product_id = null) {
        var_dump(">>> IMPORT BIKE <<<");
        $reference_column = static::REFERENCE_COLUMN ?? "sku";
        $product_ref = null;
        $compare = '=';
        if(is_array($reference_column)) {
            $compare = 'IN';
            $product_ref = [];
            foreach($reference_column as $col) {
                $product_ref[] = $original_data[$col];
            }
            if(!preg_match('/acf\:.*/', static::REFERENCE_ATTRIBUTE)) {
                $product_ref = reset($product_ref);
            }
        } else {
            $product_ref = $original_data[$reference_column];
        }
        if(empty($product_ref)) {
            throw new \Exception("Référence produit vide");
        }

        $post_infos = [
            'post_type' => 'motos'
        ];

        if(empty($product_id)) {
            // Create or update product based on config
            if(OPENFLEX_ACTION != "CREATE") {
                if(static::REFERENCE_ATTRIBUTE == "id") {
                    $post = get_post($product_ref);
                } elseif (preg_match('/acf\:.*/', static::REFERENCE_ATTRIBUTE)) {
                    $acf_field = substr(static::REFERENCE_ATTRIBUTE, 4);
                    $query_args = array(
                        'numberposts'	=> 1,
                        'post_type'		=> OPENFLEX_POST_TYPE,
                        'meta_key'		=> $acf_field,
                        'meta_value'	=> $product_ref,
                        'compare'       => $compare
                    );

                    $the_query = new \WP_Query($query_args);
                    $posts = $the_query->posts ?? [];
                    $post = !empty($posts) ? reset($posts) : null;

                    $product_id = $post ? $post->ID : null;
                }
                // @TODO - Faire les autres colonnes

                if(!empty($post)) {
                    $post_infos = [
                        'ID' => $product_id,
                        'post_type' => OPENFLEX_POST_TYPE
                    ];
                } elseif(OPENFLEX_ACTION == "UPDATE_OR_CREATE") {
                    $post_infos = [
                        'post_type' => OPENFLEX_POST_TYPE
                    ];
                }
            } else {
                // Get an empty instance of the product object (defining it's type)
                $post_infos = [
                    'post_type' => OPENFLEX_POST_TYPE
                ];
            }

            // @TODO - Améliorer la levée d'erreurs
            if(!$post_infos) {
                if(OPENFLEX_ACTION == "UPDATE") {
                    return ['status' => 'ignored'];
                } else {
                    try {
                        throw new \Exception("Le produit n'a pas été trouvé et n'a pas pu être créé");
                    } catch(\Exception $e) {
                        $this->handleError($e, $original_data);
                        return ['status' => 'error'];
                    }
                }
                return ['status' => 'error'];
            }
        } else {
            $post_infos = [
                'ID' => $product_id,
                'post_type' => OPENFLEX_POST_TYPE
            ];
        }

        // // Product name (Title) and slug
        // $product->set_name($args['name']); // Name (title).

        // // Prices
        // $product->set_regular_price($args['regular_price']);
        // $product->set_sale_price(isset($args['sale_price']) ? $args['sale_price'] : '');
        // $product->set_price(isset($args['sale_price']) ? $args['sale_price'] :  $args['regular_price']);
        // if(isset($args['sale_price'])){
        //     $product->set_date_on_sale_from(isset($args['sale_from']) ? $args['sale_from'] : '');
        //     $product->set_date_on_sale_to(isset($args['sale_to']) ? $args['sale_to'] : '');
        // }

        // Ajout du libellé
        $vehicle_name = $this->getVehicleName($original_data);
        $post_infos['post_title'] = $vehicle_name;


        // Sauvegarde du post
        if($product_id) {
            wp_update_post($post_infos);
            var_dump("UPDATE XXXXXXXXXXX");
        } else {
            $product_id = wp_insert_post($post_infos);
            var_dump("INSERT XXXXXXXXXXX");
        }

        $product = \get_post($product_id);

        // Post-sauvegarde
        foreach($args as $column => $value) {
            // On ignore les colonnes ignore
            if(strpos($column, "ignore")) {
                continue;
            }
            try {
                if(empty($value)) {
                    if(OPENFLEX_VALUE_IF_EMPTY && !empty(OPENFLEX_VALUE_IF_EMPTY) && isset(OPENFLEX_VALUE_IF_EMPTY[$column])) {
                        $value = OPENFLEX_VALUE_IF_EMPTY[$column];
                    } else {
                        throw new \Exception("La colonne $column est vide");
                    }
                }

                // For each column, we use the corresponding function to correctly import the value
                if(!empty($function = static::BASIC_FIELDS[$column] ?? false)) {
                    $product->$function($value);
                } elseif (!empty($complex_function = static::COMPLEX_FIELDS[$column] ?? false)) {
                    $this->$complex_function($product, $value);
                } elseif ($this->is_custom_type && in_array($column, static::POST_FIELDS)) {
                    $post_infos[$column] = $value;
                } elseif (preg_match('/acf\:.*/', $column)) {
                    $acf_field = substr($column, 4);
                    if(function_exists('update_field')) {
                        $updated = update_field($acf_field, $value, $product_id);
                    }
                } else if(preg_match('/taxonomy\:.*/', $column)) {
                    $taxonomy = substr($column, 9);
                    $term = get_term_by('slug', $value, $taxonomy);
                    if(!empty($term) && !empty($term->term_id)) {
                        wp_set_post_terms($product_id, [(int)$term->term_id], $taxonomy);
                    }
                }
            } catch(\Throwable $e) {
                if(in_array($column, static::REQUIRED_FIELDS)) {
                    $this->handleError($e, $original_data);
                    return ['status' => 'error'];
                } else {
                    $this->handleWarning($e, $original_data);
                }
            }
        }

        // We complete empty values and do other stuff
        if(!$this->is_custom_type) {
            $this->fillMiscData($product, $args);
        }

        ## --- SAVE PRODUCT --- ##
        if($this->is_custom_type) {
            var_dump(">>>>>>>>>>>>>>> POST INFOS");
            var_dump($post_infos);
            if($product_id) {
                wp_update_post($post_infos);
                var_dump("UPDATE XXXXXXXXXXX");
            } else {
                $product_id = wp_insert_post($post_infos);
                var_dump("INSERT XXXXXXXXXXX");
            }
        } else {
            $product_id = $product->save();
        }

        return ['status' => 'ok', 'id' => $product_id];
    }

    private function persistBike($args, $original_data, $product_id = null) {
        foreach(self::ACF_FIELDS as $col => $field) {
            if(!empty($original_data[$col])) {
                update_field($field, $original_data[$col], $product_id);
            }
        }

        $this->setFinition($original_data, $product_id);
        $this->setPuissance($original_data, $product_id);
        $this->setKmGaranti($original_data, $product_id);
        $this->setPremiereMain($original_data, $product_id);
        $this->setDateMec($original_data, $product_id);
        $this->setFullSocieteAdresse($original_data, $product_id);
        $this->setBikeBrand($original_data, $product_id);
        $this->setBikeFuel($original_data, $product_id);
        // @TODO - Faire fonctionner
        $this->setBikeCategory($original_data, $product_id);
        // @TODO - Faire fonctionner
        $this->setBikeCity($original_data, $product_id);

        $this->importDetails($original_data, $product_id, 'bike');
        $this->setGrimDate($product_id);
    }

    // Utility function that returns the correct product object instance
    // @TODO - Supprimer
    private function getProductObjectType($type) {
        // Get an instance of the WC_Product object (depending on his type)
        if(isset($type) && $type === 'variable'){
            $product = new \WC_Product_Variable();
        } elseif(isset($type) && $type === 'grouped'){
            $product = new \WC_Product_Grouped();
        } elseif(isset($type) && $type === 'external'){
            $product = new \WC_Product_External();
        } else {
            $product = new \WC_Product_Simple(); // "simple" By default
        } 
        
        if(!is_a($product, 'WC_Product'))
            return false;
        else
            return $product;
    }

    /*
     * Complex import functions
     */
    private function importImage($product, $image_url) {
        $image_id = $this->importImageFromUrl($image_url, "product_image");
        $product->set_image_id($image_id);
    }

    private function importThumbnail($product, $image_url) {
        $image_id = $this->importImageFromUrl($image_url, "product_thumbnail");
        $product->set_image_id($image_id);
    }

    /**
     * Imports an image from the given url
     * From https://wordpress.stackexchange.com/a/371360
     * 
     * @param string $image_url - The URL of the image
     * @param $media_type - The type of media (optional)
     * 
     * @return int $image_id - The ID of the image in the wordpress library
     */
    private function importImageFromUrl($image_url, $media_type = null) {
        $image_url = $this->formatMediaUrl($image_url, $media_type);
        $file_array  = [
            'name' => \wp_basename($image_url),
            'tmp_name' => \download_url($image_url)
        ];

        // If error storing temporarily, return the error.
        if (\is_wp_error($file_array['tmp_name'])) {
            @unlink($file_array['tmp_name']);
            return $file_array['tmp_name'];
        }

        // Do the validation and storage stuff.
        $image_id = \media_handle_sideload($file_array, 0);

        // If error storing permanently, unlink.
        if (\is_wp_error($image_id)) {
            @unlink($file_array['tmp_name']);
        }
        return $image_id;
    }

    /**
     * Selects the given categories for the article
     * 
     * @param WC_Product $product - The currently imported product
     * @param string $categories_tree - The list of categories with breadcrumb format
     * (i.e. "Category 1 > Subcategory 1,Category 1 > Subcategory 2")
     */
    private function importCategoriesTree($product, $categories_tree) {
        $trees = explode(\OPENFLEX_CATEGORIES_TREE_SEPARATOR, $categories_tree);

        $category_ids = [];

        foreach($trees as $tree) {
            $categories = explode(\OPENFLEX_CATEGORIES_TREE_BREADCRUMB, $tree);
            // Loop through the hierarchy and find each category or create it
            $categ_id = null;
            foreach($categories as $category) {
                $categ_id = $this->getCategory($category, \OPENFLEX_CATEGORIES_TREE_ATTRIBUTE, $categ_id);
            }
            if($categ_id) {
                $category_ids[] = $categ_id;
            }
        }
        if(!empty($category_ids)) {
            $product->set_category_ids($category_ids);
        }
    }

    /**
     * Fins the category with the given value, for the given attribute
     * 
     * @param string $category - The value to search for (name, slug, etc.)
     * @param string $attribute - The concerned attribute (name, slug, etc.)
     * 
     * @return $categ_id - The ID of the found or created category. Null if not found and not created
     */
    private function getCategory($category, $attribute, $id_parent = null) {
        // Loads the categories list if it is not already done
        if(empty($this->categories_list)) {
            $args = array(
                'taxonomy'   => "product_cat",
                'hide_empty' => false
            );
            $this->categories_list = \get_terms($args);
            $this->categories_list = json_decode(json_encode($this->categories_list ?? []), true); // To array
        }
        // Actually gets the category
        $categ_id = $this->findCategoryId($category, $attribute, $id_parent);

        // If the category is not found and the configuration says to create it, we create it
        if(empty($categ_id) && \OPENFLEX_CATEGORIES_CREATE_IF_NULL) {
            $categ_id = $this->createCategory($category, $id_parent);
        }
        return $categ_id;
    }

    /**
     * Search the categories list for the given column, returns null if not found
     * 
     * @param string $value - The value to search for
     * @param string $column - The concerned column (name / slug / name_to_slug)
     */
    private function findCategoryId($value, $column, $id_parent = null) {
        if($column == "name") {
            $value = htmlspecialchars($value);
        }

        foreach($this->categories_list as $category) {
            if($category[$column] === $value && (!$id_parent || $category['parent'] == $id_parent)) {
                return $category['term_id'];
            }
        }
        return null;
    }

    /**
     * Creates the category with the given name and parent and add it to the categories_list variable
     * 
     * @param string $name - The name of the category (slug will be automatically generated)
     * @param int $id_parent - The ID of the parent
     */
    private function createCategory($name, $id_parent = 0) {
        $slug = \sanitize_title($name);
        $categ_id = \wp_insert_term($name, 'product_cat', array (
            'parent' => $id_parent,
            'slug' => $slug
        ));

        // Catch errors
        if(is_wp_error($categ_id)) {
            throw new \Exception($categ_id->get_error_message());
        } else {
            $categ_id = $categ_id['term_id'];
        }

        // Add the created category to the list
        $this->categories_list[] = [
            'term_id' => $categ_id,
            'name' => $name,
            'slug' => $slug,
            'taxonomy' => 'product_cat',
            'parent' => $id_parent
        ];

        return $categ_id;
    }

    /**
     * Formats the given media URL depending on the config (i.e. transform a relative URL into an absolute one)
     * @TODO - Déplacer dans une classe Helpers
     * 
     * @param $media_url - The URL of the media
     * @param $media_type - The type of media (optional)
     * 
     * @return $url - The absolute full url of the media
     */
    private function formatMediaUrl($media_url, $media_type = null) {
        $url = $media_url;
        if(!\OPENFLEX_MEDIA_ABSOLUTE) {
            if(empty(\OPENFLEX_BASE_URL)) {
                throw new \Exception("La variable de configuration OPENFLEX_BASE_URL n'est pas renseignée, alors qu'elle est nécessaire à cause de OPENFLEX_MEDIA_ABSOLUTE");
            }
            switch(\OPENFLEX_MEDIA_FORMAT) {
                case "magento":
                    $url = $this->formatMagentoMediaUrl($media_url, $media_type);
                    break;
                default:
                    $folder = "";
                    if(!empty($media_type) && !empty(\OPENFLEX_MEDIA_FOLDERS[$media_type])) {
                        $folder = \OPENFLEX_MEDIA_FOLDERS[$media_type];
                    }
                    $url = \OPENFLEX_BASE_URL.$folder.$media_url;
            }
        }

        // We eventually transform the generated URL
        if(!empty(\OPENFLEX_MEDIA_TRANSFORM_URL)) {
            if(\OPENFLEX_MEDIA_TRANSFORM_URL == "uppercase") {
                $url = strtoupper($url);
            } elseif(\OPENFLEX_MEDIA_TRANSFORM_URL == "lowercase") {
                $url = strtolower($url);
            }
        }

        return $url;
    }

    /**
     * Formats the given media url for magento
     * 
     * @param string $media_url - The URL of the media
     * @param string $media_type - The type of media (optional)
     * 
     * @return $url - The URL
     */
    private function formatMagentoMediaUrl($media_url, $media_type = null) {
        switch($media_type) {
            case "product_image":
            case "product_thumbnail":
                $folder = "/pub/media/catalog/product/";
                $folder.= substr($media_url, 0, 1)."/";
                $folder.= substr($media_url, 1, 1)."/";
                $url = \OPENFLEX_BASE_URL.$folder.$media_url;
                break;
            default:
                $url = \OPENFLEX_BASE_URL.$media_url;
        }
        return $url;
    }

    // Fill some columns and do some stuff
    private function fillMiscData($product, $data) {
        // Set the product price as the sale price if defined, else as the regular price
        if(!empty($data['regular_price']) || !empty($data['sale_price'])) {
            $product->set_regular_price($data['regular_price']);
            $product->set_sale_price(isset($data['sale_price']) ? $data['sale_price'] : '');
            $product->set_price(isset($data['sale_price']) ? $data['sale_price'] :  $data['regular_price']);

            if(isset($data['sale_price'])){
                $product->set_date_on_sale_from(isset($data['sale_from']) ? $data['sale_from'] : '');
                $product->set_date_on_sale_to(isset($data['sale_to']) ? $data['sale_to'] : '');
            }
        }
    }

    // Sets the stock quantity and the correct stock status
    private function setStockQuantity($product, $data) {
        // @TODO - Mettre ça dans une variable de config et l'appliquer à toutes la lignes
        // comme ça on pourra un faire un import avec que des articles virtuels en mettant la config à false
        //$product->set_manage_stock(true);
        $product->set_stock_status($data['stock_quantity'] && $data['stock_quantity'] > 0 ? 'instock' : 'outofstock');
        $product->set_stock_quantity($data['stock_quantity']);
        // @TODO - Implémenter
        //$product->set_backorders(isset($args['backorders']) ? $args['backorders'] : 'no'); // 'yes', 'no' or 'notify'
    }

    private static function beforeImport($import = null) {}
    private static function afterImport($import = null) {
        $ftp_config = OPENFLEX_FTP_CONFIGS[$import->use_config] ?? [];
        $city = $ftp_config["city"] ?? false;
        if(!$city) {
            return;
        }

        var_dump("== afterImport ==");
        var_dump($city);
        // Archivage des motos dont la référence n'est pas présente dans le fichier
        $whole_importer = new OpenFlexClient(\ABSPATH.OPENFLEX_CSV_PATH, true);
        $data = $whole_importer->getVehicles();

        $references = array_map(function($line) {
            return $line['VehiculeReference'] ?? NULL;
        }, $data);
        $references = array_unique(array_filter($references));

        $delete_args = array(
            'post_type'  => 'motos',
            'posts_per_page' => '-1',
            'meta_query' => array(
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'reference',
                        'value'   => $references,
                        'compare' => 'NOT IN'
                    ),
                    array(
                        'key'     => 'reference',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ),
            'tax_query' => array(
                array(
                    'relation' => 'OR',
                    array(
                        'taxonomy'         => 'moto_city',
                        'terms'            => $city,
                        'field'            => 'slug'
                    ),
                    array(
                        'taxonomy'     => 'moto_city',
                        'operator' => 'NOT EXISTS'
                    )
                )
            )
        );
        var_dump($delete_args);
        $delete_query = new \WP_Query($delete_args);
        $motos_to_delete = $delete_query->posts ?? [];
        $count_delete = count($motos_to_delete);

        $logfile = plugin_dir_path(__FILE__)."/classes/deleted.log";
        file_put_contents($logfile, date("Y-m-d H:i:s")." - {$count_delete} motos à supprimer\n", FILE_APPEND);

        var_dump($motos_to_delete);
        if(!empty($motos_to_delete)) {
            foreach($motos_to_delete as $motos_to_delete) {
                file_put_contents($logfile, date("Y-m-d H:i:s")." - {$motos_to_delete->ID} supprimée\n", FILE_APPEND);
                //\wp_delete_post($motos_to_delete->ID);
            }
        }
        file_put_contents($logfile, "\n", FILE_APPEND);

        // Nettoyage du cache (désactivé car trop long)
        // if(function_exists('FWP')) {
        //     FWP()->indexer->index();

        //     \WP_CLI::log('Clean Rocket WP cache');
        //     if (function_exists('rocket_clean_domain')) {
        //         rocket_clean_domain();
        //     }
        // }
    }

    /*
     * Fonctions pour champs d'import spéciaux
     */
    public static function parseEquipment($product_infos, $product_id, $value) {
        $terms_to_check = [
            "/bluetooth/i" => "bluetooth",
            "/shifter.*pro/i" => "shifter-pro",
            "/(keyless|sans.*cle|sans.*clef)/i" => "demarrage-sans-cle",
            "/poignee.*chauffante/i" => "poignees-chauffantes",
            "/sellle.*chauffante/i" => "selle-chauffante",
            "/feux.*route/i" => "feux-route",
            "/kit.*surbaissement/i" => "kit-surbaissement",
            "/appel.*urgence/i" => "appel-urgence",
            "/teleservice/i" => "teleservice"
        ];

        $equipments = explode("|", $value);
        // Filtre des trucs bidons
        $equipments = array_filter($equipments, function($equip) {
            return $equip != "...";
        });

        $acf_values = [];
        $terms = [];
        foreach($equipments as $equip) {
            $acf_values[] = [
                'item' => $equip
            ];

            $equip_slug = sanitize_title($equip);

            // On regarde si l'équipement fait partie de ceux affichés en icône
            foreach($terms_to_check as $regex => $term_ref) {
                if(preg_match($regex, $equip_slug)) {
                    $term = get_term_by('slug', $term_ref, "equipment_moto");
                    if(!empty($term) && !empty($term->term_id)) {
                        $terms[] = (int)$term->term_id;
                    }
                }
            }
        }

        update_field("equipments_serial", $acf_values, $product_id);

        if(!empty($terms)) {
            wp_set_post_terms($product_id, $terms, "equipment_moto", true);
        }

        return $product_infos;
    }

    /**
     * Génère le nom de la moto en combinant la marque et le modèle
     */
    public static function getVehicleName($original_data) {
        $post_title = [];
        if(!empty($original_data['make'])) {
            $post_title[] = $original_data['make'];
        }
        // Pas de finition dans le nom du véhicule
        // if(!empty($original_data['version'])) {
        //     $post_title[] = $original_data['version'];
        // } elseif(!empty($original_data['model'])) {
        //     $post_title[] = $original_data['model'];
        // }
        if(!empty($original_data['model'])) {
            $post_title[] = $original_data['model'];
        }
        return implode(" ", $post_title);
    }

    /**
     * Génère le nom de la finition en combinant la marque, le modèle et l'année de sortie
     */
    public static function setFinition($original_data, $product_id) {
        $nom_moto = [];
        if(!empty($original_data['version'])) {
            $post_title[] = $original_data['version'];
        } elseif(!empty($original_data['model'])) {
            $post_title[] = $original_data['model'];
        }

        $finition = implode(" ", $nom_moto);

        // Ajout des infos complémentaires
        if(!empty($original_data['externalColorWording'])) {
            $finition .= ", {$original_data['externalColorWording']}";
        }
        if(!empty($original_data['horsepower'])) {
            $finition .= ", {$original_data['horsepower']} cv";
        }

        update_field("finishes", $finition, $product_id);
    }

    /**
     * Parse la puissance pour voir si la moto est éligible au permis A2
     */
    public static function setPuissance($original_data, $product_id) {
        // Si puissance < 47.5 chevaux, on ajoute l'équipement "Permis A2"
        if(!empty($original_data['horsepower'])) {
            $puissance = (float)$original_data['horsepower'];
            if($puissance <= 47.5) {
                $term = get_term_by('slug', 'permis-a2', "equipment_moto");
                if(!empty($term) && !empty($term->term_id)) {
                    $terms = [(int)$term->term_id];
                    wp_set_post_terms($product_id, $terms, "equipment_moto", true);
                }
            }
        }

        update_field("din_power", $original_data['horsepower'], $product_id);
    }

    /**
     * Pour le champ ACF "Kilométrage garanti"
     */
    public static function setKmGaranti($original_data, $product_id) {
        if(!empty($original_data['guaranteedMileage']) && filter_var($original_data['guaranteedMileage'], FILTER_VALIDATE_BOOLEAN)) {
            update_field("km_garanti", true, $product_id);
        }
    }

    /**
     * Pour le champ ACF "Première main"
     */
    public static function setPremiereMain($original_data, $product_id) {
        if(!empty($original_data['firstHand']) && filter_var($original_data['firstHand'], FILTER_VALIDATE_BOOLEAN)) {
            update_field("premiere_main", true, $product_id);
        }
    }

    /**
     * Pour les champs "Date de mise en circulation"
     */
    public static function setDateMec($original_data, $product_id) {
        if(!empty($original_data['putIntoService'])) {
            $date = new DateTime($original_data['putIntoService']);
            if($date) {
                update_field("day", $date->format("d"), $product_id);
                update_field("month", $date->format("m"), $product_id);
                update_field("year", $date->format("Y"), $product_id);
            }
        }
    }

    /**
     * Génère l'adress complète du concessionnaire depuis les champs
     */
    public static function setFullSocieteAdresse($original_data, $product_id) {
        // Génération adresse
        $pos_infos = [];
        if(!empty($original_data['physicalPresencePointOfSale'])) {
            $pos_infos = $original_data['physicalPresencePointOfSale'];
        } elseif(!empty($original_data['pointOfSale'])) {
            $pos_infos = $original_data['physicalPresencePointOfSale'];
        }

        if(empty($pos_infos)) {
            return;
        }

        $adresse = null;
        if(!empty($pos_infos['name'])) {
            $adresse = $pos_infos['name'];
        }

        // Génération ville
        $ville = [];
        if(!empty($pos_infos['zipCode'])) {
            $ville[] = $pos_infos['zipCode'];
        }
        if(!empty($pos_infos['city'])) {
            $ville[] = $pos_infos['city'];
        }
        $ville = implode(" ", $ville);

        $full_adresse = [];
        if(!empty($adresse)) {
            $full_adresse[] = $adresse;
        }
        if(!empty($ville)) {
            $full_adresse[] = $ville;
        }

        $full_adresse = implode(", ", $full_adresse);
        if(!empty($full_adresse)) {
            update_field("dealer_address", $full_adresse, $product_id);
        }
    }

    /**
     * Parse la marque et l'enregistre dans la taxonomie correspondante
     */
    public static function setBikeBrand($original_data, $product_id) {
        if(empty($original_data['make'])) {
            return;
        }

        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_categ = sanitize_title($original_data['make']);
        $term = get_term_by('slug', $ref_categ, "moto_brand");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
        } else {
            // Création de la marque si inexistante
            $term = wp_insert_term($original_data['make'], 'moto_brand', ['slug' => $ref_categ]);
            if(!empty($term)) {
                $terms = [(int)$term['term_id']];
            }
        }
        if(!empty($terms)) {
            wp_set_post_terms($product_id, $terms, "moto_brand");
        }
    }

    /**
     * Parse la catégorie et l'enregistre dans la taxonomie correspondante
     * @TODO - Adapter pour OpenFlex
     */
    public static function setBikeCategory($original_data, $product_id) {
        if(empty($original_data['category'])) {
            return;
        }

        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_categ = sanitize_title($original_data['category']);
        $term = get_term_by('slug', $ref_categ, "moto_category");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_category");
        }
    }

    /**
     * Parse le carburant et l'enregistre dans la taxonomie correspondante
     */
    public static function setBikeFuel($original_data, $product_id) {
        if(empty($original_data['genericFuel'])) {
            if(empty($original_data['fuel'])) {
                return;
            } else {
                $original_data['genericFuel'] = $original_data['fuel'];
            }
        }
        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_carburant = sanitize_title($original_data['genericFuel']);
        $term = get_term_by('slug', $ref_carburant, "moto_fuel");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_fuel");
        }
    }

    /**
     * Parse la ville et l'enregistre dans la taxonomie correspondante
     * @TODO - Adapter pour OpenFlex
     */
    public static function setBikeCity($original_data, $product_id) {
        if(empty($original_data['city'])) {
            return;
        }

        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_ville = sanitize_title($original_data['city']);
        if($ref_ville == "boe") {
        	$ref_ville = "agen";
        }

        $term = get_term_by('slug', $ref_ville, "moto_city");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_city");
        }
    }

    public static function setGrimDate($product_id) {
        $now = new \DateTime("now");
        update_field("grim_date", $now->format('Y-m-d H:i:s'), $product_id);
    }

    /**
     * Fonction utilisée pour faire un appel sur l'API GET details
     * afin de récupérer des infos supplémentaires sur le véhicule
     * à ajouter après l'import : photos, équipements
     */
    public function importDetails($original_data, $product_id, $type) {
        if(empty($original_data['id'])) {
            return;
        }

        $details = $this->importer->getVehicleDetails($original_data['id']);
        var_dump($details);

        if(empty($details)) {
            return;
        }

        // Import des photos
        // @TODO - Ajouter la photo principale ? Je suis pas sûr de l'utilité
        // de referentialPicture, peut-être que c'est juste une photo de
        // référence pour le modèle
        if(!empty($details['pictures'])) {
            $attachments = [];

            foreach($details['pictures'] as $picture) {
                if(empty($picture['pictureUrl'])) {
                    continue;
                }

                $attachment_id = static::wp_insert_attachment_from_url($picture['pictureUrl'], $product_id);
                if(!empty($attachment_id)) {
                    $attachments[] = $attachment_id;
                }
            }

            // Si au moins une image a pu être ajoutée dans la médiathèque, on l'ajoute à la galerie ACF
            if(!empty($attachments)) {
                update_field("images", $attachments, $product_id);
            }
        }

        if($type == 'bike') {
            $this->importBikeEquipments($details, $product_id);
        } else {
            $this->importCarEquipments($details, $product_id);
        }
    }

    /**
     * Importe les équipements motos
     */
    public function importBikeEquipments($details, $product_id) {
        // @TODO - Équipements à identifier via Regex
        $terms_to_check = [
            "/bluetooth/i" => "bluetooth",
            "/shifter.*pro/i" => "shifter-pro",
            "/(keyless|sans.*cle|sans.*clef)/i" => "demarrage-sans-cle",
            "/poignee.*chauffante/i" => "poignees-chauffantes",
            "/sellle.*chauffante/i" => "selle-chauffante",
            "/feux.*route/i" => "feux-route",
            "/kit.*surbaissement/i" => "kit-surbaissement",
            "/appel.*urgence/i" => "appel-urgence",
            "/teleservice/i" => "teleservice"
        ];
        // @TODO - Équipements à identifier via code
        $terms_codes = [
            "code_equipement" => "ref_icone"
        ];

        $this->importEquipments($details, $product_id, $terms_to_check, $terms_codes, "equipment_moto");
    }

    /**
     * Importe les équipements voitures
     */
    public function importCarEquipments($details, $product_id) {
        // @TODO - Équipements à identifier via Regex
        $terms_to_check = [];
        // @TODO - Équipements à identifier via code
        $terms_codes = [
            "code_equipement" => "ref_icone"
        ];

        $this->importEquipments($details, $product_id, $terms_to_check, $terms_codes, "equipment");
    }

    /**
     * Importe les équipements dans les 3 champs ACF pour les équipements
     * de série, les options et les packs, et vérifie pour chaque champ si
     * son libellé est dans les regex des champs avec icône.
     * Si c'est le cas, l'équipement est ajouté à la taxonomie identifiée par
     * le paramètre $tax_ref
     */
    public function importEquipments($details, $product_id, $terms_to_check, $terms_codes, $tax_ref) {
        if(empty($details['equipments'])) {
            return;
        }

        $equip_serials = [];
        $equip_packs = [];
        $equip_options = [];
        $terms = [];
        foreach($details['equipments'] as $equip) {
            $equip_title = $equip['wording'];

            // Pour les packs, on ajoute la liste des équipements entre
            // parenthèses dans le titre
            if(!empty($equip['packElements'])) {
                $packElements = [];
                foreach($equip['packElements'] as $packElem) {
                    if(!empty($packElem['wording'])) {
                        $packElements[] = $packElem['wording'];
                    }
                }

                if(!empty($packElements)) {
                    $equip_title .= " (".implode(", ", $packElements).")";
                }

                $equip_packs[] = [
                    'item' => $equip_title
                ];
            } else {
                if(!empty($equip['serial']) && filter_var($equip['serial'], FILTER_VALIDATE_BOOLEAN)) {
                    $equip_serials[] = [
                        'item' => $equip_title
                    ];
                } else {
                    $equip_options[] = [
                        'item' => $equip_title
                    ];
                }
            }

            $equip_slug = sanitize_title($equip['wording']);

            // On regarde si l'équipement fait partie de ceux affichés en icône
            // Première vérification via le code de l'équipement
            $term_ref = null;
            if(!empty($equip['code'])) {
                if(!empty($terms_codes[$equip['code']])) {
                    $term_ref = $terms_codes[$equip['code']];
                }
            }

            // Deuxième vérification via le libellé
            if(empty($term_ref)) {
                foreach($terms_to_check as $regex => $ref) {
                    if(preg_match($regex, $equip_slug)) {
                        $term_ref = $ref;
                    }
                }
            }

            if(!empty($term_ref)) {
                $term_id = $this->getTermId($term_ref, $tax_ref);
                if(!empty($term_id)) {
                    $terms[] = (int)$term_id;
                }
            }
        }

        update_field("equipments_serial", $equip_serials, $product_id);
        update_field("equipments_options", $equip_options, $product_id);
        update_field("equipments_packs", $equip_packs, $product_id);

        if(!empty($terms)) {
            wp_set_post_terms($product_id, $terms, $tax_ref, true);
        }
    }

    /**
     * Récupère l'id du terme de taxonomie identifié par le slug term_ref,
     * pour la taxonomie tax_ref, et le met en cache afin d'éviter des doublons
     * d'appels BDD pour le même terme
     */
    public function getTermId($term_ref, $tax_ref) {
        // Vérification dans le cache
        if(!empty($this->_taxonomy_terms)) {
            if(!empty($this->_taxonomy_terms[$tax_ref])) {
                if(!empty($this->_taxonomy_terms[$tax_ref][$term_ref])) {
                    return $this->_taxonomy_terms[$tax_ref][$term_ref];
                }
            }
        } else {
            $this->_taxonomy_terms[$tax_ref] = [];
        }

        // Récupération depuis la bdd
        $term = get_term_by('slug', $term_ref, $tax_ref);
        if(!empty($term) && !empty($term->term_id)) {
            // Mise en cache
            if(empty($this->_taxonomy_terms[$tax_ref])) {
                $this->_taxonomy_terms[$tax_ref] = [];
            }
            $this->_taxonomy_terms[$tax_ref][$term_ref] = $term->term_id;

            return $term->term_id;
        }
        return null;
    }

    /**
     * Parse les photos (avec URL)
     * @deprecated - Moved to importDetails
     */
    public static function parsePhotosUrl($product_infos, $product_id, $value) {
        // Désactivé car bouffe trop de perfs et inutile en local
        return;

        // On regarde si le produit a déjà des photos. Si c'est le cas on skip (sinon ça fait des doublons)
        if(!empty(have_rows("images", $product_id))) {
            return $product_infos;
        }

        // On crée les attachments pour chacune des photos
        $attachments = [];
        $photos_url = explode("|", $value);

        foreach($photos_url as $photo_url) {
            $photo_url = explode("?md5=", $photo_url);
            if(empty($photo_url) || empty($photo_url[0])) {
                continue;
            }

            $photo_url = reset($photo_url);
            $attachment_id = static::wp_insert_attachment_from_url($photo_url, $product_id);
            if(!empty($attachment_id)) {
                $attachments[] = $attachment_id;
            }
        }

        // Si au moins une image a pu être ajoutée dans la médiathèque, on l'ajoute à la galerie ACF
        if(!empty($attachments)) {
            update_field("images", $attachments, $product_id);
        }
        return $product_infos;
    }

    /**
     * Provient d'ici : https://gist.github.com/m1r0/f22d5237ee93bcccb0d9
     * 
     * Insert an attachment from a URL address.
     *
     * @param  string   $url            The URL address.
     * @param  int|null $parent_post_id The parent post ID (Optional).
     * @return int|false                The attachment ID on success. False on failure.
     */
    public static function wp_insert_attachment_from_url( $url, $parent_post_id = null ) {

        if ( ! class_exists( 'WP_Http' ) ) {
            require_once \ABSPATH . \WPINC . '/class-http.php';
        }

        $http     = new \WP_Http();
        $response = $http->request( $url );
        if ( 200 !== $response['response']['code'] ) {
            return false;
        }

        $upload = \wp_upload_bits( basename( $url ), null, $response['body'] );
        if ( ! empty( $upload['error'] ) ) {
            return false;
        }

        $file_path        = $upload['file'];
        $file_name        = basename( $file_path );
        $file_type        = \wp_check_filetype( $file_name, null );
        $attachment_title = \sanitize_file_name( pathinfo( $file_name, \PATHINFO_FILENAME ) );
        $wp_upload_dir    = \wp_upload_dir();

        $post_info = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => $attachment_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        // Create the attachment.
        $attach_id = \wp_insert_attachment( $post_info, $file_path, $parent_post_id );

        // Include image.php.
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Generate the attachment metadata.
        $attach_data = \wp_generate_attachment_metadata( $attach_id, $file_path );

        // Assign metadata to attachment.
        \wp_update_attachment_metadata( $attach_id, $attach_data );

        return $attach_id;

    }
}

?>