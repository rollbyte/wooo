<?php
namespace wooo\core;

interface ISessionHandler {
	/**
	 * @param string $sess_path
	 * @param string $sess_name
	 */
	function open($sess_path, $sess_name);
	
	function close();
	
	/**
	 * @param string $id
	 * @return string
	 */
	function read($id);
	
	/**
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	function write($id, $sess_data);
	
	/**
	 * @param string $id
	 */
	function destroy($id);
	
	/**
	 * @param int $maxlifetime
	 */
	function gc($maxlifetime);
}