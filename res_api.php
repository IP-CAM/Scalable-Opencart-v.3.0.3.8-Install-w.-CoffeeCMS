<?php 

// http://site.com/res_api.php?api_nm=has_order_id&order_id=1

if (is_file('config.php')) {
	require_once('config.php');
}


class CoffeeCMS_ResAPI
{

	public $dbGroup = 'default';
	public $dbConnect = '';
	public $error = '';
	public $is_cache = false;
	public $is_prefix = true;
	public $insert_id = '';

	// Seconds
	public $cache_time = 60;

    public function route()
    {
        $api_nm=isset($_REQUEST['api_nm'])?$_REQUEST['api_nm']:'';

        $result=[
            'error'=>'no',
            'data'=>'',
        ];

        if(isset($api_nm[2]) && method_exists('CoffeeCMS_ResAPI',$api_nm))
        {
            self::connect();

            try {
                $result['error']='no';
                $result['data']=self::$api_nm();
            } catch (\Exception $ex) {
                $result['error']='yes';
                $result['data']=$ex->getMessage();
            }

        }
        else
        {
            $result['error']='yes';
            $result['data']='API name not exists.';
        }

        echo json_encode($result);die();
    }

    public function has_order_id()
    {
        $order_id=isset($_REQUEST['order_id'])?$_REQUEST['order_id']:'';

        
        self::setPrefix(false);
        self::connect();

        $queryStr='';
        $queryStr.="select a.total,a.order_id,a.payment_method,a.ip,a.order_status_id,b.name as status_name";
        $queryStr.=" from oc_order as a left join oc_order_status as b ON a.order_status_id=b.order_status_id";
        $queryStr.="  where a.order_id='".$order_id."' ";

        $loadOrderData=self::query($queryStr);


        $result=[
            'order_data'=>[],
            'product_data'=>[],
        ];

        if(count($loadOrderData) > 0)
        {
            $queryStr='';
            $queryStr.="select a.order_id,b.product_id,b.name,b.quantity,b.price,b.total";
            $queryStr.=" from oc_order as a left join oc_order_product as b ON a.order_id=b.order_id";
            $queryStr.="  where a.order_id='".$order_id."' ";
    
            $loadOrderProdData=self::query($queryStr);
    
            $result['order_data']=$loadOrderData[0];
            $result['product_data']=$loadOrderProdData;

            return $result;
        }
        else
        {
            throw new Exception("This order not exists in our system", 1);
        }

        // throw new Exception("Error Processing Request", 1);
        
    }


	public $db = array(
		'default' => array(
			'DSN' => '',
			'hostname' => DB_HOSTNAME,
			'username' => DB_USERNAME,
			'password' => DB_PASSWORD,
			'database' => DB_DATABASE,
			'port' => 3306,
			'DBDriver' => 'MySQLi',
			'DBPrefix' => '',
			'pConnect' => false,
			'cacheOn' => false,
			'cacheDir' => '',
			'charset' => 'utf8',
			'DBCollat' => 'utf8_general_ci',
			'swapPre' => '',
			'encrypt' => false,
			'compress' => false,
			'strictOn' => false,
			'failover' => [],
		),
	);

	public function setPrefix($isPrefix=true) {
		$this->is_prefix = $isPrefix;
	}
	public function setCache($time) {
		$this->is_cache = true;
		$this->cache_time = $time;
	}

	public function unsetCache() {
		$this->is_cache = false;
		$this->cache_time = 300;
	}

