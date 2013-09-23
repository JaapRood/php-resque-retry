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

	/**
	 * Return the redis key used to track retries
	 * 
	 * @param 	Resque_Job 	$job
	 * @param 	string
	 */
	protected function redisRetryKey($job) {
		return 'resque-retry:' . (string) $job;
	}

	/**
	 * Test whether the retry criteria are valid
	 *
	 * @param  	Exception 	$exception
	 * @param 	Resque_Job 	$job
	 * @return  boolean
	 */
	protected function retryCriteriaValid($exception, $job) {
		return true; // retry everything for now
	}

	
}