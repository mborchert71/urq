<?php

class urq extends pdo {

    public $short_key = array();
    public $request = array();
    public $query = array();

    public function __construct($dsn, $user = null, $pass = null, $options = null) {

        parent::__construct($dsn, $user, $pass, $options);
        parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function instance($setup) {

        if (!is_file($setup)) {
            return;
        }
        $_ = json_decode(file_get_contents($setup));
        $urq = new urq($_->dsn, @$_->user, @$_->pass, @$_->options);
        foreach ($_ as $key => $val) {
            if (property_exists("urq", $key)) {
                $urq->$key = $val;
            }
        }
        return $urq;
    }

    public function request_prepare() {

        $this->request = &$_REQUEST;
        $req = &$this->request;

        foreach ($this->short_key as $key => $word) {
            if (array_key_exists($key, $req)) {
                $req[$word] = &$req[$key];
            }
        }

        if (!array_key_exists("columns", $req)) {
            $req["columns"] = "*";
        }
        if (array_key_exists("limit", $req)) {
            $req["limit"] = "limit " . intval($req["limit"]);
        }
        if (array_key_exists("offset", $req)) {
            $req["offset"] = "offset " . intval($req["offset"]);
        }
        //order
        //where , having
        //group
        //columns
        //column
        if (array_key_exists("column", $req)) {
            if (!is_array($req["column"])) {
                throw new Exception("request-key column is not an assoc array");
            }
            $_ = "";
            foreach ($req["column"] as $key => $val) {
                if (!preg_match("/^[\w]{1,63}$/", $key)) {
                    throw new Exception("request-key in column is not valid: \"$key\"");
                }
                $_.="$key=:col_$key,";
                $_REQUEST["col_$key"] = $val;
            }
            $_REQUEST["column"]= substr($_,0,strlen($_)-1);
        }
        return $this->request;
    }

    public function request_query() {

        $req = &$this->request;

        foreach ($req as $key => $value) {
            if (preg_match("/select|update|insert|delete|create|show|describe|alter/", $key)) {
                $cmd = $this->query->$key;
                unset($req[$key]);
                break;
            }
            if (preg_match("/process|name/", $key)) {
                $cmd = $this->query->{$req[$key]};
                unset($req[$key]);
                break;
            }
        }

        preg_match_all("/:([\w\d_.]{1,63}):/", $cmd, $match);

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

        $cmd = $this->request_query();
print $cmd;
        $query = $this->prepare($cmd);

        foreach ($req as $key => $value) {
            if (preg_match("/:" . $key . "[^\\w:]/", $cmd)) {
                $query->bindParam($key, $req[$key]);
                unset($req[$key]);
            }
        }

        $query->execute();

        return $query;
    }

}
