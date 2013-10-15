<?php
class DynamicCacheFile extends DynamicCache {

    public $cache_dir;
    public $ttl;
    public $prefix;

    function __construct( $app = NULL ) {
        $this->app = $app;
        $this->cache_dir = $app->cache_dir;
        require_once( 'dynamicmtml.util.php' );
        $this->ttl = $app->cache_driver->ttl;
        $this->prefix = $app->config( 'DynamicCachePrefix' );
    }

    function get ( $key, $ttl = NULL ) {
        if (! $ttl ) $ttl = $this->ttl;
        $cache_dir = $this->cache_dir;
        $file = __cat_file( $cache_dir, $key );
        if (! file_exists( $file ) ) {
            return FALSE;
        }
        if ( $ttl ) {
            if ( ( time() - filemtime( $file ) ) > $ttl ) {
                unlink ( $file );
                return FALSE;
            }
        }
        return file_get_contents( $file );
    }

    function set ( $key, $value, $ttl = NULL, $updated_at = NULL ) {
        $cache_dir = $this->cache_dir;
        $file = __cat_file( $cache_dir, $key );
        if ( is_object( $value ) || is_array( $value ) ) {
            $value = serialize( $value );
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
        file_put_contents( $file, $value );
    }

    function remove ( $key ) {
        $cache_dir = $this->cache_dir;
        $file = __cat_file( $cache_dir, $key );
        if (! file_exists( $file ) ) {
            return FALSE;
        }
        return unlink ( $file );
    }

    function purge () {
        $cache_dir = $this->cache_dir;
        $do = FALSE;
        if ( $ttl = $this->ttl ) {
            $prefix = $this->prefix;
            if ( $dh = opendir ( $cache_dir ) ) {
                while ( $filename = readdir ( $dh ) ) {
                    if ( $filename === '.' || $filename === '..' ) {
                        continue;
                    }
                    if ( strpos( $filename, $prefix ) === 0 ) {
                        $file = __cat_file( $cache_dir, $filename );
                        if ( ( time() - filemtime( $file ) ) > $ttl ) {
                            unlink ( $file );
                            $do = TRUE;
                        }
                    }
                }
                closedir ( $dh );
            }
        }
        return $do;
    }

    function clear () {
        $cache_dir = $this->cache_dir;
        $do = FALSE;
        $prefix = $this->prefix;
        if ( $dh = opendir ( $cache_dir ) ) {
            while ( $filename = readdir ( $dh ) ) {
                if ( $filename === '.' || $filename === '..' ) {
                    continue;
                }
                if ( strpos( $filename, $prefix ) === 0 ) {
                    $file = __cat_file( $cache_dir, $filename );
                    unlink ( $file );
                    $do = TRUE;
                }
            }
            closedir ( $dh );
        }
        return $do;
    }

}

?>