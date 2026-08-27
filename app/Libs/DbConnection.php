<?php
class app_Libs_DbConnection
{
    protected $username = "root";
    protected $password = "";
    protected $host = "localhost";
    protected $database = "mantamarket";
    protected $tableName;
    protected $queryParams = [];
    protected static $connectionInstance = null;

    public function __construct()
    {
        $this->connect();
    }

    public function connect()
    {
        if (self::$connectionInstance == null) {
            try {
                self::$connectionInstance = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->username);
                self::$connectionInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $ex) {
                echo "ERROR:" . $ex->getMessage();
                die();
            }
        }
        return self::$connectionInstance;
    }

    public function query($sql, $param = [])
    {
        $q = self::$connectionInstance->prepare($sql);
        if (is_array($param) && $param) {
            $q->execute($param);
        } else {
            $q->execute();
        }
        return $q;
    }

    public function buildQueryParams($params)
    {
        $default = [
            "select" => "*",
            "join"   => "",   // 👈 thêm dòng này
            "where" => "",
            "other" => "",
            "params" => ""
        ];
        $this->queryParams = array_merge($default, $params);
        return $this;
    }

    public function buildCondition($condition)
    {
        if (trim($condition)) {
            return "where " . $condition;
        }
        return "";
    }

public function select()
{
    $sql = "select " . $this->queryParams["select"] . 
           " from " . $this->tableName . " " .
           $this->queryParams["join"] . " " .   // 👈 thêm JOIN vào đây
           $this->buildCondition($this->queryParams["where"]) . " " . 
           $this->queryParams["other"];

    $query = $this->query($sql, $this->queryParams["params"]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

    public function selectOne(): array
    {
        $this->queryParams["other"] = "limit 1";
        $data = $this->select();
        return $data ? $data[0] : [];
    }

    public function insert()
    {
        $sql = "insert into " . $this->tableName . " " . $this->queryParams["field"];
        $result = $this->query($sql, $this->queryParams["value"]);
        if ($result) {
            return self::$connectionInstance->lastInsertId();
        } else {
            return FALSE;
        }
    }


    
    public function update()
    {
        $sql = "update " . $this->tableName . " set " . $this->queryParams["value"] . " " . $this->buildCondition($this->queryParams["where"]) . " " . $this->queryParams["other"];
        return $this->query($sql, $this->queryParams["params"]);
    }

    public function delete($where = "", $params = [])
    {
        $sql = "delete from " . $this->tableName . " " . $this->buildCondition($this->queryParams["where"]) . "" . " " . $this->queryParams["other"];
        return $this->query($sql, $this->queryParams["params"]);
    }
}
