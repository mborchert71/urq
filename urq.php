<?php

/**
 * Unified Resource Query
 */
class urq extends pdo {

    const order = "order";
    const columns = "columns";
    const delete = "delete";
    const colval = "colval";
    const from = "from";
    const group = "group";
    const having = "having";
    const insert = "insert";
    const limit = "limit";
    const name = "name";
    const offset = "offset";
    const process = "process";
    const select = "select";
    const table = "table";
    const upate = "update";
    const values = "values";
    const where = "where";

    private static $instance;
    public $short_key;
    public $query;
    public $command;
    public $request = array();

    public function __construct($dsn, $user = null, $pass = null, $options = null) {

        parent::__construct($dsn, $user, $pass, $options);
        parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function load_setup($file) {
        $_ = json_decode(file_get_contents($file));
        foreach ($_ as $key => $val) {
            if (property_exists(__CLASS__, $key)) {
                $this->$key = $val;
            }
        }
    }

    public static function my($setup = null) {//todo: make fileload on New optional
        $request = array();

        if (self::$instance) {
            
            if (is_array($setup)) {
                self::$instance->request = &$setup;
            }
            elseif (is_file($setup)) {
                self::$instance->load_setup($setup);
            }

            return self::$instance;
        }
        elseif (!is_string($setup)) {
            if (!is_file(__DIR__ . "/urq.set")) {
                throw new \Exception("file not found: 'urq.set");
            }
            if (is_array($setup)) {
                $request = $setup;
            }
            $setup = __DIR__ . "/urq.set";
        }

        $_ = json_decode(file_get_contents($setup));
        self::$instance = new urq($_->dsn, @$_->user, @$_->pass, @$_->options);
        self::$instance->request = &$request;

        foreach ($_ as $key => $val) {
            if (property_exists(__CLASS__, $key)) {
                self::$instance->$key = $val;
            }
        }

        return self::$instance;
    }

    public function request_prepare() {

        $req = &$this->request;

        foreach ($this->short_key as $key => $word) {
            if (array_key_exists($key, $req)) {
                $req[$word] = $req[$key];
                unset($req[$key]);
            }
        }
        //limit
        if (array_key_exists("limit", $req)) {
            $req["limit"] = "limit " . intval($req["limit"]);
        }
        //offset
        if (array_key_exists("offset", $req)) {
            $req["offset"] = "offset " . intval($req["offset"]);
        }
        //order
        if (array_key_exists("order", $req)) {
            if (!is_array($req["order"])) {
                throw new Exception("request-key order is not an array");
            }
            $_ = "";
            foreach ($req["order"] as $key => $val) {
                if (preg_match("/^[_.,\d\w]{1,127}$|\s(asc|desc)$/ui", $val)) {
                    $_.= $val . ",";
                }
            }
            $req["order"] = " order by " . substr($_, 0, strlen($_) - 1);
        }

        //where , having
        if (array_key_exists("where", $req)) {
            if (!is_array($req["where"])) {
                throw new Exception("request 'where' must be an array");
            }
            $req["where"] = " where " . $this->request_where_having($req["where"], null, $this->bindcol);
        }

        if (array_key_exists("having", $req)) {
            if (!is_array($req["having"])) {
                throw new Exception("request 'having' must be an array");
            }
            $req["having"] = " having " . $this->request_where_having($req["having"], null, $this->bindcol);
        }
        //group
        //columns
        if (array_key_exists("columns", $req)) {
            if (!is_array($req["columns"])) {
                throw new Exception("request-key columns is not an array");
            }

            foreach ($req["columns"] as $key) {
                if (!preg_match("/^([\w\d_\.]{1,63})(\s[\w\d_\.]{1,63})?$/ui", $key)) {
                    throw new Exception("request-key in columns is not valid: \"$key\"");
                }
            }
            $req["columns"] = implode(",", $req["columns"]);
        }
        else {
            $req["columns"] = "*";
        }
        //values
        if (array_key_exists("values", $req)) {
            if (!is_array($req["values"])) {
                throw new Exception("request-key values is not an assoc array");
            }
            $_ = "";
            foreach ($req["values"] as $key => $val) {
                $_.=":col_$key,";
                $req["col_$key"] = $val;
            }
            $req["values"] = substr($_, 0, strlen($_) - 1);
        }
        //column-value-pair
        if (array_key_exists("colval", $req)) {
            if (!is_array($req["colval"])) {
                throw new Exception("request-key column is not an assoc array");
            }
            $_ = "";
            foreach ($req["colval"] as $key => $val) {
                if (!preg_match("/^[\w]{1,63}$/ui", $key)) {
                    throw new Exception("request-key in column is not valid: \"$key\"");
                }
                $_.="$key=:cv_$key,";
                $req["cv_$key"] = $val;
            }
            $req["colval"] = substr($_, 0, strlen($_) - 1);
        }       
        //process
        if(!array_key_exists("process",$req)){
            $req["process"] = "select";
        }
        
        $return = new stdClass();
        $return->param = &$this->request;

        return $return;
    }

    public function request_query() {

        $req = &$this->request;

        foreach ($req as $key => $value) {
            if (preg_match("/select|update|insert|delete|create|show|describe|alter/", $key)) {
                $cmd = $this->query->$key;
                unset($req[$key]);
                break;
            }
            if (preg_match("/process/", $key)) {
                $cmd = $this->query->{$req[$key]};
                unset($req[$key]);
                break;
            }
        }

        preg_match_all("/:([\w\d_.]{1,63}):/ui", $cmd, $match);

        foreach ($match[1] as $idx => $key) {
            if (array_key_exists($key, $req)) {
                $cmd = str_replace($match[0][$idx], $req[$key], $cmd);
                unset($req[$key]);
            }
            else {
                $cmd = str_replace($match[0][$idx], "", $cmd);
            }
        }
        return $cmd;
    }

    public function request() {

        $req = $this->request_prepare();

        $cmd = $this->command = $this->request_query();

        $query = $this->prepare($cmd);

        foreach ($req->param as $key => $val) {
            if (preg_match("/:" . $key . "[^\w:]?/ui", $cmd)) {
                $query->bindParam($key, $req->param[$key]);
                unset($req->param[$key]);
            }
        }

        $query->execute();

        return $query;
    }

    public function request_where_having($mixed, $mkey) {

        static $wh;
        if (!$wh) {
            $wh = 0;
        }
        if (is_array($mixed)) {
            foreach ($mixed as $key => $val) {
                $mixed[$key] = $this->request_where_having($val, $key, $bind);
            }
            return "( " . implode(" ", $mixed) . " )";
        }
        elseif (is_numeric($mixed)) {
            return $mixed;
        }
        elseif (is_string($mixed)) {
            if (preg_match("/^" . COL_HINT . "[\d\w._\|]{1,63}$/", $mixed)) {
                return str_replace(COL_HINT, "", $mixed);
            }
            if (preg_match("/^(:=|\|\||OR|XOR|&&|AND|NOT|BETWEEN|CASE|WHEN|THEN|ELSE|=|<=>|>=|<=|<|<>|>|!=|IS|LIKE|REGEXP|IN|\||&|<<|>>|-|\+|\*|\/|DIV|%|MOD|^|~|!|BINARY|COLLATE)$/i"
                    , $mixed) && $mkey & 1) {
                return $mixed;
            }
            if (preg_match("/^NULL$/i", $mixed)) {
                return $mixed;
            }
            if (preg_match("/^[^\s^\t^\n^\r]{1,63}/ui", $mixed)) {
                $wh++;
                $this->request["wh" . $wh] = $mixed;
                return ":wh" . $wh;
            }
        }
        throw new Exception("request 'where' is not valid: $mixed");
    }

}
