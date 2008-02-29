<?php

// Date and Time

class BB_Locale {
	var $weekday;
	var $weekday_initial;
	var $weekday_abbrev;

	var $month;
	var $month_abbrev;

	var $meridiem;
	var $number_format;
	var $datetime_formatstring;

	var $text_direction = '';
	var $locale_vars = array('text_direction');

	function init() {
		// The Weekdays
		$this->weekday[0] = __('Sunday');
		$this->weekday[1] = __('Monday');
		$this->weekday[2] = __('Tuesday');
		$this->weekday[3] = __('Wednesday');
		$this->weekday[4] = __('Thursday');
		$this->weekday[5] = __('Friday');
		$this->weekday[6] = __('Saturday');

		// The first letter of each day.  The _%day%_initial suffix is a hack to make
		// sure the day initials are unique.
		$this->weekday_initial[__('Sunday')]    = __('S_Sunday_initial');
		$this->weekday_initial[__('Monday')]    = __('M_Monday_initial');
		$this->weekday_initial[__('Tuesday')]   = __('T_Tuesday_initial');
		$this->weekday_initial[__('Wednesday')] = __('W_Wednesday_initial');
		$this->weekday_initial[__('Thursday')]  = __('T_Thursday_initial');
		$this->weekday_initial[__('Friday')]    = __('F_Friday_initial');
		$this->weekday_initial[__('Saturday')]  = __('S_Saturday_initial');

		foreach ($this->weekday_initial as $weekday_ => $weekday_initial_) {
			$this->weekday_initial[$weekday_] = preg_replace('/_.+_initial$/', '', $weekday_initial_);
		}

		// Abbreviations for each day.
		$this->weekday_abbrev[__('Sunday')]    = __('Sun');
		$this->weekday_abbrev[__('Monday')]    = __('Mon');
		$this->weekday_abbrev[__('Tuesday')]   = __('Tue');
		$this->weekday_abbrev[__('Wednesday')] = __('Wed');
		$this->weekday_abbrev[__('Thursday')]  = __('Thu');
		$this->weekday_abbrev[__('Friday')]    = __('Fri');
		$this->weekday_abbrev[__('Saturday')]  = __('Sat');

		// The Months
		$this->month['01'] = __('January');
		$this->month['02'] = __('February');
		$this->month['03'] = __('March');
		$this->month['04'] = __('April');
		$this->month['05'] = __('May');
		$this->month['06'] = __('June');
		$this->month['07'] = __('July');
		$this->month['08'] = __('August');
		$this->month['09'] = __('September');
		$this->month['10'] = __('October');
		$this->month['11'] = __('November');
		$this->month['12'] = __('December');

		// Abbreviations for each month. Uses the same hack as above to get around the
		// 'May' duplication.
		$this->month_abbrev[__('January')] = __('Jan_January_abbreviation');
		$this->month_abbrev[__('February')] = __('Feb_February_abbreviation');
		$this->month_abbrev[__('March')] = __('Mar_March_abbreviation');
		$this->month_abbrev[__('April')] = __('Apr_April_abbreviation');
		$this->month_abbrev[__('May')] = __('May_May_abbreviation');
		$this->month_abbrev[__('June')] = __('Jun_June_abbreviation');
		$this->month_abbrev[__('July')] = __('Jul_July_abbreviation');
		$this->month_abbrev[__('August')] = __('Aug_August_abbreviation');
		$this->month_abbrev[__('September')] = __('Sep_September_abbreviation');
		$this->month_abbrev[__('October')] = __('Oct_October_abbreviation');
		$this->month_abbrev[__('November')] = __('Nov_November_abbreviation');
		$this->month_abbrev[__('December')] = __('Dec_December_abbreviation');

		foreach ($this->month_abbrev as $month_ => $month_abbrev_) {
			$this->month_abbrev[$month_] = preg_replace('/_.+_abbreviation$/', '', $month_abbrev_);
		}

		// The Meridiems
		$this->meridiem['am'] = __('am');
		$this->meridiem['pm'] = __('pm');
		$this->meridiem['AM'] = __('AM');
		$this->meridiem['PM'] = __('PM');

		// Numbers formatting
		// See http://php.net/number_format

		$trans = __('number_format_decimals'); 
		$this->number_format['decimals'] = ('number_format_decimals' == $trans) ? 0 : $trans;
		
		$trans = __('number_format_decimal_point');
		$this->number_format['decimal_point'] = ('number_format_decimal_point' == $trans) ? '.' : $trans;

		$trans = __('number_format_thousands_sep');	
		$this->number_format['thousands_sep'] = ('number_format_thousands_sep' == $trans) ? ',' : $trans; 
		
		// Date/Time formatting
		
		$this->datetime_formatstring['datetime'] = __('F j, Y - h:i A');
		$this->datetime_formatstring['date'] = __('F j, Y');
		$this->datetime_formatstring['time'] = __('h:i A');
		
		$this->_load_locale_data();
	}

