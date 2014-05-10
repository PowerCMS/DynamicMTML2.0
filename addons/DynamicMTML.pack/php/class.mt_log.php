<?php
require_once( 'class.baseobject.php' );
class Log extends BaseObject {
    public $_table = 'mt_log';
    protected $_prefix = "log_";
    public function Save() {
        $mt = MT::get_instance();
        if ( $created_on = $this->created_on ) {
            $this->created_on = $mt->db()->ts2db( $created_on );
        }
        if ( $modified_on = $this->modified_on ) {
            $this->modified_on = $mt->db()->ts2db( $modified_on );
        }
        $driver = $mt->config( 'ObjectDriver' );
        if ( strpos( $driver, 'SQLServer' ) !== FALSE ) {
            // UMSSQLServer
            $sql = 'select max(log_id) from mt_log';
            $res = $mt->db()->Execute( $sql );
            $max = $res->_array[ 0 ][ '' ];
            $sql = 'SET IDENTITY_INSERT mt_log ON';
            $mt->db()->Execute( $sql );
            $max++;
            $this->id = $max;
            try {
                $this->Insert();
            } catch ( Exception $e ) {
                // $e->getMessage();
                $max += 10;
                $this->id = $max;
                try {
                    $res = $this->Insert();
                } catch ( Exception $e ) {
                }
            }
            $sql = 'SET IDENTITY_INSERT mt_log OFF';
            $mt->db()->Execute( $sql );
        } else {
            $res = parent::Save();
        }
    }
}
?>