	public function connect() {
		$conn = new mysqli($this->db[$this->dbGroup]['hostname'], $this->db[$this->dbGroup]['username'], $this->db[$this->dbGroup]['password'], $this->db[$this->dbGroup]['database'], $this->db[$this->dbGroup]['port']);
		$conn->set_charset("utf8");

		$this->dbConnect = $conn;

		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}
	}

	public function addPrefix($queryStr) {

		$replaces = array(
			'/insert\s+into\s+(\w+)/i' => 'insert into ' . $this->db['default']['DBPrefix'] . '$1',
			'/insert\s+into\s+[\`\'](\w+)[\`\']/i' => 'insert into ' . $this->db['default']['DBPrefix'] . '$1',

			'/DROP\s+TABLE\s+IF\s+EXISTS\s+(\w+)/i' => 'DROP TABLE IF EXISTS ' . $this->db['default']['DBPrefix'] . '$1',
			'/DROP\s+TABLE\s+IF\s+EXISTS\s+[\`\'](\w+)[\`\']/i' => 'DROP TABLE IF EXISTS ' . $this->db['default']['DBPrefix'] . '$1',

			'/delete\s+from\s+(\w+)/i' => "delete from " . $this->db['default']['DBPrefix'] . '$1',
			'/delete\s+from\s+[\`\'](\w+)[\`\']/i' => 'delete from ' . $this->db['default']['DBPrefix'] . '$1',

			'/CREATE TABLE\s+(\w+)/i' => "CREATE TABLE " . $this->db['default']['DBPrefix'] . '$1',
			'/CREATE TABLE\s+[\`\'](\w+)[\`\']/i' => "CREATE TABLE " . $this->db['default']['DBPrefix'] . '$1',

			'/SHOW TABLES LIKE\s+[\`\'](\w+)[\`\']/i' => "SHOW TABLES LIKE '" . $this->db['default']['DBPrefix'] . "$1'",

			'/ALTER TABLE\s+(\w+)/i' => "ALTER TABLE " . $this->db['default']['DBPrefix'] . '$1',
			'/ALTER TABLE\s+[\`\'](\w+)[\`\']/i' => "ALTER TABLE " . $this->db['default']['DBPrefix'] . '$1',

			'/select\s+(.*)\s+from\s+(\w+)/iU' => "select $1 from " . $this->db['default']['DBPrefix'] . '$2',

			'/join\s+(\w+)/i' => "join " . $this->db['default']['DBPrefix'] . '$1',
			'/update\s+(\w+)/i' => "update " . $this->db['default']['DBPrefix'] . '$1',

		);

		$queryStr = preg_replace(array_keys($replaces), array_values($replaces), $queryStr);

		return $queryStr;
	}

	public function load_from_cache($queryStr) {
		$hash = md5($queryStr);
		$savePath = PUBLIC_PATH . 'caches/sql/' . $hash . '.php';

		$result = [];

		if (file_exists($savePath)) {
			$mod_time = filemtime($savePath);
			// Seconds
			$liveTime = ((double) time() - (double) $mod_time);

			if ((double) $liveTime > (double) $this->cache_time) {
				unlink($savePath);
			}
		}

		if (!file_exists($savePath)) {
			$this->connect();

			if ($this->dbConnect->connect_error) {
				die("Connection failed: " . $this->dbConnect->connect_error . " - Query: " . $queryStr);
			}

			$queryDB = $this->dbConnect->query($queryStr);

			$this->error = $this->dbConnect->error;

			if (isset($this->error[5])) {
				mysqli_close($this->dbConnect);

				die($this->dbConnect->error . " - Query: " . $queryStr);
			}

			if ((int) $queryDB->num_rows > 0) {
				while ($row = $queryDB->fetch_assoc()) {
					$result[] = $row;
				}
			}

			mysqli_close($this->dbConnect);

			create_file($savePath, "<?php Configs::\$_['sql_result']='" . json_encode($result) . "';");
		}

		require_once $savePath;

		$result = json_decode(Configs::$_['sql_result'], true);

		return $result;

	}

	public function query($queryStr = '', $objectStr = '') {
		$result = [];

		$queryStr = $this->addPrefix($queryStr);

		$this->connect();

		if ($this->dbConnect->connect_error) {
			die("Connection failed: " . $this->dbConnect->connect_error . " - Query: " . $queryStr);
		}

		$queryDB = $this->dbConnect->query($queryStr);

		$this->error = $this->dbConnect->error;

		if (isset($this->error[5])) {
			mysqli_close($this->dbConnect);

			die($this->dbConnect->error . " - Query: " . $queryStr);
		}

		if ((int) $queryDB->num_rows > 0) {
			while ($row = $queryDB->fetch_assoc()) {
				$result[] = $row;
			}
		}

		mysqli_close($this->dbConnect);

	
		if (is_object($objectStr)) {
			$objectStr($result);
			return true;
		}

		return $result;

	}

	public function nonquery($queryStr = '', $objectStr = '') {
		$this->connect();

		$queryStr = $this->addPrefix($queryStr);

		$this->dbConnect->multi_query($queryStr);

		$this->error = $this->dbConnect->error;

		if (isset($this->error[5])) {
			mysqli_close($this->dbConnect);

			die($this->dbConnect->error . " - Query: " . $queryStr);
		}

		$this->insert_id=$this->dbConnect->insert_id;

		mysqli_close($this->dbConnect);

		if (is_object($objectStr)) {
			$objectStr();
			return true;
		}

		return true;

	}    
}


$resapi=new CoffeeCMS_ResAPI();

$resapi->route();