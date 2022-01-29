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
	public static $insert_id = '';

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

    public function get_list_order()
    {
        $category_id=isset($_REQUEST['category_id'])?$_REQUEST['category_id']:'';
        $user_id=isset($_REQUEST['user_id'])?$_REQUEST['user_id']:'';
        $limit=isset($_REQUEST['limit'])?$_REQUEST['limit']:'15';
        $page_no=isset($_REQUEST['page_no'])?$_REQUEST['page_no']:'1';
        $verify_password=isset($_REQUEST['verify_password'])?$_REQUEST['verify_password']:'';

		$result_resonse=[];
		$result_resonse['error']='no';
		$result_resonse['data']='';

		
		// if($verify_password!=coffeecms_pointback::$verify_password)
		// {
		// 	$result_resonse['error']='yes';
		// 	$result_resonse['data']='Verify password not valid.';
		// }

        if((int)$page_no > 0)
        {
            $page_no=(int)$page_no-1;
        }
        if((int)$page_no<=0)
        {
            $page_no=0;
        }

        $offset=(int)$page_no*15;

        
        self::setPrefix(false);
        self::connect();

        $queryStr='';
		$queryStr=" select a.*,b.date_added";
		$queryStr.=" from oc_order_product as a";
		$queryStr.=" left join oc_order as b ON a.order_id=b.order_id ";
		// $queryStr.=" left join oc_product_to_category as d ON a.product_id=d.product_id";
		// $queryStr.=" left join oc_category_description as c ON b.category_id=c.category_id";
		$queryStr.=" left join (SELECT product_id,count(*) as total FROM oc_order_product where order_id IN (select order_id from oc_order where order_status_id IN ('2','5','1','15')) group by product_id) as d ON a.product_id=d.product_id ";
		$queryStr.=" where a.order_id<>'' ";

		if(strlen($user_id) > 0)
		{
			$queryStr.=" AND a.product_id IN (select product_id from oc_product where model='".$user_id."' ) ";
		}
		if(strlen($category_id) > 0)
		{
			$queryStr.=" AND a.product_id IN (select product_id from oc_product_to_category where category_id='".$category_id."' ) ";
		}

		$queryStr.=" order by b.date_added limit ".$page_no.",".$limit;
		
        $result_resonse['data']=self::query($queryStr);

        return json_encode($result_resonse);
    }

    public function get_list_product()
    {
        $category_id=isset($_REQUEST['category_id'])?$_REQUEST['category_id']:'';
        $user_id=isset($_REQUEST['user_id'])?$_REQUEST['user_id']:'';
        $keywords=isset($_REQUEST['keywords'])?$_REQUEST['keywords']:'';
        $limit=isset($_REQUEST['limit'])?$_REQUEST['limit']:'12';
        $page_no=isset($_REQUEST['page_no'])?$_REQUEST['page_no']:'1';
        $order_by=isset($_REQUEST['order_by'])?$_REQUEST['order_by']:'date_added';
        $order_type=isset($_REQUEST['order_type'])?$_REQUEST['order_type']:'desc';

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
		$queryStr=" select a.product_id,a.image,a.price,b.category_id,c.name as category_name,prod.name as product_title,d.total_order";
		$queryStr.=" from oc_product as a";
		$queryStr.=" left join oc_product_description as prod ON a.product_id=prod.product_id";
		$queryStr.=" left join oc_product_to_category as b ON a.product_id=b.product_id";
		$queryStr.=" left join oc_category_description as c ON b.category_id=c.category_id";
		$queryStr.=" left join (SELECT product_id,count(*) as total_order FROM oc_order_product where order_id IN (select order_id from oc_order where order_status_id IN ('2','5','1','15')) group by product_id) as d ON a.product_id=d.product_id ";
		$queryStr.=" where a.status='1'";

		if(strlen($category_id) > 0)
		{
			$queryStr.=" AND b.category_id='".$category_id."' ";
		}

		if(strlen($keywords) > 0)
		{
			$queryStr.=" AND (b.name LIKE'%".$keywords."%' OR b.tag LIKE'%".$keywords."%') ";
		}

		if(strlen($user_id) > 0)
		{
			$queryStr.=" AND a.model='".$user_id."' ";
		}
		
		$queryStr.=" order by a.".$order_by." ".$order_type." limit ".$page_no.",".$limit;
		
        $loadData=self::query($queryStr);

        return $loadData;
    }

    public function insert_new_product()
    {
        $user_id=isset($_POST['user_id'])?$_POST['user_id']:'';
        $title=isset($_POST['title'])?trim(addslashes(strip_tags($_POST['title']))):'';
        $quantity=isset($_POST['quantity'])?trim($_POST['quantity']):'1000';
        $image=isset($_POST['image'])?addslashes(trim($_POST['image'])):'';
        $price=isset($_POST['price'])?addslashes(trim($_POST['price'])):'';
        $points=isset($_POST['points'])?addslashes(trim($_POST['points'])):'0';
        $descriptions=isset($_POST['descriptions'])?trim(addslashes(strip_tags($_POST['descriptions']))):'';
        $category_id=isset($_POST['category_id'])?trim($_POST['category_id']):'';
        $download_file=isset($_POST['download_file'])?trim($_POST['download_file']):'';
        $shipping=isset($_POST['shipping'])?trim($_POST['shipping']):'0';
        $tags=isset($_POST['tags'])?trim($_POST['tags']):'';

        $verify_password=isset($_POST['verify_password'])?trim($_POST['verify_password']):'';

		$result_resonse=[];
		$result_resonse['error']='no';
		$result_resonse['data']='';

		if($verify_password!=coffeecms_pointback::$verify_password)
		{
			$result_resonse['error']='yes';
			$result_resonse['data']='Verify password not valid.';
		}

		// $saveImagePath=DIR_IMAGE.randNumber(9).basename($image);
		$saveImagePath=DIR_IMAGE;

		// $saveDownloadFilePath=DIR_IMAGE.randNumber(9).basename($download_file);
		$saveDownloadFilePath=DIR_DOWNLOAD;

		if(isset($image[5]))
		{
			$output_filename = self::randAlpha(6).'_'.basename($image);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $image);
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$result = curl_exec($ch);
			curl_close($ch);
		  
			// the following lines write the contents to a file in the same directory (provided permissions etc)
			$fp = fopen($saveImagePath.$output_filename, 'w');
			fwrite($fp, $result);
			fclose($fp);	
			
			$saveImagePath=$output_filename;
		}

		if(isset($download_file[5]))
		{
			$output_filename = self::randAlpha(6).'_'.basename($download_file);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $download_file);
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$result = curl_exec($ch);
			curl_close($ch);
		  
			// the following lines write the contents to a file in the same directory (provided permissions etc)
			$fp = fopen($saveDownloadFilePath.$output_filename, 'w');
			fwrite($fp, $result);
			fclose($fp);			
			$saveDownloadFilePath=$output_filename;
		}

        self::setPrefix(false);
        self::connect();

        $queryStr='';
		$queryStr.=" insert into oc_product(model,quantity,sku,upc,ean,jan,isbn,mpn,";
		$queryStr.="stock_status_id,image,location,manufacturer_id,shipping,price,points,tax_class_id,date_available,";
		$queryStr.="status,date_added,date_modified)";
		$queryStr.="VALUES('".$user_id."','9000','','','','','','',";
		$queryStr.="'7','".$saveImagePath."','',manufacturer_id,'".$shipping."','".$price."','".$points."',tax_class_id,'".date('Y-m-d')."',";
		$queryStr.="'1','".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."');";

        self::nonquery($queryStr);

		$product_id=self::$insert_id;

		// print_r($product_id);die();

		$result_resonse['data']=$product_id;

        $queryStr='';
		$queryStr.=" insert into oc_product_description(product_id,language_id,name,description,tag,meta_title,meta_description,meta_keyword)";
		$queryStr.="VALUES('".$product_id."','1','".$title."','".$descriptions."','".$tags."','".$title."','".$descriptions."','');";

		self::nonquery($queryStr);

		$queryStr='';
		$queryStr.=" insert into oc_product_image(product_id,image,sort_order)";
		$queryStr.="VALUES('".$product_id."','".$saveImagePath."','0');";

		self::nonquery($queryStr);

		$queryStr='';
		$queryStr.=" insert into oc_product_to_category(product_id,category_id)";
		$queryStr.="VALUES('".$product_id."','".$category_id."');";

		self::nonquery($queryStr);

		if(isset($download_file[5]))
		{
			$queryStr='';
			$queryStr.=" insert into oc_download(filename,mask,date_added)";
			$queryStr.="VALUES('".$saveDownloadFilePath."','".basename($download_file)."','".date('Y-m-d H:i:s')."');";

			self::nonquery($queryStr);

			$download_id=self::$insert_id;

			$queryStr='';
			$queryStr.=" insert into oc_download_description(download_id,language_id,name)";
			$queryStr.="VALUES('".$download_id."','1','".basename($download_file)."');";

			self::nonquery($queryStr);

			$queryStr='';
			$queryStr.=" insert into oc_product_to_download(product_id,download_id)";
			$queryStr.="VALUES('".$product_id."','".$download_id."');";

			self::nonquery($queryStr);

		}

        echo json_encode($result_resonse);die();
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

	public function randNumber($len = 10)
	{
		$str = '012010123456789234560123450123456789234560123456789789345012345601234567892345601234567897893450123456678978934501234567896789';
	
		$str = substr(str_shuffle($str), 0, $len);
	
		return $str;
	
	}
	
	public function randAlpha($len = 10)
	{
		$str = 'abcdefghijklmnopfghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUqrstufghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	
		$str = substr(str_shuffle($str), 0, $len);
	
		return $str;
	
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

		self::$insert_id=$this->dbConnect->insert_id;

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