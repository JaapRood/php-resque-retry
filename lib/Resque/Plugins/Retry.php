<?php

namespace Resque\Plugins;
use \Resque;
use \ResqueScheduler;

class Retry {

	/**
	 * Hook into the job failing
	 *
	 * Will attempt to retry the job if all retry criterias pass
	 * 
	 * @param  	Exception 	$exception
	 * @param 	Resque_Job 	$job
	 */
	public function onFailure($exception, $job) {
		if ($this->retryCriteriaValid($exception, $job)) {
			$this->tryAgain($exception, $job);
			$job->retryKey = $this->redisRetryKey($job);
		} else {
			$this->cleanRetryKey($job);
		}

	}

	/**
	 * Hook into before the job is performed
	 *
	 * Sets up the tracking of the of the amount of attempts trying to perform this job
	 * 
	 * @param 	Resque_Job 	$job
	 */
	public function beforePerform($job) {
		// Keep track of the number of retry attempts
		$retryKey = $this->redisRetryKey($job);

		Resque::redis()->setnx($retryKey, -1); // set to -1 if key doesn't exist
		$job->retryAttempt = Resque::redis()->incr($retryKey);

		// Set basic info on the job
		$job->retryKey = $this->redisRetryKey($job);
		$job->failureBackend = '\\Resque\\Failure\\RedisRetrySuppression';
	}

	/**
	 * Hook into the job having been performed
	 *
	 * Cleans up any data we've tracked for retrying now that the job has been successfully 
	 * performed.
	 * 
	 * @param 	Resque_Job 	$job
	 */
	public function afterPerform($job) {
		$this->cleanRetryKey($job);
	}


	/**
	 * Retry the job
	 *
	 * @param 	Exception 	$exception 	the exception that caused the job to fail
	 * @param  	Resque_Job	$job 		the job that failed and should be retried
	 */
	protected function tryAgain($exception, $job) {
		$retryDelay = $this->retryDelay($job);
		
		$queue = $job->queue;
		$class = $job->getClass();
		$arguments = $job->getArguments();

		if ($retryDelay <= 0) {
			Resque::enqueue($queue, $class, $arguments);
		} else {
			ResqueScheduler::enqueueIn($retryDelay, $queue, $class, $arguments);
		}

		$job->retrying = true;
		$job->retryDelay = $retryDelay;
	}

	/**
	 * Clean up the retry attempts information from Redis
	 * 
	 * @param 	Resque_Job 	$job
	 */
	protected function cleanRetryKey($job) {
		$retryKey = $this->redisRetryKey($job);

		Resque::redis()->del($retryKey);
	}

	/**
	 * Return the redis key used to track retries
	 * 
	 * @param 	Resque_Job 	$job
	 * @param 	string
	 */
	protected function redisRetryKey($job) {
		$name = array(
			'Job{' . $job->queue .'}'
		);
		$name[] = $job->payload['class'];
		if(!empty($job->payload['args'])) {
			$name[] = md5(json_encode($job->payload['args']));
		}

		return 'retry:' . '(' . implode(' | ', $name) . ')';
	}

	/**
	 * Test whether the retry criteria are valid
	 *
	 * @param  	Exception 	$exception
	 * @param 	Resque_Job 	$job
	 * @return  boolean
	 */
	protected function retryCriteriaValid($exception, $job) {
		if ($this->retryLimitReached($job)) return false;


		return true; // retry everything for now
	}

	protected function retryLimitReached($job) {
		$retryLimit = $this->retryLimit($job);

		if ($retryLimit === 0) {
			return true;
		} elseif ($retryLimit > 0) {
			return ($job->retryAttempt >= $retryLimit);
		} else {
			return false;
		}
	}

	/**
	 * Get the retry delay from the job, defaults to 0
	 *
	 * @param 	Resque_Job 	$job
	 * @return  int 		retry delay in seconds
	 */
	protected function retryLimit($job) {
		return $this->getInstanceProperty($job, 'retryLimit', 1);
	}

	/**
	 * Get the retry delay from the job, defaults to 0
	 *
	 * @param 	Resque_Job 	$job
	 * @return  int 		retry delay in seconds
	 */
	protected function retryDelay($job) {
		return $this->getInstanceProperty($job, 'retryDelay', 0);
	}

	/**
	 * Get a property of the job instance if it exists, otherwise
	 * the default value for this property. Return null for a property
	 * that has no default set
	 */
	protected function getInstanceProperty($job, $property, $default = null) {
		$instance = $job->getInstance();

		if (method_exists($instance, $property)) {
			return call_user_func_array(array($instance, $property), $job);
		}

		if (property_exists($instance, $property)) {
			return $instance->{$property};
		}

		return $default;
	}
	
}