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
class Animal
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

		if(!empty(file_get_contents("php://input"))){

				$raw=(file_get_contents("php://input"));
				//some test data for filling

				if($food=json_decode($raw,true)){

				}else{
					header("HTTP/1.0 400 Error");
					echo "invalid JSON format";
				}


				if($food!=""){
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
							//if post, then all data will be submitted at onst filling
								$recordUUI=sha1(microtime().$raw. mt_rand());
								$time=time();

								$args=array('load'=>$raw,'time'=>$time,'count'=>0);

								//saving data into redis, using multi to save roundtrips

								$redis->multi()
								->hMset($recordUUI,$args)
								//->lPush('que', $recordUUI)
								->zAdd('sQue', $time, $recordUUI)
								->incrBy('postCalls',1)
								->incrBy('requestCount',1)
								->incrBy('loadVolume', mb_strlen($raw, '8bit'))
								->exec();

							}

						if(strtolower($food['endpoint']['method'])=="get"){
							//for GET compatibility sake break load into multiple calls
							//$recordData[]='que';
							$recordDataForSortedSet[]='sQue';

								foreach($food['data'] as $index=>$data){

									$time=time();
									$recordUUI=sha1(microtime().$raw. mt_rand());

									$recordData[]=$recordUUI;
									$recordDataForSortedSet[]=$time;
									$recordDataForSortedSet[]=$recordUUI;

									$breaking['endpoint']=$food['endpoint'];
									$breaking['data']=$data;
									$args=array('load'=>json_encode($breaking),'time'=>$time,'count'=>0);
									$redis->hMset($recordUUI,$args);
									$countCalls++;
								}


							//saving data into redis, didn't find method to use multi for that
							//call_user_func_array(array($redis, 'lpush'), $recordData);

							call_user_func_array(array($redis, 'zAdd'),$recordDataForSortedSet); //sorted list for process

							$redis->multi()
								->incrBy('requestCount',1)
								->incrBy('loadVolume', mb_strlen($raw, '8bit'))
								->incrBy('getCalls',$countCalls)
								->exec();

							//print_r($err = $redis->getLastError());
							//print_r($ind);

						}



						echo 'ok';
						$redis->publish('queue', '1');


					}else{
						header("HTTP/1.0 400 Error");
						echo 'Redis is offline';
					}


				}else{
					header("HTTP/1.0 400 Error");
					echo $errorMessage;
				}
			}
		}else{
			header("HTTP/1.0 400 Error");
			echo "No Payload";
		}
	}


}

$obj = new Animal();

$obj->feed();

$data = xhprof_disable();

$XHPROF_ROOT = '/usr/share/php';
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";
$xhprof_runs = new XHProfRuns_Default();
// Save the run under a namespace "xhprof".
$run_id = $xhprof_runs->save_run($data, "xhprof");