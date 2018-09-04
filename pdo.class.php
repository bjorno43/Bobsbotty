<?php

Class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    private $dbh;
    private $error;

    private $stmt;

    public function __construct(){
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
        // Set options
        $options = array(
            PDO::ATTR_PERSISTENT    => true,
            PDO::ATTR_ERRMODE       => PDO::ERRMODE_EXCEPTION
        );
        // Create a new PDO instanace
        try{
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        }
        // Catch any errors
        catch(PDOException $e){
            $this->error = $e->getMessage();
			return $this->error;
        }
    }

	/* Prepare query */
    public function query($query){
        $this->stmt = $this->dbh->prepare($query);
    }

	/* Bind parameters */
    public function bind($param, $value, $type = null){
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

	/* Execute query */
    public function execute(){
        return $this->stmt->execute();
    }

	/* Fetch column */
    public function column(){
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_COLUMN);
    }

	/* Fetch multiple rows */
    public function resultset(){
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

	/* Fetch single row */
    public function single(){
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

	/* Fetch number of rows */
    public function rowCount(){
        return $this->stmt->rowCount();
    }

	/* Fetch last inserted id */
    public function lastInsertId(){
        return $this->dbh->lastInsertId();
    }

	/* Start transaction */
    public function beginTransaction(){
        return $this->dbh->beginTransaction();
    }

	/* Finish transaction */
    public function endTransaction(){
        return $this->dbh->commit();
    }

	/* Cancel transaction */
    public function cancelTransaction(){
        return $this->dbh->rollBack();
    }

	/* Debug parameters */
    public function debugDumpParams(){
        return $this->stmt->debugDumpParams();
    }
}

?>
