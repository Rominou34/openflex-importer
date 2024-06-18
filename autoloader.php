<?php

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($filename) {

    $file_path = explode('\\', $filename);

    if (isset($file_path[count($file_path) - 1 ])) {
        $class_file = $file_path[count($file_path) - 1];

        $class_file = "classes/$class_file.class.php";
    }

    $fully_qualified_path = trailingslashit(
        dirname(__FILE__)
    );

    for ($i = 1; $i < count($file_path) - 1; $i ++) {
        $dir = $file_path[$i];
        //$dir = str_ireplace('_', '-', $dir);
        $fully_qualified_path .= trailingslashit($dir);
    }
    $fully_qualified_path .= $class_file;

    if (file_exists($fully_qualified_path)) {
        include_once($fully_qualified_path);
    }
});

?>