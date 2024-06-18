<?php

/*
 * Define global constants
 */
define('OPENFLEX_CSV_PATH', "../app/plugins/products-importer/test_preprod_1_line.csv");
define('OPENFLEX_CSV_DELIMITER', ";");
define('OPENFLEX_ATTR_SEPARATOR', ";");
define('OPENFLEX_MAX_LINES_LOOP', 2);
define('OPENFLEX_AUTOLOOP', false);
define('OPENFLEX_ACTION', 'UPDATE_OR_CREATE');
define('OPENFLEX_DELETE_OTHERS', true);
define('OPENFLEX_VALUE_IF_EMPTY', [
    'stock_quantity' => 0
]);
define('OPENFLEX_POST_TYPE', 'motos');

// FTP settings (if the file has to be downloaded from a FTP server)
define('OPENFLEX_USE_FTP', false);
define('OPENFLEX_FTP_SERVER', 'ftp.publicationvo.com');
define('OPENFLEX_FTP_USER', 'grimoccasion');
define('OPENFLEX_FTP_PASSWORD', 'o1WyU3PXtZT8');
define('OPENFLEX_FTP_PATH', '/datas/bm81c3.csv');

// NEW CONFIGURATION
define('OPENFLEX_FTP_CONFIGS', [
    // [
    //     'server' => 'ftp.publicationvo.com',
    //     'user' => 'grimoccasion',
    //     'password' => 'o1WyU3PXtZT8',
    //     'path' => '/datas/bm81c3.csv',
    //     'city' => 'albi'
    // ],
    // [
    //     'server' => 'ftp.publicationvo.com',
    //     'user' => 'grimoccasion',
    //     'password' => 'o1WyU3PXtZT8',
    //     'path' => '/datas/mu31m4.csv',
    //     'city' => 'toulouse'
    // ],
    // [
    // 'server' => 'ftp.publicationvo.com',
    //     'user' => 'grimoccasion',
    //     'password' => 'o1WyU3PXtZT8',
    //     'path' => '/datas/bm47c2.csv',
    //     'city' => 'agen'
    // ]
]);

// File structure
define('OPENFLEX_CSV_STRUCTURE', [
    'VehiculeModele' => 'function:getPostTitle',
    'Version' => 'name',
    'ignore_3' => 'ignore_3',
    'ignore_4' => 'ignore_4',
]);
define('OPENFLEX_CSV_STRUCTURE_AFTER', [
    //'Poids' => 'acf:poids', >> Existe plus
    'VehiculeCylindree' => 'acf:volume',
    'VehiculeKilometrage' => 'acf:km',
    'VehiculeCouleurExterieure' => 'acf:color',
    'VehiculeBoite' => 'acf:transmission',
    'VehiculePrixVenteTTC' => 'acf:price',
    'VehiculePuissanceFiscale' => 'acf:fiscal_power',
    'VehiculePuissanceReelle' => 'function:parsePuissance',
    'AnnonceurSocieteNom' => 'acf:dealer_name',
    'Longueur' => 'acf:longueur',
    'Largeur' => 'acf:largeur',
    'Empattement' => 'acf:empattement',
    'Hauteur' => 'acf:hauteur',
    'VehiculeCo2' => 'acf:co2',
    'VehiculeGarantie' => 'acf:guarantee',
    'VehiculeReference' => 'acf:reference', // @ICI
    //'Taxonomies' => 'taxonomy:equipment_moto',
    'AnnonceurSocieteAdresse' => 'function:getFullSocieteAdresse',
    'VehiculeMarque' => 'function:parseMarque',
    'VehiculeCategorie' => 'function:parseCategorie',
    'VehiculeEnergie' => 'function:parseCarburant',
    'VehiculeEquipementsSerie' => 'function:parseEquipment',
    'VehiculeModele' => 'function:getFinition',
    'VehiculeKmGaranti' => 'function:parseKmGaranti',
    'VehiculePremiereMain' => 'function:parsePremiereMain',
    'VehiculeDate1Mec' => 'function:parseDateMec',
    'AnnonceurSocieteVille' => 'function:parseVille',
    'VehiculePhotosUrl' => 'function:parsePhotosUrl'
]);
define('OPENFLEX_REFERENCE_ATTRIBUTE', 'acf:reference');
//define('OPENFLEX_REFERENCE_COLUMN', 'VehiculeIdentifiant');
define('OPENFLEX_REFERENCE_COLUMN', ['referentialCarId']);
define('OPENFLEX_PARSE_HEADER', true);
// Default values for initial import
define('OPENFLEX_DEFAULT_VALUES', [
    'post_status' => 'publish'
]);
// Default values for after import
define('OPENFLEX_DEFAULT_VALUES_AFTER', [
    'function:setGrimDate' => 'now'
]);

