package DynamicMTML::Cache::Memcache;

use strict;
use warnings;
use base qw/DynamicMTML::Cache/;
use PowerCMS::Util qw( utf8_on );

sub init {
    my $class = shift;
    $class->SUPER::init( @_ );
    my $memcache = MT->request( 'DynamicMemcachedServers' );
    if (! $memcache ) {
        my @default = MT->config->MemcachedServers;
        my @server  =  MT->config->DynamicMemcachedServers;
        require MT::Memcached;
        if ( @server ) {
            MT->instance->config( 'MemcachedServers', @server );
            $memcache = MT::Memcached->new();
            MT->instance->config( 'MemcachedServers', @default );
        } else {
            $memcache = MT::Memcached->instance;
        }
    }
    if ( defined $memcache ) {
        MT->request( 'DynamicMemcachedServers', $memcache );
    }
    $class->{ client } = $memcache;
    return $class;
}

sub get {
    my $class = shift;
    my $key = shift;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    return utf8_on( $class->{ client }->get( $key ) );
}

sub set {
    my $class = shift;
    my ( $key, $value, $ttl, $updated_at ) = @_;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    $ttl = $class->{ ttl } unless $ttl;
    if ( $updated_at ) {
        my $update_key = $class->{ prefix } . '_upldate_key_' . $updated_at;
        my $update_keys;
        if ( $update_keys = $class->get( $update_key ) ) {
            my @keys = split( /,/, $update_keys );
            if (! grep( /^$key$/, @keys ) ) {
                push ( @keys, $key );
                $update_keys = join( ',', @keys );
            }
        }
        $update_keys = $key unless $update_keys;
        $class->{ client }->set( $update_key, $update_keys, $class->{ ttl } );
    }
    # TODO::If value is less than 60*60*24*30 (30 days),
    # time is assumed to be relative from the present. If larger, it's considered an absolute Unix time.
    return $class->{ client }->set( $key, $value, $ttl );
}

sub remove {
    my $class = shift;
    my $key = shift;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    return $class->{ client }->delete( $key );
}

sub purge {
    my $class = shift;
    return $class->clear();
}

sub clear {
    my $class = shift;
    my $prefix = $class->{ prefix };
    # flush_all
    my $memcache = $class->{ client };
    my $driver = MT->config( 'MemcachedDriver' );
    my $do = 0;
    my $error;
    if ( $driver eq 'Cache::Memcached' ) {
        my $slabs_all = $memcache->stats( [ 'slabs' ] );
        my $server_slabs = $slabs_all->{ hosts } if $slabs_all;
        for my $key ( keys %$server_slabs ) {
            my $slab = $server_slabs->{ $key }->{ slabs };
            my @slabs = split( /\n/, $slab );
            my %all_items;
            for my $str ( @slabs ) {
                if ( $str =~ m/^STAT(.*)?:/ ) {
                    my $id = $1;
                    $id =~ s/\s//g;
                    $all_items{ $id } = $str
                        if ( $id =~ m/^[0-9]{1,}$/ );
                }
            }
            for my $key ( keys %all_items ) {
                my $cm = "cachedump $key 10000";
                my $cache = $memcache->stats( $cm );
                next unless defined $cache;
                $cache = $cache->{ hosts };
                for my $key ( keys %$cache ) {
                    my $items = $cache->{ $key };
                    for my $item ( keys %$items ) {
                        my $cache_item = $items->{ $item };
                        next unless $cache_item;
                        my @items = split( /\n/, $cache_item );
                        for my $line ( @items ) {
                            my $cache_key = $1 if ( $line =~ /^ITEM\s(.*?)\s/ );
                            if ( $cache_key && $cache_key =~ /^$prefix/ ) {
                                if ( $memcache->delete( $cache_key ) ) {
                                    $do = 1;
                                } else {
                                    $error = 1;
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        if ( $memcache->flush_all() ) {
            $do = 1;
        } else {
            $error = 1;
        }
    }
    return undef if $error;
    return $do;
}

1;