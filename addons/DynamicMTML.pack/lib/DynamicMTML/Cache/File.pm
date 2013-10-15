package DynamicMTML::Cache::File;

use strict;
use warnings;
use base qw/DynamicMTML::Cache/;
use File::Spec;
use MT::FileMgr;
use PowerCMS::Util qw( get_children_files powercms_files_dir_path
                       read_from_file write2file );

sub init {
    my $class = shift;
    $class->SUPER::init( @_ );
    my $cache_dir = File::Spec->catdir( powercms_files_dir_path(), 'cache' );
    $class->{ cache_dir } = $cache_dir;
    my $fmgr = MT::FileMgr->new( 'Local' );
    $class->{ fmgr } = $fmgr;
    return $class;
}

sub get {
    my $class = shift;
    my ( $key, $ttl ) = @_;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    $ttl = $class->{ ttl } unless $ttl;
    my $file = File::Spec->catfile( $class->{ cache_dir }, $key );
    if (-f $file ) {
        my @stats = stat $file;
        if ( $stats[ 9 ] < ( time - $ttl ) ) {
            unlink $file;
            return undef;
        }
        return read_from_file( $file );
    }
    return undef;
}

sub set {
    my $class = shift;
    my ( $key, $value, $ttl, $updated_at ) = @_;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    my $file = File::Spec->catfile( $class->{ cache_dir }, $key );
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
        $class->set( $update_key, $update_keys );
    }
    return write2file( $file, $value );
}

sub remove {
    my $class = shift;
    my $key = shift;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    my $file = File::Spec->catfile( $class->{ cache_dir }, $key );
    return $class->{ fmgr }->delete( $file );
}

sub purge {
    my $class = shift;
    return $class->clear( 1 );
}

sub clear {
    my $class = shift;
    my $purge = shift;
    my $cache_dir = File::Spec->catfile( $class->{ cache_dir } );
    my $prefix = $class->{ prefix };
    my $do = 0;
    my $error;
    my $ttl = $class->{ ttl };
    my @caches = get_children_files( $cache_dir, "/$prefix/" );
    for my $file ( @caches ) {
        if ( $purge ) {
            my @stats = stat $file;
            if ( $stats[ 9 ] < ( time - $ttl ) ) {
                if ( $class->{ fmgr }->delete( $file ) ) {
                    $do = 1;
                } else {
                    $error = 1;
                }
            }
        } else {
            if ( $class->{ fmgr }->delete( $file ) ) {
                $do = 1;
            } else {
                $error = 1;
            }
        }
    }
    return undef if $error;
    return $do;
}

1;