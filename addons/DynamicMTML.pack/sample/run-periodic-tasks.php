<?php
    $mt_dir = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . DIRECTORY_SEPARATOR;
    if (! file_exists ( $mt_dir . 'mt-config.cgi' ) ) {
        echo "mt-config.cgi was not found.\n";
        return;
    }
    require_once ( $mt_dir . 'php' . DIRECTORY_SEPARATOR . 'mt.php' );
    require_once ( $mt_dir . 'addons' . DIRECTORY_SEPARATOR . 'DynamicMTML.pack' .
                   DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'class.dynamicmtml.php' );
    $mt_config = $mt_dir . 'mt-config.cgi';
    $app = new DynamicMTML();
    $app->configure( $mt_config );
    $mt = MT::get_instance( NULL, $mt_config );
    $ctx =& $mt->context();
    $app->set_context( $mt, $ctx );
    $app->run_tasks();
    $app->run_workers();
?>