// Media import
define('OPENFLEX_MEDIA_ABSOLUTE', false);
define('OPENFLEX_MEDIA_FORMAT', 'magento');
define('OPENFLEX_BASE_URL', 'http://212.44.247.61');
define('OPENFLEX_MEDIA_TRANSFORM_URL', 'lowercase');

define('OPENFLEX_MEDIA_FOLDERS', [
    'product_image' => '',
    'product_thumbnail' => ''
]);

// Complex fields
define('OPENFLEX_CATEGORIES_TREE_SEPARATOR', ',');
define('OPENFLEX_CATEGORIES_TREE_BREADCRUMB', '/');
define('OPENFLEX_CATEGORIES_TREE_ATTRIBUTE', 'name');
define('OPENFLEX_CATEGORIES_CREATE_IF_NULL', true);
define('OPENFLEX_WARN_UNMAPPED', false);

// Fonctions d'import custom
class OpenFlex_CustomFunctions {
    public static function beforeImport($import = null) {}
    public static function afterImport($import = null) {
        $ftp_config = OPENFLEX_FTP_CONFIGS[$import->use_config] ?? [];
        $city = $ftp_config["city"] ?? false;
        if(!$city) {
            return;
        }

        var_dump("== afterImport ==");
        var_dump($city);
        // Archivage des motos dont la référence n'est pas présente dans le fichier
        $whole_importer = new Products_Importer\CsvImporter(\ABSPATH.OPENFLEX_CSV_PATH, true);
        $data = $whole_importer->get();

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
    public static function getPostTitle($product_infos, $product_id, $value, $args, $original_data) {
        $post_title = [];
        if(!empty($original_data['VehiculeMarque'])) {
            $post_title[] = $original_data['VehiculeMarque'];
        }
        if(!empty($original_data['VehiculeModele'])) {
            $post_title[] = $original_data['VehiculeModele'];
        }
        if(!empty($original_data['VehiculeVersion'])) {
            $post_title[] = $original_data['VehiculeVersion'];
        }
        if(is_a($product_infos, 'WC_Product')) {
            $product_infos->set_name(implode(" ", $post_title));
        } else {
            $product_infos['post_title'] = implode(" ", $post_title);
        }
        return $product_infos;
    }

    /**
     * Génère le nom de la finition en combinant la marque, le modèle et l'année de sortie
     */
    public static function getFinition($product_infos, $product_id, $value, $args, $original_data) {
        $nom_moto = [];
        if(!empty($original_data['VehiculeModele'])) {
            $nom_moto[] = $original_data['VehiculeModele'];
        }
        if(!empty($original_data['VehiculeVersion'])) {
            $nom_moto[] = $original_data['VehiculeVersion'];
        }

        $finition = implode(" ", $nom_moto);

        // Ajout des infos complémentaires
        if(!empty($original_data['VehiculeCouleurExterieure'])) {
            $finition .= ", {$original_data['VehiculeCouleurExterieure']}";
        }
        if(!empty($original_data['VehiculePuissanceReelle'])) {
            $finition .= ", {$original_data['VehiculePuissanceReelle']} cv";
        }

        update_field("finishes", $finition, $product_id);
        return $product_infos;
    }

    /**
     * Parse la puissance pour voir si la moto est éligible au permis A2
     */
    public static function parsePuissance($product_infos, $product_id, $value, $args, $original_data) {
        // Si puissance < 47.5 chevaux, on ajoute l'équipement "Permis A2"
        if(!empty($original_data['VehiculePuissanceReelle'])) {
            $puissance = (float)$original_data['VehiculePuissanceReelle'];
            if($puissance <= 47.5) {
                $term = get_term_by('slug', 'permis-a2', "equipment_moto");
                if(!empty($term) && !empty($term->term_id)) {
                    $terms = [(int)$term->term_id];
                    wp_set_post_terms($product_id, $terms, "equipment_moto", true);
                }
            }
        }

        update_field("din_power", $original_data['VehiculePuissanceReelle'], $product_id);
        return $product_infos;
    }

    /**
     * Pour le champ ACF "Kilométrage garanti"
     */
    public static function parseKmGaranti($product_infos, $product_id, $value, $args, $original_data) {
        if(!empty($original_data['VehiculeKmGaranti']) && $original_data['VehiculeKmGaranti'] == "Garanti") {
            update_field("km_garanti", true, $product_id);
        }
        return $product_infos;
    }

    /**
     * Pour le champ ACF "Première main"
     */
    public static function parsePremiereMain($product_infos, $product_id, $value, $args, $original_data) {
        if(!empty($original_data['VehiculePremiereMain']) && $original_data['VehiculePremiereMain'] == "VRAI") {
            update_field("premiere_main", true, $product_id);
        }
        return $product_infos;
    }

    /**
     * Pour les champs "Date de mise en circulation"
     */
    public static function parseDateMec($product_infos, $product_id, $value, $args, $original_data) {
        if(!empty($value)) {
            $date = DateTime::createFromFormat("d-m-Y", $value);
            if($date) {
                update_field("day", $date->format("d"), $product_id);
                update_field("month", $date->format("m"), $product_id);
                update_field("year", $date->format("Y"), $product_id);
            }
        }
        return $product_infos;
    }

    /**
     * Génère l'adress complète du concessionnaire depuis les champs
     */
    public static function getFullSocieteAdresse($product_infos, $product_id, $value, $args, $original_data) {
        // Génération adresse
        $adresse = [];
        if(!empty($original_data['AnnonceurSocieteAdresse'])) {
            $adresse[] = $original_data['AnnonceurSocieteAdresse'];
        }
        if(!empty($original_data['AnnonceurSocieteAdresseSuite'])) {
            $adresse[] = $original_data['AnnonceurSocieteAdresseSuite'];
        }
        $adresse = implode(", ", $adresse);

        // Génération ville
        $ville = [];
        if(!empty($original_data['AnnonceurSocieteCodePostal'])) {
            $ville[] = $original_data['AnnonceurSocieteCodePostal'];
        }
        if(!empty($original_data['AnnonceurSocieteVille'])) {
            $ville[] = $original_data['AnnonceurSocieteVille'];
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
        update_field("dealer_address", $full_adresse, $product_id);
        return $product_infos;
    }

    /**
     * Parse la marque et l'enregistre dans la taxonomie correspondante
     */
    public static function parseMarque($product_infos, $product_id, $value) {
        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_categ = sanitize_title($value);
        $term = get_term_by('slug', $ref_categ, "moto_brand");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
        } else {
            // Création de la marque si inexistante
            $term = wp_insert_term($value, 'moto_brand', ['slug' => $ref_categ]);
            if(!empty($term)) {
                $terms = [(int)$term['term_id']];
            }
        }
        if(!empty($terms)) {
            wp_set_post_terms($product_id, $terms, "moto_brand");
        }
        return $product_infos;
    }

    /**
     * Parse la catégorie et l'enregistre dans la taxonomie correspondante
     */
    public static function parseCategorie($product_infos, $product_id, $value) {
        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_categ = sanitize_title($value);
        $term = get_term_by('slug', $ref_categ, "moto_category");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_category");
        }
        return $product_infos;
    }

    /**
     * Parse le carburant et l'enregistre dans la taxonomie correspondante
     */
    public static function parseCarburant($product_infos, $product_id, $value) {
        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_carburant = sanitize_title($value);
        $term = get_term_by('slug', $ref_carburant, "moto_fuel");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_fuel");
        }
        return $product_infos;
    }

    /**
     * Parse la ville et l'enregistre dans la taxonomie correspondante
     */
    public static function parseVille($product_infos, $product_id, $value) {
        // On slugifie la catégorie pour retrouver la bonne taxonomie
        $ref_ville = sanitize_title($value);
        if($ref_ville == "boe") {
        	$ref_ville = "agen";
        }

        $term = get_term_by('slug', $ref_ville, "moto_city");
        if(!empty($term) && !empty($term->term_id)) {
            $terms = [(int)$term->term_id];
            wp_set_post_terms($product_id, $terms, "moto_city");
        }
        return $product_infos;
    }

    public static function setGrimDate($product_infos, $product_id, $value) {
        $now = new \DateTime("now");
        update_field("grim_date", $now->format('Y-m-d H:i:s'), $product_id);
        return $product_infos;
    }

    /**
     * Parse les photos (avec URL)
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