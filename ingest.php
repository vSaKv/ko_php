<?php
xhprof_enable();
set_time_limit(600);
/**
 * Author: Sergei Krutov
 * Date: 10/14/15
 * For: kochava test project.
 * Version: 1
 */

/*
isset($_POST['mandrill_events'])
$model->file=file_get_contents("php://input");

http://sample_domain_endpoint.com/data?key=Phyllobates&value=Terribilis&foo=
 */
require_once 'Config.php';

class Animal extends Config
{
	//public $App;
	private $params;

	function __construct() {
	/*
	 * Initializing Parameters for ingest
	 */
		$this->params=array(
			'allowedMethods'=>array('post','get'),
		);
	}


	public function feed()
	{
	/*
	 *
	 *
	 *
	 */
		$food="";
		$errorMessage="";

		//if(!empty(file_get_contents("php://input"))){

			try{
				$raw=(file_get_contents("php://input"));
				//some test data for filling
				$raw='{
					  "endpoint":{
						"method":"POST",
						"url":"http://test.com/tst.php?key={key}&value={value}&foo={bar}"
					  },
					  "data":[
						{
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						},
						 {
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						},
						 {
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						},
						 {
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates6",
						  "value":"Terribilis1"
						},
						 {
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						}, {
						  "key":"Azureus",
						  "value":"Dendrobates"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						},
						{
						  "key":"Phyllobates",
						  "value":"Terribilis"
						},
						{
						  "key":"Phyllobates1",
						  "value":"Terribilis1"
						}

					  ]
					}';

				$food=json_decode($raw,true);

				// Validation block, to check if payLoad have endpoint, correct url provided and allowed method
				if(isset($food['endpoint'])){
					if(isset($food['data'])){

						if(!in_array(strtolower($food['endpoint']['method']),$this->params['allowedMethods'])){
							$errorMessage="Wrong Method (".$food['endpoint']['method'].")";
						}

						if (!preg_match("/\b(?:(?:https?):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$food['endpoint']['url'])) {
							$errorMessage= "URL is invalid";
						}

					}else{
						$errorMessage="Missing Load";
					}

				}else{
					$errorMessage="Missing Endpoint";
				}


				if($errorMessage==""){
					//print_r($food);
					$redis = new Redis();

					if($redis->connect('127.0.0.1', 6379)){

						$countCalls=0;
						if(strtolower($food['endpoint']['method'])=='post'){
							//if post, then all data will be submitted at once

							for($i=0;$i<1000;$i++){ //todo temporary to test filling
								$recordUUI=sha1(microtime().$raw);
								$time=time();

								$args=array('load'=>$raw,'time'=>$time,'count'=>0);

								//saving data into redis, using multi to save roundtrips
								$redis->multi()
								->hMset($recordUUI,$args)
								->lPush('que', $recordUUI)
								->zAdd('sQue', $time, $recordUUI)
								->incrBy('postCalls',1)
								->incrBy('requestCount',1)
								->incrBy('loadVolume', mb_strlen($raw, '8bit'))
								->exec();
								}
							}

						if(strtolower($food['endpoint']['method'])=="get"){
							//for GET compatibility sake break load into multiple calls
							$recordData[]='que';
							$recordDataForSortedSet[]='sQue';
							$time=time();

							for($i=0;$i<50;$i++){ //todo temp for test filling

								foreach($food['data'] as $index=>$data){

									$recordUUI=sha1(microtime().$raw);
									$recordData[]=$recordUUI;
									$recordDataForSortedSet[]=$time;
									$recordDataForSortedSet[]=$recordUUI;

									$breaking['endpoint']=$food['endpoint'];
									$breaking['data']=$data;
									$args=array('load'=>json_encode($breaking),'time'=>$time,'count'=>0);
									$redis->hMset($recordUUI,$args);
									$countCalls++;
								}
							}


							//saving data into redis, didn't find method to use multi for that
							call_user_func_array(array($redis, 'lpush'), $recordData);
							call_user_func_array(array($redis, 'zAdd'),$recordDataForSortedSet);

							$redis->multi()
								->incrBy('requestCount',1)
								->incrBy('loadVolume', mb_strlen($raw, '8bit'))
								->incrBy('getCalls',$countCalls)
								->exec();

							//print_r($err = $redis->getLastError());
							//print_r($ind);

						}


						$redis->publish('queue', '1');


					}else{
						header("HTTP/1.0 400 Error");
						echo 'Redis is offline';
					}


				}else{
					header("HTTP/1.0 400 Error");
					echo $errorMessage;
				}

			} catch (Exception $e) {
				$errorMessage='Uncaught Exception: '.json_encode($e);
				header("HTTP/1.0 400 Error");
				echo $errorMessage;
			}
	}


}

$obj = new Animal();


echo 'sss';
$obj->feed();


for($i=0;$i<50;$i++){
	$obj->feed();
	if($i % 50==0){
		$redis = new Redis();

		if($redis->connect('127.0.0.1', 6379)){
			$redis->publish('queue', '1');
		}

	}

}

$data = xhprof_disable();

$XHPROF_ROOT = '/usr/share/php';
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";
$xhprof_runs = new XHProfRuns_Default();
// Save the run under a namespace "xhprof".
$run_id = $xhprof_runs->save_run($data, "xhprof");