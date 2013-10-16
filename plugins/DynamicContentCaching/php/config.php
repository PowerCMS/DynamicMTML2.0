<?php
class DynamicContentCaching extends MTPlugin {
    var $app;
    var $registry = array(
        'name' => 'DynamicContentCaching',
        'id'   => 'DynamicContentCaching',
        'key'  => 'dynamiccontentcaching',
        'author_name' => 'Alfasado Inc.',
        'author_link' => 'http://alfasado.net/',
        'version' => '0.1',
        'config_settings' => array(
            'DynamicContentCacheConditional' => array( 'default' => 1 ),
            'DynamicContentCacheLifeTime' => array( 'default' => 43200 ),
            'DynamicContentCacheExcludes' => array( 'default' => '' ),
        ),
        'callbacks' => array(
            'configure_from_db' => 'configure_from_db',
            'post_return' => 'post_return',
        ),
    );

    function configure_from_db ( $mt, $ctx, $args, $cfg ) {
        if ( isset( $cfg[ 'dynamiccachedriver' ] ) ){
            $app = $this->app;
            if ( $app->request_method !== 'GET' ) {
                return TRUE;
            }
            $url = $args[ 'url' ];
            if ( $excludes = $cfg[ 'dynamiccontentcacheexcludes' ] ) {
                $paths = explode( ',', $excludes );
                foreach ( $paths as $path ) {
                    $path = preg_quote( $path, '/' );
                    if ( preg_match( "/$path/", $url ) ) {
                        return TRUE;
                    }
                }
            }
            $cachedriver = strtolower( $cfg[ 'dynamiccachedriver' ] );
            if (! $cachedriver ) return TRUE;
            $file = $args[ 'file' ];
            $filemtime = $app->stash( 'filemtime' );
            if ( $filemtime && $app->config( 'DynamicContentCacheConditional' ) ) {
                $app->do_conditional( $filemtime );
            }
            $driver = NULL;
            if (! $driver = $app->cache_driver ) {
                require_once( 'class.dynamicmtml_cache.php' );
                $driver = new DynamicCache( $this->app, $cachedriver );
                $app->cache_driver = $driver;
            }
            if (! $driver ) return TRUE;
            $prefix = $app->config( 'DynamicCachePrefix' );
            $url = md5( $url );
            $key = "${prefix}_content_" . $url;
            if ( $cache = $driver->get( $key ) ) {
                $mtime = $cache[ 0 ];
                if ( (! file_exists( $file ) ) || ( $mtime < $filemtime ) ) {
                    $driver->remove( $key );
                } else {
                    $content = $cache[ 1 ];
                    $extension = $args[ 'extension' ];
                    $contenttype = $app->get_mime_type( $extension );
                    $app->send_http_header( $contenttype, $filemtime, strlen( $content ) );
                    echo $content;
                    exit();
                }
            }
            $app->stash( 'dynamic_cached_save_content', 1 );
            $app->stash( 'dynamic_cached_content_key', $key );
        }
        return TRUE;
    }

    function post_return ( $mt, $ctx, $args, $content ) {
        if ( $this->app->stash( 'dynamic_cached_save_content' ) ) {
            $app = $this->app;
            $key = $app->stash( 'dynamic_cached_content_key' );
            $mtime = $app->stash( 'filemtime' );
            $cache = array( $mtime, $content );
            $driver = $app->cache_driver;
            $lifetime = $app->config( 'DynamicContentCacheLifeTime' );
            if (! $lifetime ) $lifetime = NULL;
            $driver->set( $key, $cache, $lifetime );
        }
    }

}
?>