<?php

class ConceptNumericRange
{
	public $absolute_high  =  null;
	public $critical_high  =  null;
	public $normal_high    =  null;

	public $absolute_low   =  null;
	public $critical_low   =  null;
	public $normal_low     =  null;

	public $precise        =  null;
	public $units          =  null;

	public function __construct($absolute_high, $critical_high, $normal_high,
			$absolute_low, $critical_low, $normal_low,
			$units, $precise)
	{
		$this->absolute_high = $absolute_high;
		$this->critical_high = $critical_high;
		$this->normal_high = $normal_high;
		$this->absolute_low = $absolute_low;
		$this->critical_low = $critical_low;
		$this->normal_low = $normal_low;
		$this->units = $units;
		$this->precise = $precise;
	}
}

?>