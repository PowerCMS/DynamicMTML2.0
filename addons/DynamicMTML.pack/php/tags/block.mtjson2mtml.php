<?php
function smarty_block_mtjson2mtml( $args, $content, &$ctx, &$repeat ) {
    // /mt-data-api.json?api=%2fsites%2f2%2fentries%3fsortOrder%3dascend
    $localvars = array( 'json2mtmlitems', 'json2mtmltotalsize',
                        'json2mtmlcounter' );
    $tag = $ctx->this_tag();
    $app = $ctx->stash( 'bootstrapper' );
    if (! isset( $content ) ) {
        $ctx->localize( $localvars );
        if ( isset( $args[ 'item' ] ) ) {
            $item = $args[ 'item' ];
        } else {
            $item = 'items';
        }
        if ( isset( $args[ 'request' ] ) ) {
            $request = $args[ 'request' ];
        } elseif ( isset( $args[ 'endpoint' ] ) ) {
            $request = $args[ 'endpoint' ];
        }
        if ( isset( $args[ 'version' ] ) ) {
            $api_version = $args[ 'version' ];
        } else {
            if ( $tag === 'mtdataapiproxy' ) {
                $api_version = $app->config( 'DataAPIVersion' );
                if ( strpos( $request, '/' . $api_version ) === 0 ) {
                    $api_version = '';
                }
            }
        }
        if ( isset( $args[ 'instance' ] ) ) {
            $instance_url = $args[ 'instance' ];
        } else {
            $instance_url = $app->config( 'DataAPIURL' );
            if (! $instance_url ) {
                $cgi_path = $app->config( 'CgiPath' );
                $script = $app->config( 'DataAPIScript' );
                $instance_url = $cgi_path . $script;
            }
        }
        $api = "${instance_url}/${api_version}${request}";
        $method = $_SERVER[ 'REQUEST_METHOD' ];
        if ( isset( $args[ 'cache_ttl' ] ) ) {
            $ttl = $args[ 'cache_ttl' ];
        } elseif ( isset( $args[ 'ttl' ] ) ) {
            $ttl = $args[ 'ttl' ];
        }
        if ( $method === 'GET' ) {
            if ( $ttl == 'auto' ) {
                $ttl = $app->config( 'DynamicCacheTTL' );
            }
            if ( isset( $args[ 'updated_at' ] ) ) {
                $updated_at = $args[ 'updated_at' ];
            }
            $cache_driver = $app->cache_driver;
            $cache_key = 'data_api_' . md5( $api );
            if ( $cache_driver && $ttl ) {
                $buf = $cache_driver->get( $cache_key );
                if ( $buf === FALSE ) unset( $buf );
            } elseif ( $ttl ) {
                require_once( 'class.mt_session.php' );
                $prefix = $app->config( 'DynamicCachePrefix' ) ?
                          $app->config( 'DynamicCachePrefix' ) : 'dynamicmtmlcache';
                $session = new Session;
                $cache_key = $app->escape( $cache_key );
                $session_id = $prefix . '_' . $cache_key;
                $where = "session_id = '${session_id}'";// AND session_duration > '{$duration}'";
                $extra = array( 'limit' => 1 );
                $cache = $session->Find( $where, FALSE, FALSE, $extra );
                $duration = time();
                if ( isset( $cache ) ) {
                    $cache = $cache[ 0 ];
                    if ( $cache->duration > $duration ) {
                        $buf = $cache->data;
                    } else {
                        $cache->Delete();
                    }
                }
            }
        }
        if (! isset( $buf ) ) {
            $get_headers = getallheaders();
            $client_headers = array();
            foreach ( $get_headers as $key => $value ) {
                $header = $key . ': ' . $value;
                array_push( $client_headers, $header );
            }
            $curl = curl_init();
            curl_setopt( $curl, CURLOPT_HTTPHEADER, $client_headers );
            curl_setopt( $curl, CURLOPT_URL, $api );
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
            if ( $method === 'POST' ) {
                curl_setopt( $curl, CURLOPT_POST, 1 );
                $post = $app->query_string;
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
            }
            curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, FALSE );
            $buf = curl_exec( $curl );
            if ( curl_errno( $curl ) ) {
                $repeat = FALSE;
                return '';
            }
            curl_close( $curl );
            if ( $method === 'GET' ) {
                if ( $cache_driver && $ttl ) {
                    $cache_driver->set( $cache_key, $buf, $ttl, $updated_at );
                } elseif ( $ttl ) {
                    require_once( 'class.mt_session.php' );
                    $session = new Session;
                    $duration = time();
                    $session_id = $prefix . '_' . $cache_key;
                    $session->session_id = $session_id;
                    if ( $updated_at ) {
                        $name = $prefix .'_upldate_key_' . $updated_at;
                        $session->session_name = $name;
                    }
                    $session->session_kind = 'CO';
                    $session->session_start = $duration;
                    $session->session_duration = $duration + $ttl;
                    $session->data = $buf;
                    $session->Save();
                }
            }
        }
        if ( isset( $args[ 'raw_data' ] ) ) {
            $type = 'application/json';
            $length = strlen( $buf );
            if (! $mtime ) $mtime = time();
            $app->send_http_header( $type, $mtime, $length );
            echo $buf;
            exit();
            $repeat = FALSE;
        }
        $json = json_decode( $buf, TRUE );
        if ( isset( $args[ 'debug' ] ) ) {
            echo '<pre>' . $api . ':';
            var_dump( $json );
            echo '</pre>';
        }
        if ( $error = $json[ 'error' ] ) {
            $ctx->__stash[ 'vars' ][ 'code' ] = $error[ 'code' ];
            $ctx->__stash[ 'vars' ][ 'message' ] = $error[ 'message' ];
        } else {
            $totalResults = $json[ 'totalResults' ];
            if ( $item ) {
                $json = $json[ $item ];
            }
            $total = count( $json );
            $ctx->stash( 'json2mtmlitems', $json );
            $ctx->stash( 'json2mtmltotalsize', $total );
            $ctx->stash( 'json2mtmltotalresults', $totalResults );
            $ctx->stash( 'json2mtmlcounter', 0 );
            $counter = 0;
        }
    } else {
        if ( isset( $args[ 'raw_data' ] ) ) {
            $repeat = FALSE;
            return '';
        }
        $totalResults = $ctx->stash( 'json2mtmltotalresults' );
        $json = $ctx->stash( 'json2mtmlitems' );
        $counter = $ctx->stash( 'json2mtmlcounter' );
        $total = $ctx->stash( 'json2mtmltotalsize' );
    }
    if ( $json ) {
        if ( $total ) {
            if ( $counter < $total ) {
                $obj = $json[ $counter ];
                if (! $counter ) {
                    $ctx->__stash[ 'vars' ][ '__first__' ] = 1;
                } else {
                    $ctx->__stash[ 'vars' ][ '__first__' ] = 0;
                }
                foreach ( $obj as $key => $value ) {
                    $ctx->__stash[ 'vars' ][ $key ] = $value;
                    $ctx->__stash[ 'vars' ][ strtolower( $key ) ] = $value;
                }
                $counter++;
                $ctx->__stash[ 'vars' ][ '__counter__' ]  = $counter;
                $ctx->__stash[ 'vars' ][ '__odd__' ]      = ( $counter % 2 ) == 1;
                $ctx->__stash[ 'vars' ][ '__even__' ]     = ( $counter % 2 ) == 0;
                $ctx->__stash[ 'vars' ][ 'totalresults' ] = $totalResults;
                $ctx->__stash[ 'vars' ][ 'totalResults' ] = $totalResults;
                if ( $total == $counter ) {
                    $ctx->__stash[ 'vars' ][ '__last__' ] = 1;
                }
                $repeat = TRUE;
            } else {
                $ctx->restore( $localvars );
                $repeat = FALSE;
            }
            $ctx->stash( 'json2mtmlcounter', $counter );
        }
    }
    return $content;
}
?>