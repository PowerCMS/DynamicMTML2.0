<?php
function smarty_function_mtresetstash ( $args, &$ctx ) {
    $stash = $args[ 'stash' ];
    if (! $stash ) return'';
    $key = '';
    if ( isset( $args[ 'key' ] ) ) $key = $args[ 'key' ];
    $ctx->stash( '__get_hash_var_old_stash_' . $key, $ctx->stash( $stash ) );
    $ctx->stash( $stash, NULL );
}
?>