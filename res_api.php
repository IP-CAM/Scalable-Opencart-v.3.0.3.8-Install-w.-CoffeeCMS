<?php 

// http://site.com/res_api.php?api_nm=has_order_id&order_id=1

if (is_file('config.php')) {
	require_once('config.php');
}

require_once('coffeecms_pointback.php');

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
        $queryStr.="  where a.order_id='".$order_id."' AND a.order_status_id IN ('2','1','5','15') ";

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

    public function get_list_product()
    {
        $category_id=isset($_REQUEST['category_id'])?$_REQUEST['category_id']:'';
        $limit=isset($_REQUEST['limit'])?$_REQUEST['limit']:'12';
        $page_no=isset($_REQUEST['page_no'])?$_REQUEST['page_no']:'1';

        if((int)$page_no > 0)
        {
            $page_no=(int)$page_no-1;
        }
        if((int)$page_no<=0)
        {
            $page_no=0;
        }

        $offset=(int)$page_no*12;

        
        self::setPrefix(false);
        self::connect();

        $queryStr='';
		$queryStr=" select a.product_id,a.image,a.price,b.category_id,c.name as category_name,prod.name as product_title";
		$queryStr.=" from oc_product as a";
		$queryStr.=" left join oc_product_description as prod ON a.product_id=prod.product_id";
		$queryStr.=" left join oc_product_to_category as b ON a.product_id=b.product_id";
		$queryStr.=" left join oc_category_description as c ON b.category_id=c.category_id";
		$queryStr.=" where a.status='1'";

		if(strlen($category_id) > 0)
		{
			$queryStr.=" AND b.category_id='".$category_id."' ";
		}
		
		$queryStr.=" order by a.date_added desc limit ".$page_no.",".$limit;
		
        $loadData=self::query($queryStr);

        return $loadData;
    }

    public function get_list_category()
    {
        
        self::setPrefix(false);
        self::connect();

		$result=[];

        $queryStr='';
		$queryStr=" SELECT a.*,b.name,b.description";
		$queryStr.=" FROM oc_category as a";
		$queryStr.=" left join oc_category_description as b ON a.category_id=b.category_id";
		$queryStr.=" where a.status='1' and parent_id='0'";
		$queryStr.=" order by a.sort_order asc";
		
        $topData=self::query($queryStr);

        $queryStr='';
		$queryStr=" SELECT a.*,b.name,b.description";
		$queryStr.=" FROM oc_category as a";
		$queryStr.=" left join oc_category_description as b ON a.category_id=b.category_id";
		$queryStr.=" where a.status='1' and parent_id<>'0'";
		$queryStr.=" order by a.parent_id,a.sort_order asc";
		
        $subData=self::query($queryStr);

		$total=count($topData);
		$totalSub=count($subData);

		for ($i=0; $i < $total; $i++) { 

			array_push($result,$topData[$i]);
			
			for ($j=0; $j < $totalSub; $j++) { 
				if($topData[$i]['category_id']==$subData[$j]['parent_id'])
				{
					array_push($result,$subData[$j]);
				}

				// for ($k=0; $k < $totalSub; $k++) { 
				// 	if($subData[$j]['category_id']==$subData[$k]['parent_id'])
				// 	{
				// 		array_push($result,$subData[$k]);
				// 	}
				// }
			}
		}

        return $result;
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