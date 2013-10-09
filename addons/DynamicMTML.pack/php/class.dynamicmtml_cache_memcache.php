<?php
class DynamicCacheMemcache extends DynamicCache {

    public $ttl;
    public $prefix;
    public $memcache;
    public $compressed;

    function __construct( $app = NULL ) {
        $this->app = $app;
        $this->ttl = $app->cache_driver->ttl;
        $this->prefix = $app->config( 'DynamicCachePrefix' );
        $memcache = new Memcache;
        $server = $app->config( 'DynamicMemcachedServer' );
        $port = $app->config( 'DynamicMemcachedPort' );
        $compressed = $app->config( 'DynamicMemcachedCompressed' );
        if ( $compressed ) {
            $compressed = TRUE;
        } else {
            $compressed = FALSE;
        }
        $this->compressed = $compressed;
        $memcache->connect( $server, $port );
        $this->memcache = $memcache;
        // $this->clear( NULL, 'DEBUG' );
        // $version = $memcache->getVersion();
    }

    function get ( $key ) {
        return $this->memcache->get( $key );
    }

    function set ( $key, $value, $ttl = NULL ) {
        if (! $ttl ) {
            $ttl = $this->ttl;
        }
        return $this->memcache->set( $key, $value, $this->compressed, $ttl );
    }

    function remove ( $key ) {
        return $this->memcache->delete( $key );
    }

    function purge () {
        return $this->clear();
    }

    function clear ( $flush = NULL, $debug = NULL ) {
        $memcache = $this->memcache;
        if ( $flush !== NULL ) {
            return $memcache->flush();
        }
        $prefix = $this->prefix;
        $slabs = $memcache->getExtendedStats( 'slabs' );
        $all_keys = array();
        $server_array = array();
        foreach ( $slabs as $key => $value ) {
            array_push( $server_array, $key );
        }
        foreach ( $server_array as $key ) {
            $server_slabs = $slabs[ $key ];
            //var_dump( $server_slabs );
            foreach ( $server_slabs as $key => $value ) {
                if ( $key !== 'active_slabs' && $key !== 'total_malloced' ) {
                    $keys = array_flip( array_keys( $memcache->getStats('cachedump', $key, 10000 ) ) );
                    array_push( $all_keys, $keys );
                }
            }
        }
        if ( $debug ) {
            var_dump( $all_keys );
            exit();
        }
        $do = FALSE;
        foreach ( $all_keys as $key => $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $chache => $value ) {
                    $pos = strpos( $chache, $prefix );
                    if ( $pos !== FALSE ) {
                        if ( $res = $this->remove( $chache ) ) {
                            $do = TRUE;
                        } else {
                            return FALSE;
                        }
                    }
                }
            }
        }
        return $do;
    }

}

?>