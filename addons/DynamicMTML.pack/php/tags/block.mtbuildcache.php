<?php
function smarty_block_mtbuildcache( $args, $content, $ctx, $repeat ) {
    $app = $ctx->stash( 'bootstrapper' );
    $ttl = $args[ 'ttl' ];
    $key = $args[ 'key' ];
    $in_request = $args[ 'in_request' ];
    $updated_at = $args[ 'updated_at' ];
    $cache = $ctx->stash( 'buildcache:' . $key );
    if ( isset( $cache ) ) {
        return $cache;
    }
    $driver = $app->cache_driver;
    if (! isset( $content ) ) {
        if (! $in_request ) {
            if ( $driver ) {
                if (! $ttl ) $ttl = $driver->ttl;
                if ( $cache = $driver->get( $key, $ttl ) ) {
                    $ctx->stash( 'buildcache:' . $key, $cache );
                    return $cache;
                }
            } else {
                require_once( 'class.mt_session.php' );
                $session = new Session;
                $ttl = $app->escape( $ttl );
                $key = $app->escape( $key );
                $where = "session_id = '${key}'";// AND session_duration > '{$duration}'";
                $extra = array( 'limit' => 1 );
                $cache = $session->Find( $where, FALSE, FALSE, $extra );
                $duration = time();
                if ( isset( $cache ) ) {
                    if ( $cache->duration < $duration ) {
                        $ctx->stash( 'buildcache:' . $key, $cache[ 0 ]->data );
                        return $cache[ 0 ]->data;
                    } else {
                        $cache->Remove();
                    }
                }
            }
        }
    } else {
        if (! $in_request ) {
            if ( $driver ) {
                if (! $ttl ) $ttl = $driver->ttl;
                $driver->set( $key, $content, $ttl, $updated_at );
            } else {
                require_once( 'class.mt_session.php' );
                $session = new Session;
                $ttl = $app->escape( $ttl );
                $key = $app->escape( $key );
                $duration = time();
                $session->session_id = $key;
                $session->session_kind = 'CO';
                $session->session_start = $duration;
                $session->session_duration = $duration + $ttl;
                $session->data = $content;
                $session->Save();
            }
        }
        $ctx->stash( 'buildcache:' . $key, $content );
        return $content;
    }
}
?>