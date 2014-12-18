<?php
/**
* Licensed to Level 7 Systems Ltd. under one or more contributor
* license agreements. Level 7 Systems Ltd. licenses
* this file to you under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License. You may
* obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
* WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
* License for the specific language governing permissions and limitations
* under the License.
*/

class PhpCrate
{
    private $read_timeout = 10000; // ms
    private $port = 4200;
    
    private $servers = array();
    
    private $_socket = null;
    private $server;

    /**
     * Sets array of servers to use
     * 
     * @param array $servers array of server IP address to use
     */
    public function setServers($servers)
    {
        foreach ($servers as $server) {
            $this->servers[$server] = true;
        }
    }
    
    /**
     * Sets port number to use
     * 
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }
    
    /**
     * Connect to Crate.io server
     *
     * @return bool
     */
    public function connect()
    {
        if (!$this->servers) {
            throw new Exception("No servers to connect left");
        }
        
        $servers = array_keys($this->servers);
        
        $this->server = $servers[rand(0, count($servers) - 1)];
        
        if (!$this->_socket = @fsockopen($this->server, $this->port, $err_no, $err_str, 2)) {
            $this->_socket = null;
            unset($this->servers[$this->server]);
            return false;
        }
        
        stream_set_timeout($this->_socket, 3);
        stream_set_blocking($this->_socket, 0);
        
        return true;
    }
    
    /**
     * Executes INSERT, UPDATE, DELETE statement
     * 
     * @param string $sql   SQL query to execute
     * @param array  $args  array of parameters to pass to the query
     * 
     * @return int number of rows affected
     */
    public function exec($sql, $args = array())
    {
        $response = null;
        
        while (!$response) {
            $response = $this->send($sql, $args);
        }
        
        if (!isset($response['rowcount'])) {
            throw new Exception("rowcount parameter missing in server response");
        }
        
        return $response['rowcount'];
    }
    
    /**
     * Executes SELECT statement
     * 
     * @param string $sql   SQL query to execute
     * @param array  $args  array of parameters to pass to the query
     * 
     * @return array results as an associative array
     */
    public function query($sql, $args = array())
    {
        $response = null;
        
        while (!$response) {
            $response = $this->send($sql, $args);
        }
        
        if (!isset($response['rows'])) {
            throw new Exception("rows parameter missing in server response");
        }
        
        if (!isset($response['cols'])) {
            throw new Exception("cols parameter missing in server response");
        }
        
        $output = array();
        
        foreach ($response['rows'] as $index => $row) {
            foreach ($row as $key => $value) {
                $output[$index][$response['cols'][$key]] = $value;
            }
        }
        
        return $output;
    }
    
    private function send($sql, $args)
    {
        if (!$this->_socket) {
            
            while (!$this->_socket) {
                $this->connect();
            }
        }
        
        $json = json_encode(array(
            "stmt" => $sql,
            "args" => $args,
        ));
        
        $data = "POST /_sql HTTP/1.1\r\n"
                ."Accept: */*\r\n"
                ."Host: ".$this->server.":".$this->port."\r\n"
                ."Content-Length: ".strlen($json)."\r\n\r\n"
                .$json."\r\n";
        
        if (!fwrite($this->_socket, $data, strlen($data))) {
            unset($this->servers[$this->server]);
            $this->_socket = null;
            return false;
        }
        
        $response_length = 0;
        
        $i = 0;
        
        $response = '';
        
        $body = false;
        
        while (1) {
            
            $line = trim(fgets($this->_socket, 1024));
            
            $m = array();
            
            if (preg_match('/^Content-Length: ([0-9]+)$/', $line, $m)) {
                $response_length = $m[1];
            }
            
            // if we have Content-Length and there is an ampty line, what is below must be response body
            if ($response_length && !$line) {
                $body = true;
            }
            
            if ($body) {
                
                if (!$response_length) {
                    throw new Exception("Failed to parse Content-Length header from server response");
                }
                
                $response.= $line;
                
                if (strlen($response) == $response_length) {
                    break;
                }
            }
            
            usleep(5);
            
            $i++;
            
            if ($i > ($this->read_timeout * 20)) {
                return false;
            }
        }

        if ($response == '') {
            throw new Exception("Failed to read response to '$sql'");
        }
        
        if (!$json = json_decode($response, true)) {
            throw new Exception("Failed to json_decode '$response'");
        }
        
        
        if (isset($json['error'])) {
            throw new Exception("(".$json['error']['code'].") ".$json['error']['message']);
        }
        
        return $json;
    }

    /**
     * Close the connection
     *
     */
    public function __destruct()
    {
        if ($this->_socket) {

            return fclose($this->_socket);
        }
    }
}
