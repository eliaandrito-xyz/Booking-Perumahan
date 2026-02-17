<?php
class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "perumahan_kupang";
    private $connection;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
?>