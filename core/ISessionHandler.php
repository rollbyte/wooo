<?php

namespace wooo\core;

interface ISessionHandler
{
  
    /**
     *
     * @param string $sess_path
     * @param string $sess_name
     */
    public function open($sess_path, $sess_name);
  
    public function close();
  
    /**
     *
     * @param  string $id
     * @return string
     */
    public function read($id);
  
    /**
     *
     * @param  string $id
     * @param  string $sess_data
     * @return boolean
     */
    public function write($id, $sess_data);
  
    /**
     *
     * @param string $id
     */
    public function destroy($id);
  
    /**
     *
     * @param int $maxlifetime
     */
    public function gc($maxlifetime);
}