	function _load_locale_data() {
		$locale = get_locale();
		$locale_file = BBPATH . "bb-includes/languages/$locale.php";
		if ( !file_exists($locale_file) )
			return;

		include($locale_file);

		foreach ( $this->locale_vars as $var ) {
			$this->$var = $$var;	
		}
	}

	function get_weekday($weekday_number) {
		return $this->weekday[$weekday_number];
	}

	function get_weekday_initial($weekday_name) {
		return $this->weekday_initial[$weekday_name];
	}

	function get_weekday_abbrev($weekday_name) {
		return $this->weekday_abbrev[$weekday_name];
	}

	function get_month($month_number) {
		return $this->month[zeroise($month_number, 2)];
	}

	function get_month_initial($month_name) {
		return $this->month_initial[$month_name];
	}

	function get_month_abbrev($month_name) {
		return $this->month_abbrev[$month_name];
	}

	function get_meridiem($meridiem) {
		return $this->meridiem[$meridiem];
	}

	function get_datetime_formatstring($type = 'datetime') {
		return $this->datetime_formatstring[$type];
	}

	// Global variables are deprecated. For backwards compatibility only.
	function register_globals() {
		$GLOBALS['weekday']         = $this->weekday;
		$GLOBALS['weekday_initial'] = $this->weekday_initial;
		$GLOBALS['weekday_abbrev']  = $this->weekday_abbrev;
		$GLOBALS['month']           = $this->month;
		$GLOBALS['month_abbrev']    = $this->month_abbrev;
	}

	function BB_Locale() {
		$this->init();
		$this->register_globals();
	}
}

function bb_gmdate_i18n( $dateformatstring, $unixtimestamp ) {
	global $bb_locale;
	$i = $unixtimestamp;
	if ( !empty($bb_locale->month) && !empty($bb_locale->weekday) ) {
		$datemonth = $bb_locale->get_month( gmdate('m', $i) );
		$datemonth_abbrev = $bb_locale->get_month_abbrev( $datemonth );
		$dateweekday = $bb_locale->get_weekday( gmdate('w', $i) );
		$dateweekday_abbrev = $bb_locale->get_weekday_abbrev( $dateweekday );
		$datemeridiem = $bb_locale->get_meridiem( gmdate('a', $i) );
		$datemeridiem_capital = $bb_locale->get_meridiem( gmdate('A', $i) );
		$dateformatstring = ' ' . $dateformatstring;
		$dateformatstring = preg_replace("/([^\\\])D/", "\\1" . backslashit( $dateweekday_abbrev ), $dateformatstring);
		$dateformatstring = preg_replace("/([^\\\])F/", "\\1" . backslashit( $datemonth ), $dateformatstring);
		$dateformatstring = preg_replace("/([^\\\])l/", "\\1" . backslashit( $dateweekday ), $dateformatstring);
		$dateformatstring = preg_replace("/([^\\\])M/", "\\1" . backslashit( $datemonth_abbrev ), $dateformatstring);
		$dateformatstring = preg_replace("/([^\\\])a/", "\\1" . backslashit( $datemeridiem ), $dateformatstring);
		$dateformatstring = preg_replace("/([^\\\])A/", "\\1" . backslashit( $datemeridiem_capital ), $dateformatstring);

		$dateformatstring = substr($dateformatstring, 1, strlen($dateformatstring)-1);
	}
	$j = @gmdate($dateformatstring, $i);
	return $j;
}

function bb_get_datetime_formatstring_i18n( $type = 'datetime' ) {
	$formatstring = bb_get_option( $type . '_format' );
	if ( empty($formatstring) ) {
		global $bb_locale;
		$formatstring = $bb_locale->get_datetime_formatstring( $type );
	}
	return $formatstring;
}

function bb_datetime_format_i18n( $unixtimestamp, $type = 'datetime', $formatstring = '' ) {
	if ( empty($formatstring) ) {
		$formatstring = bb_get_datetime_formatstring_i18n( $type );
	}
	return bb_gmdate_i18n( $formatstring, bb_offset_time( $unixtimestamp ) );
}

function bb_number_format_i18n($number, $decimals = null) {
	global $bb_locale;
	// let the user override the precision only
	$decimals = is_null($decimals) ? $bb_locale->number_format['decimals'] : intval($decimals);

	return number_format($number, $decimals, $bb_locale->number_format['decimal_point'], $bb_locale->number_format['thousands_sep']);
}

?>
