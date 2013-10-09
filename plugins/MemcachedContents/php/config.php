<?php
class MemcachedContents extends MTPlugin {
    var $app;
    var $registry = array(
        'name' => 'MemcachedContents',
        'id'   => 'MemcachedContents',
        'key'  => 'memcachedcontents',
        'author_name' => 'Alfasado Inc.',
        'author_link' => 'http://alfasado.net/',
        'version' => '0.1',
        'config_settings' => array(
            'MemcachedContentsConditional' => array( 'default' => 1 ),
            'MemcachedContentsLifeTime' => array( 'default' => 43200 ),
        ),
        'callbacks' => array(
            'configure_from_db' => 'configure_from_db',
            'post_return' => 'post_return',
        ),
    );

    function configure_from_db ( $mt, $ctx, $args, $cfg ) {
        if ( isset( $cfg[ 'dynamiccachedriver' ] ) ){
            $app = $this->app;
            $cachedriver = strtolower( $cfg[ 'dynamiccachedriver' ] );
            $pos = strpos( $cachedriver, 'memcache' );
            if ( $pos === FALSE ) return TRUE;
            $file = $args[ 'file' ];
            $filemtime = $app->stash( 'filemtime' );
            if ( $filemtime && $app->config( 'MemcachedContentsConditional' ) ) {
                $app->do_conditional( $filemtime );
            }
            $driver = NULL;
            if (! $driver = $app->cache_driver ) {
                require_once( 'class.dynamicmtml_cache.php' );
                $driver = new DynamicCache( $this->app, $cachedriver );
                $app->cache_driver = $driver;
            }
            if (! $driver ) return TRUE;
            $url = $args[ 'url' ];
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
            $app->stash( 'memcached_save_content', 1 );
            $app->stash( 'memcached_content_key', $key );
        }
        return TRUE;
    }

    function post_return ( $mt, $ctx, $args, $content ) {
        if ( $this->app->stash( 'memcached_save_content' ) ) {
            $app = $this->app;
            $key = $app->stash( 'memcached_content_key' );
            $mtime = $app->stash( 'filemtime' );
            $cache = array( $mtime, $content );
            $driver = $app->cache_driver;
            $lifetime = $app->config( 'MemcachedContentsLifeTime' );
            if (! $lifetime ) $lifetime = NULL;
            $driver->set( $key, $cache, $lifetime );
        }
    }

}
?>