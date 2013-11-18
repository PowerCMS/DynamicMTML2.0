<?php
class DynamicCacheSession extends DynamicCache {

    public $ttl;
    public $prefix;
    public $session;

    function __construct( $app = NULL ) {
        $this->app = $app;
        $this->ttl = $app->cache_driver->ttl;
        $this->prefix = $app->config( 'DynamicCachePrefix' );
        require_once( 'class.mt_session.php' );
    }

    function get ( $key, $ttl = NULL, $raw = FALSE ) {
        $session = new Session;
        $where = "session_id = '${key}' AND session_kind='CO'";
        $extra = array( 'limit' => 1 );
        $cache = $session->Find( $where, FALSE, FALSE, $extra );
        if ( $cache ) {
            if ( $raw ) return $cache;
            $cache = $cache[ 0 ];
            if ( $ttl && ( $cache->start < ( time() - $ttl ) ) ) {
                return FALSE;
            }
            return $cache->data;
        }
        return FALSE;
    }

    function set ( $key, $value, $ttl = NULL, $updated_at = NULL ) {
        if (! $ttl ) {
            $ttl = $this->ttl;
        }
        if ( is_object( $value ) || is_array( $value ) ) {
            $value = serialize( $value );
        }
        $start = time();
        $session = new Session;
        $session->session_id = $key;
        $session->session_kind = 'CO';
        $session->session_start = $start;
        $session->session_duration = $start + $ttl;
        $session->data = $value;
        if ( $updated_at ) {
            $session->name = $this->prefix . '_upldate_key_' . $updated_at;
        }
        if ( $this->get( $key, NULL, TRUE ) ) {
            return $session->Update();
        }
        return $session->save();
    }

    function remove ( $key ) {
        echo $key;
        if ( $cache = $this->get( $key, NULL, 1 ) ) {
            return $cache->Delete();
        }
        return FALSE;
    }

    function purge ( $clear = FALSE ) {
        $prefix = $this->prefix;
        if ( $clear === FALSE ) {
            $duration = time();
            $duration = "AND session_duration < ${duration}";
        }
        $session = new Session;
        $where = "session_id LIKE '${prefix}%' AND session_kind='CO' ${duration}";
        $extra = array();
        $objects = $session->Find( $where, FALSE, FALSE, $extra );
        $do = FALSE;
        $error;
        if ( $objects ) {
            foreach( $objects as $cache ) {
                if ( $cache->Delete() ) {
                    $do = TRUE;
                } else {
                    $error = 1;
                }
            }
        }
        if ( $error ) return NULL;
        return $do;
    }

    function clear ( $flush = NULL, $debug = NULL ) {
        return $this->purge( TRUE );
    }

    function flush_by_key ( $key ) {
        $prefix = $this->prefix;
        $session = new Session;
        $where = "session_id LIKE '${prefix}%' AND session_kind='CO' AND session_name='${key}'";
        $extra = array();
        $objects = $session->Find( $where, FALSE, FALSE, $extra );
        $do = FALSE;
        $error;
        if ( $objects ) {
            foreach( $objects as $cache ) {
                if ( $cache->Delete() ) {
                    $do = TRUE;
                } else {
                    $error = 1;
                }
            }
        }
        if ( $error ) return NULL;
        return $do;
    }

}

?>