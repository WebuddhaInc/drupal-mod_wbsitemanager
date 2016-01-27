<?php

defined('_DEXEC') or die();

/**
 * Parameter Object
 */

  class wbSiteManager_Params {

    /**
     * [__construct description]
     * @param array $data [description]
     */
    public function __construct( $data = array(), $parser = null ){
      if( is_array($data) || is_object($data) )
        if( $parser == 'http-cli' ){
          $req = $data = (array)$data;
          foreach( $req AS $k => $v ){
            if( is_null($v) || !strlen($v) ){
              $data = array_merge($data, array_fill_keys(str_split($k), 1));
              unset($data[$k]);
            }
          }
        }
        if( $parser == 'cli' ){
          $req = (array)$data;
          $data = array();
          for( $i=0; $i<count($req); $i++ ){
            $val = $req[$i];
            if( strpos($val, '--') === 0 ){
              $data[ substr($val, 2) ] = isset($req[ ++$i ]) ? $req[ $i ] : 1;
            }
            else if( strpos($val, '-') === 0 ){
              $data = array_merge($data, array_fill_keys(str_split(substr($val, 1)), 1));
            }
            else {
              $data[ end(array_keys($data)) ] = $val;
            }
          }
        }
        foreach( $data AS $k => $v ){
          if( is_array($data) )
            $this->set( $k, $data[$k] );
          else
            $this->set( $k, $data->{$k} );
        }
    }

    /**
     * [set description]
     * @param [type] $k [description]
     * @param [type] $v [description]
     */
    public function set( $k, $v ){
      $this->{$k} =& $v;
    }

    /**
     * [get description]
     * @param  [type] $k [description]
     * @param  [type] $d [description]
     * @return [type]    [description]
     */
    public function get( $k, $d = null ){
      return isset($this->{$k}) ? $this->{$k} : $d;
    }

  }
