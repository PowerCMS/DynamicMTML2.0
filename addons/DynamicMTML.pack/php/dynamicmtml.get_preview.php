<?php
    $preview_servers = $app->config( 'PreviewServers' );
    if ( is_array( $preview_servers ) ) {
        $agent = 1;
        if ( isset( $_SERVER[ 'HTTP_X_FORWARDED_BY' ] ) ) {
            if ( $_SERVER[ 'HTTP_X_FORWARDED_BY' ] === 'DynamicMTML' ) {
                $agent = 0;
            }
        }
        if ( $agent ) {
            foreach ( $preview_servers as $server ) {
                $server = rtrim( $server, '/' );
                if ( $base === $server ) continue;
                $preview = preg_replace( "!^$base!", $server, $url );
                $client_headers = $app->client_headers();
                array_push( $client_headers, 'X-Forwarded-By: DynamicMTML' );
                $curl = curl_init();
                curl_setopt( $curl, CURLOPT_HTTPHEADER, $client_headers );
                curl_setopt( $curl, CURLOPT_URL, $preview );
                curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
                curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, FALSE );
                $preview_content = curl_exec( $curl );
                if ( $preview_content ) {
                    $app->send_http_header( $contenttype, time(), strlen( $preview_content ) );
                    echo $preview_content;
                    exit();
                }
            }
        }
    }
?>