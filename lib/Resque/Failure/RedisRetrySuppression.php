<?php

namespace Resque\Failure;
use \Resque_Failure_Redis;
use \Resque;

class RedisRetrySuppression extends Resque_Failure_Redis {
	/**
	 * Initialize a failed job class and save it (where appropriate).
	 *
	 * @param object $job Job that failed.
	 * @param object $exception Instance of the exception that was thrown by the failed job.
	 * @param object $worker Instance of Resque_Worker that received the job.
	 * @param string $queue The name of the queue the job was fetched from.
	 */
	public function __construct($job, $exception, $worker, $queue) {
		if (!property_exists($job, 'retrying') or !$job->retrying or $job->retryDelay <= 0) {
			return parent::__construct($job, $exception, $worker, $queue);
		}
		
		$retryDelay = $job->retryDelay;

		$data = $this->getData($job, $exception, $worker, $queue);
		$data->retry_delay = $retryDelay;
		$data->retried_at = strftime('%a %b %d %H:%M:%S %Z %Y', $job->retryingAt);

		Resque::redis()->rpush('failed', json_encode($data));
		
	}

	// /**
	//  * Return the redis key used to log the failure information
	//  * 
	//  * @param 	Resque_Job 	$job
	//  * @param 	string
	//  */
	// protected function redisRetryKey($job) {
	// 	return 'failed-retrying:'.$job->retryKey;
	// }

	// /**
	//  * Clean up the retry information from Redis
	//  * 
	//  * @param 	Resque_Job 	$job
	//  */
	// protected function clearRetryKey($job) {
	// 	$retryKey = $this->redisRetryKey($job);

	// 	Resque::redis()->del($retryKey);
	// }
}