<?php
class DynamicCache extends DynamicMTML {

    protected $driver;
    public $ttl;
    public $prefix;

    function __construct ( $app = NULL, $driver = NULL ) {
        $this->ttl = $app->config( 'DynamicCacheTTL' );
        $driver = rtrim( $driver, 'd' );
        $mt = $app->mt;
        $app->cache_driver = $this;
        $class = 'DynamicCache' . ucfirst( $driver );
        require_once( "class.dynamicmtml_cache_${driver}.php" );
        $driver = new $class( $app );
        $this->driver = $driver;
        $this->prefix = $app->config( 'DynamicCachePrefix' );
        $app->cache_driver = $this;
    }

    function get ( $key, $ttl = NULL ) {
        if ( $prefix = $this->prefix ) {
            if ( strpos( $key, $prefix ) !== 0 ) {
                $key = "${prefix}_${key}";
            }
        }
        $value = $this->driver->get( $key, $ttl );
        $type = gettype( $value );
        if ( $type === 'array' ) {
            return $value;
        }
        if ( $type !== 'object' ) {
            try {
                $ser = unserialize( $value );
            } catch ( Exception $e ) {
                return;
            }
            if ( $ser === FALSE ) {
                return $value;
            } elseif ( is_array( $ser ) ) {
                return $ser;
            } else {
                $original = $value;
                $value = $ser;
                $type = 'object';
            }
        }
        if ( $type === 'object' ) {
            $class = get_class( $value );
            if ( $class === '__PHP_Incomplete_Class' ) {
                $vars = get_object_vars( $value );
                $class = $vars[ '__PHP_Incomplete_Class_Name' ];
                $_table = $vars[ '_table' ];
                require_once( "class.${_table}.php" );
                if ( gettype( $value ) === 'object' ) {
                    if ( $original ) {
                        // File
                        $value = unserialize( $original );
                    } else {
                        // Memcache
                        $value = $this->driver->get( $key );
                    }
                }
            }
        }
        return $value;
    }

    function set ( $key, $value, $ttl = NULL, $updated_at = NULL ) {
        if ( $prefix = $this->prefix ) {
            if ( strpos( $key, $prefix ) !== 0 ) {
                $key = "${prefix}_${key}";
            }
        }
        $this->driver->set( $key, $value, $ttl, $updated_at );
    }

    function remove ( $key ) {
        if ( $prefix = $this->prefix ) {
            if ( strpos( $key, $prefix ) !== 0 ) {
                $key = "${prefix}_${key}";
            }
        }
        $this->driver->remove( $key );
    }

    function purge () {
        $this->driver->purge();
    }

    function clear () {
        $this->driver->clear();
    }

    function flush_by_key ( $key ) {
        $update_key = $this->prefix . '_upldate_key_' . $key;
        if ( method_exists( $this->driver, 'flush_by_key' ) ) {
            return $this->driver->flush_by_key( $update_key );
        }
        $update_keys = $this->driver->get( $update_key );
        $do = FALSE;
        $error;
        if ( $update_keys ) {
            $keys = explode( ',', $update_keys );
            foreach ( $keys as $key ) {
                if ( $this->driver->remove( $key ) ) {
                    $do = TRUE;
                } else {
                    $error = 1;
                }
            }
            if ( $this->driver->remove( $update_key ) ) {
                $do = TRUE;
            } else {
                $error = 1;
            }
        }
        if ( $error ) return NULL;
        return $do;
    }

}

?>