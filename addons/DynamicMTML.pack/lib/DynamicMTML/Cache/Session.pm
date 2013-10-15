package DynamicMTML::Cache::Session;

use strict;
use warnings;
use base qw/DynamicMTML::Cache/;
use PowerCMS::Util qw( utf8_on );

sub init {
    my $class = shift;
    $class->SUPER::init( @_ );
    return $class;
}

sub get {
    my $class = shift;
    my ( $key, $ttl ) = @_;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    my $session = MT->model( 'session' )->load( { id => $key, kind => 'CO' } );
    if ( $session ) {
        my $data = $session->data;
        if ( $ttl ) {
            my $start = $session->start;
            if ( $start < ( time - $ttl ) ) {
                $session->remove or die $session->errstr;
                return undef;
            }
        } else {
            $ttl = $class->{ ttl };
            my $duration = $session->duration;
            if ( $duration < time ) {
                $session->remove or die $session->errstr;
                return undef;
            }
        }
        return utf8_on( $data );
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
    my $session = MT->model( 'session' )->get_by_key( { id => $key, kind => 'CO' } );
    $session->start( time );
    $ttl = $class->{ ttl } unless $ttl;
    $session->duration( time + $ttl );
    $session->data( $value );
    if ( $updated_at ) {
        my $update_key = $class->{ prefix } . '_upldate_key_' . $updated_at;
        $session->name( $update_key );
    }
    return $session->save or die $session->errstr;
}

sub remove {
    my $class = shift;
    my $key = shift;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_' . $key;
    }
    my $session = MT->model( 'session' )->load( { id => $key, kind => 'CO' } );
    return $session->remove or die $session->errstr;
}

sub purge {
    my $class = shift;
    my $prefix = $class->{ prefix };
    my $do = 0;
    my @session = MT->model( 'session' )->load( { id => { like => "${prefix}%" },
                                                  kind => 'CO',
                                                  duration => { '>' => time } } );
    for my $sess ( @session ) {
        $sess->remove or die $sess->errstr;
        $do = 1;
    }
    return $do;
}

sub clear {
    my $class = shift;
    my $prefix = $class->{ prefix };
    my $do = 0;
    my @session = MT->model( 'session' )->load( { id => { like => "${prefix}%" }, kind => 'CO' } );
    for my $sess ( @session ) {
        $sess->remove or die $sess->errstr;
        $do = 1;
    }
    return $do;
}

sub flush_by_key {
    my $class = shift;
    my $key = shift;
    my $prefix = $class->{ prefix };
    if ( $key !~ /^$prefix/ ) {
        $key = $prefix . '_upldate_key_' . $key;
    }
    my $do = 0;
    my @session = MT->model( 'session' )->load( { name => $key, kind => 'CO' } );
    for my $sess ( @session ) {
        $sess->remove or die $sess->errstr;
        $do = 1;
    }
    return $do;
}

1;