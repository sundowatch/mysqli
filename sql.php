    <?php 

class sql{

    function __construct($sqlenv_file='sql.env'){
        $sql_info = $this->sql_file($sqlenv_file);
        $this->db_info = $sql_info;
        $this->conn = new mysqli($sql_info->host, $sql_info->username, $sql_info->password, $sql_info->database);
        if($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    /**
     * Private Function sql_file : retrieves sql file
     * @param String $sqlenv_file : sql filename
     * @return Object database info
     */
    private function sql_file($sqlenv_file){
        $file_content = file_get_contents($sqlenv_file);
        $lines = explode(PHP_EOL, $file_content);

        $ret = new stdClass();

        foreach($lines AS $line){
            $line = explode('=',$line);
            if($line[0] == 'HOST')
                $ret->host = $line[1];
            elseif($line[0] == 'DATABASE')
                $ret->database = $line[1];
            elseif($line[0] == 'USERNAME')
                $ret->username = $line[1];
            elseif($line[0] == 'PASSWORD')
                $ret->password = $line[1];
        }

        return $ret;
    }

    /**
     * Public Function query : mysqli query
     * @param String $query : Query String
     * @return Query : SQL Query
     */
    public function query($query){
        return $this->conn->query($query);
    }

    /**
     * Public Function fetch : fetch_array
     * @param Boolean $bulk_array : converts result to Array
     * @return : Fetch || Array
     */
    public function fetch($query, $bulk_array=FALSE){
        if(!$bulk_array){
            return $query->fetch_array();
        } else{
            $ret_arr = [];
            while($q = $query->fetch_array()){
                array_push($ret_arr, $q);
            }
            return $ret_arr;
        }
    }

    /**
     * Public Function where : sql where query
     * @param String $table : table name
     * @param String $conditions: conditions
     * @param String $orderby : order by 
     * @return Query 
     */
    public function where($table, $conditions, $orderby=''){
        return $this->query("SELECT * FROM {$table} WHERE {$conditions} {$orderby}");
    }

    /**
     * Public Function update : update query
     * @param String $table : table name
     * @param String $update_data : data query will be updated
     * @param String $where_condition : query where condition
     * @param Boolean $update_date : updates date
     * @return Query
     */
    public function update($table, $update_data, $where_condition, $update_date=true){
        if($update_date)
            $up = ', update_date=NOW()';
        else
            $up = '';
        return $this->query("UPDATE {$table} SET {$update_data}{$up} WHERE {$where_condition}");
    }

    /**
     * Public Function delete : delete query
     * @param String $table : table name
     * @param String $where_condition : query where condition
     * @param Boolean $only_is_deleted : will it update only is_deleted column
     * @return Query
     */
    public function delete($table, $where_condition, $only_is_deleted=true){
        if($only_is_deleted){
            return $this->query("UPDATE {$table} SET is_deleted=1 WHERE {$where_condition}");
        } else{
            return $this->query("DELETE FROM {$table} WHERE {$where_condition}");
        }
    }

    /**
     * Public Function insert : insert query
     * @param String $table : table name
     * @param String $insert_cols : insert columns
     * @param String $insert_values : insert values
     * @return Query
     */
    public function insert($table, $insert_cols, $insert_values){
        return $this->query("INSERT INTO {$table}({$insert_cols}) VALUES({$insert_values})");
    }

    /**
     * Public Function table_columns : sql table columns
     * @param String $table : table name
     * @param Boolean $strip : will not be included columns
     * @return Array column names
     */
    public function table_columns($table, $strip=FALSE){
        $columns = $this->query("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='{$this->db_info->database}' AND `TABLE_NAME`='{$table}';");
        if($strip){
            $strip = str_replace(' ', '', $strip);
            $strip = explode(',', $strip);
        }
        $ret_arr = [];

        while($col = $columns->fetch_array()){
            if($strip){
                if(array_search($col[0], $strip) === FALSE){
                    array_push($ret_arr, $col[0]);
                }
            } else{
                array_push($ret_arr, $col[0]);
            }
        }
        return $ret_arr;
    }

    /**
     * Public function batch_insert : void
     * @param String $table : table name
     * @param Array $insert_values_arr : will be inserted values : this must be ordered according to database table
     * @param String $additional_strip_column : will be passed columns in the database table
     * @return : Array : Sql results
     */
    public function batch_insert($table, $insert_values_arr, $additional_strip_column = FALSE){
        if(!$additional_strip_column){
            $additional_strip_column = '';
        }
        else{
            $additional_strip_column = ','.$additional_strip_column;
        }
        $columns = $this->table_columns($table, 'id, create_date, update_date, is_deleted '.$additional_strip_column);
        $columns = implode(',',$columns);
        $ret_arr = [];
        foreach($insert_values_arr AS $ins){
            $query = "INSERT INTO {$table}({$columns}) VALUES({$ins})";
            $q = $this->query($query);
            array_push($ret_arr, $q);
        }

        return $ret_arr;
    }

    /**
     * Public function batch_insert : void
     * @param String $table : table name
     * @param String $filename : from file
     * @param String $additional_strip_column : will be passed columns in the database table
     * @return : Array : Sql Results
     */
    public function batch_insert_from_file($table, $filename, $additional_strip_column = FALSE){
        $insert_values_arr = $this->file_lines($filename,"'");
        $batch = $this->batch_insert($table, $insert_values_arr, $additional_strip_column);
        return $batch;
    }

    /**
     * Public Function json_query : returns matched json key value
     * @param String $table 
     * @param String $where_condition
     * @param String $json_col : json columnd of table
     * @param String $json_key : json field key
     * @return Mixed matched value
     */
    public function json_query($table, $where_condition, $json_col='json', $json_key){
        $query = $this->query("SELECT {$json_col} FROM {$table} WHERE {$where_condition}");
        $json = $this->fetch($query, true);
        $json = json_decode($json, true);

        $ret = NULL;
        foreach($json AS $key=>$val){
            if($key == $json_key){
               $ret = $val;
                break;
            }
        }

        return $ret;
    }

    /**
     * Public Functin json_where : returns matched json
     * @param String $table : table name
     * @param String $json_col : json column name like json
     * @param String $json_where_condition : json condition
     * @return Array matched rows
     */
    public function json_where($table, $json_col='json', $json_where_condition='key=value'){
        $query = $this->query("SELECT * FROM {$table}");
        
        $where = explode('=', $json_where_condition);

        $ret_row_arr = [];
        while($row = $query->fetch_array()){
            $json = $json[$json_col];
            $json = json_decode($json, true);
            if($json[$where[0]] == $where[1]){
                array_push($ret_row_arr, $row);
            }
        }

        return $ret_row_arr;
    }


    /**
     * Public Function file_lines : explodes file lines with line break
     * @param String $filename : file name
     * @param String|Boolean $wrap : wrap the string with given character : It has made for generally wrap the text with '
     * @return : Array : lines
     */
    public function file_lines($filename, $wrap=FALSE){
        $file = file_get_contents($filename);
        $file = explode(PHP_EOL, $file);
        if($wrap){
            for($i = 0; $i < count($file); $i++){
                $file[$i] = $wrap.$file[$i].$wrap;
            }
        }
        return $file;
    }

    

    function __destruct(){
        $this->conn->close();
    }
}

?>