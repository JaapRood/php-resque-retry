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
	public function beforePeform($job) {
		// Keep track of the number of retry attempts
		$retryKey = $this->redisRetryKey($job);

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
	 */
	public function afterPerform($job) {
		$this->cleanRetryKey($job);
	}

	protected function tryAgain() {}

	/**
	 * Clean up the retry attempts information from Redis
	 * 
	 * @param 	Resque_Job 	$job
	 */
	protected function cleanRetryKey($job) {
		$retryKey = $this->redisRetryKey($job);

		Resque::redis()->del($retryKey);
	}

	public function redisRetryKey($job, $instance) {}
	public function retryCriteriaValid() {}

	
}