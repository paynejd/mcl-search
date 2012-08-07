<?php

class ConceptSearchResults
{
	/**
	 * ConceptSearch object
	 */
	public $cs = null;

	/**
	 * ConceptCollection object
	 */
	public $cc = null;

	/**
	 * Start time of search operation in microseconds.
	 */
	public $start_time = 0;

	/**
	 * Stop time of search operation in microseconds.
	 */
	public $stop_time = 0;


	/**
	 * Constructor
	 */
	public function __construct( $cs  ,  $cc  ) {
		$this->cs  =  $cs  ;
		$this->cc  =  $cc  ;
	}

	/**
	 * Returns the number of visible concepts contained in the ConceptCollection.
	 */
	public function getVisibleCount() {
		if ($this->cc) {
			return $this->cc->getVisibleCount();
		}
		return 0;
	}

	/**
	 * Get search execution time in seconds.
	 */
	public function getSearchTime() {
		return ($this->stop_time - $this->start_time);
	}
}

?>