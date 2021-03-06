<?php
function smarty_block_mtvarsrecurse( $args, $content, &$ctx, &$repeat ) {
    if (! isset( $content ) ) {
        $key = $args[ 'key' ];
        $vars = $ctx->__stash[ 'vars' ][ $key ];
        if ( (! $vars ) || (! is_array( $vars ) ) ) {
            $repeat = FALSE;
            return '';
        }
        foreach ( $vars as $key => $val ) {
            $ctx->__stash[ 'vars' ][ $key ] = $val;
            $ctx->__stash[ 'vars' ][ strtolower( $key ) ] = $val;
        }
    } else {
        return $content;
    }
}
?>