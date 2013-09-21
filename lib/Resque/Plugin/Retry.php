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
	 * @param   Object   	$instance
	 */
	public function onFailure($exception, $job, $instance) {
		if ($this->retryCriteriaValid($exception, $job, $instance)) {
			$this->tryAgain($exception, $job, $instance);
		} else {
			$this->cleanRetryKey($job, $instance);
		}

	}

	/**
	 * Hook into before the job is performed
	 *
	 * Sets up the tracking of the of the amount of attempts trying to perform this job
	 * 
	 * @param 	Resque_Job 	$job
	 * @param   Object   	$instance
	 */
	public function beforePeform($job, $instance) {
		// Keep track of the number of retry attempts
		$retryKey = $this->redisRetryKey($job, $instance);

		Resque::redis()->setnx($retryKey, -1); // set to -1 if key doesn't exist
		$instance->retryAttempt = Resque::redis()->incr($retryKey);
	}

	/**
	 * Hook into the job having been performed
	 *
	 * Cleans up any data we've tracked for retrying now that the job has been successfully 
	 * performed.
	 * 
	 * @param 	Resque_Job 	$job
	 * @param   Object   	$instance
	 */
	public function afterPerform($job, $instance) {
		$this->cleanRetryKey($job, $instance);
	}

	public function tryAgain() {}

	/**
	 * Clean up the retry attempts information from Redis
	 * 
	 * @param 	Resque_Job 	$job
	 * @param   Object   	$instance
	 */
	public function cleanRetryKey($job, $instance) {
		$retryKey = $this->redisRetryKey($job, $instance);

		Resque::redis()->del($retryKey);
	}

	public function redisRetryKey($job, $instance) {}
	public function retryCriteriaValid() {}

	
}