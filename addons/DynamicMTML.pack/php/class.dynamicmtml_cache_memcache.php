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
        $server = $app->config( 'DynamicMemcachedServers' );
        $port;
        if (! $server ) {
            $server = $app->config( 'MemcachedServers' );
        }
        $servers = array();
        if ( $server ) {
            if ( is_array( $server ) ) {
                $servers = $server;
                $server = array_pop( $servers );
            }
            $serverses = explode( ':', $server );
            $server = $serverses[ 0 ];
            $port = $serverses[ 1 ];
        } else {
            $server = 'localhost';
        }
        if (! $port ) $port = '11211';
        $compressed = $app->config( 'DynamicMemcachedCompressed' );
        if ( $compressed ) {
            $compressed = TRUE;
        } else {
            $compressed = FALSE;
        }
        $this->compressed = $compressed;
        $memcache = new Memcache;
        $memcache->connect( $server, $port );
        if ( $servers ) {
            foreach ( $servers as $server ) {
                $serverses = explode( ':', $server );
                $server = $serverses[ 0 ];
                $port = $serverses[ 1 ];
                if (! $port ) $port = '11211';
                $memcache->addServer( $server, $port );
            }
        }
        $this->memcache = $memcache;
        // $this->clear( NULL, 'DEBUG' );
        // $version = $memcache->getVersion();
    }

    function get ( $key, $ttl = NULL ) {
        return $this->memcache->get( $key );
    }

    function set ( $key, $value, $ttl = NULL, $updated_at = NULL ) {
        if (! $ttl ) {
            $ttl = $this->ttl;
        }
        if ( $updated_at ) {
            $update_key = $this->prefix . '_upldate_key_' . $updated_at;
            $update_keys = $this->get( $update_key );
            if ( $update_keys ) {
                $update_keys = explode( ',', $update_keys );
            }
            if ( $update_keys && is_array( $update_keys ) ) {
                if (! in_array( $key, $update_keys ) ) {
                    array_push( $update_keys, $key );
                }
                $update_keys = join( ',', $update_keys );
            } else {
                $update_keys = $key;
            }
            $this->set( $update_key, $update_keys );
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
        $do = NULL;
        $error;
        foreach ( $all_keys as $key => $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $chache => $value ) {
                    $pos = strpos( $chache, $prefix );
                    if ( $pos === 0 ) {
                        if ( $res = $this->remove( $chache ) ) {
                            $do = TRUE;
                        } else {
                            $error = 1;
                        }
                    }
                }
            }
        }
        if ( $error ) return FALSE;
        return $do;
    }

}

?>