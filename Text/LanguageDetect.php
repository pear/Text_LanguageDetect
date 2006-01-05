<?php

/**
 * Detects the language of a given piece of text.
 *
 * Attempts to detect the language of a sample of text by correlating ranked
 * 3-gram frequencies to a table of 3-gram frequencies of known languages.
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    LanguageDetect
 * @author     Nicholas Pisarro <infinityminusnine+pear@gmail.com>
 * @copyright  2005 Nicholas Pisarro
 * @license    http://www.debian.org/misc/bsd.license BSD
 * @version    CVS: $Id$
 */

require_once 'PEAR.php';

/**
 * Language detection class
 *
 * Requires the langauge model database (lang.dat) that should have
 * accompanied this class definition in order to be instantiated.
 *
 * Example usage:
 *
 * <code>
 * require_once 'Text/LanguageDetect.php';
 *
 * $l = new Text_LanguageDetect;
 *
 * $stdin = fopen('php://stdin', 'r');
 *
 * echo "Supported languages:\n";
 *
 * $langs = $l->getLanguages();
 * if (PEAR::isError($langs)) {
 *     die($langs->getMessage());
 * }
 *
 * sort($langs);
 * echo join(', ', $langs);
 *
 * while ($line = fgets($stdin)) {
 *     print_r($l->detect($line, 4));
 * }
 * </code>
 *
 * @category   Text
 * @package    LanguageDetect
 * @author     Nicholas Pisarro <infinityminusnine+pear@gmail.com>
 * @copyright  2005 Nicholas Pisarro
 * @license    http://www.debian.org/misc/bsd.license BSD
 * @version    Release: @package_version@
 * @todo       allow users to generate their own language models
 */
 
class Text_LanguageDetect
{
    /** 
     * The filename that stores the trigram data for the detector
     * 
     * @var      string
     * @access   private
     */
    var $_db_filename = 'lang.dat';

    /**
     * The data directory
     *
     * @var      string
     * @access   private
     */
    var $_data_dir = '@data_dir@';

    /**
     * The size of the trigram data arrays
     * 
     * @var      int
     * @access   private
     */
    var $_threshold = 300;

    /**
     * The trigram data for comparison
     * 
     * Will be loaded on start from $this->_db_filename
     *
     * May be set to a PEAR_Error object if there is an error during its 
     * initialization
     *
     * @var      array
     * @access   private
     */
    var $_lang_db = array();

    /**
     * Whether or not to simulate perl's Language::Guess exactly
     * 
     * @access  private
     * @var     bool
     * @see     setPerlCompatible()
     */
    var $_perl_compatible = false;

    /**
     * the maximum possible score.
     *
     * needed for score normalization. Different depending on the
     * perl compatibility setting
     *
     * @access  private
     * @var     int
     * @see     setPerlCompatible()
     */
    var $_max_score = 0;

    /**
     * Constructor
     *
     * Will attempt to load the language database. If it fails, you will get
     * a PEAR_Error object returned when you try to use detect()
     *
     */
    function Text_LanguageDetect()
    {
        $this->_lang_db = $this->_readdb($this->_get_db_loc());
    }

    /**
     * Returns the path to the location of the database
     *
     * @access    private
     * @return    string    expected path to the language model database
     */
    function _get_db_loc()
    {
        // checks if this has been installed properly
        if ($this->_data_dir != '@' . 'data_dir' . '@') {
            // if the data dir was set by the PEAR installer, use that
            return $this->_data_dir . '/Text_LanguageDetect/' . $this->_db_filename;
        } else {
            // try the local working directory if otherwise
            return '../data/' . $this->_db_filename;
        }
    }

    /**
     * Loads the language trigram database from filename
     *
     * Trigram datbase should be a serialize()'d array
     * 
     * @access    private
     * @param     string      $fname   the filename where the data is stored
     * @return    array                the language model data
     * @throws    PEAR_Error
     */
    function _readdb($fname)
    {
        // input check
        if (!file_exists($fname)) {
            return PEAR::raiseError("Language database does not exist.");
        } elseif (!is_readable($fname)) {
            return PEAR::raiseError("Language database is not readable.");
        }

        if (function_exists('file_get_contents')) {
            return unserialize(file_get_contents($fname));
        } else {
            // if you don't have file_get_contents(), 
            // then this is the next fastest way
            ob_start();
            readfile($fname);
            $contents = ob_get_contents();
            ob_end_clean();
            return unserialize($contents);
        }
    }

    /**
     * Checks if this object is ready to detect languages
     * 
     * @access   private
     * @param    mixed   &$err  error object to be returned by reference, if any
     * @return   bool           true if no errors
     */
    function _setup_ok(&$err)
    {

        if (PEAR::isError($this->_lang_db)) {
            // if there was an error from when the language database was loaded
            // then return that error
            $err = $this->_lang_db;
            return false;

        } elseif (!is_array($this->_lang_db)) {
            $err = PEAR::raiseError('Language database is not an array.');
            return false;

        } elseif (!count($this->_lang_db)) {
            $err =  PEAR::raiseError('Language database has no elements.');
            return false;

        } else {
            return true;
        }
    }

    /**
     * Omits languages
     *
     * Pass this function the name of or an array of names of 
     * languages that you don't want considered
     *
     * If you're only expecting a limited set of languages, this can greatly 
     * speed up processing
     *
     * @access   public
     * @param    mixed  $omit_list      language name or array of names to omit
     * @param    bool   $include_only   if true will include (rather than 
     *                                  exclude) only those in the list
     * @return   int                    number of languages successfully deleted
     * @throws   PEAR_Error
     */
    function omitLanguages($omit_list, $include_only = false)
    {

        // setup check
        if (!$this->_setup_ok($err)) {
            return $err;
        }

        $deleted = 0;

        // deleting the given languages
        if (!$include_only) {
            if (!is_array($omit_list)) {
                $omit_list = strtolower($omit_list); // case desensitize
                if (isset($this->_lang_db[$omit_list])) {
                    unset($this->_lang_db[$omit_list]);
                    $deleted++;
                }
            } else {
                foreach ($omit_list as $omit_lang) {
                    if (isset($this->_lang_db[$omit_lang])) {
                        unset($this->_lang_db[$omit_lang]);
                        $deleted++;
                    } 
                }
            }

        // deleting all except the given languages
        } else {
            if (!is_array($omit_list)) {
                $omit_list = array($omit_list);
            }

            // case desensitize
            foreach ($omit_list as $key => $omit_lang) {
                $omit_list[$key] = strtolower($omit_lang);
            }

            foreach (array_keys($this->_lang_db) as $lang) {
                if (!in_array($lang, $omit_list)) {
                    unset($this->_lang_db[$lang]);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }


    /**
     * Returns the number of languages that this object can detect
     *
     * @access public
     * @return int            the number of languages
     * @throws PEAR_Error
     */
    function getLanguageCount()
    {
        if (!$this->_setup_ok($err)) {
            return $err;
        } else {
            return count($this->_lang_db);
        }
    }

    /**
     * Returns true if a given language exists
     *
     * If passed an array of names, will return true only if all exist
     *
     * @access    public
     * @param     mixed       $lang    language name or array of language names
     * @return    bool                 true if language model exists
     * @throws    PEAR_Error
     */
    function languageExists($lang)
    {
        if (!$this->_setup_ok($err)) {
            return $err;
        } else {
            // string
            if (is_string($lang)) {
                return isset($this->_lang_db[strtolower($lang)]);

            // array
            } elseif (is_array($lang)) {
                foreach ($lang as $test_lang) {
                    if (!isset($this->_lang_db[strtolower($test_lang)])) {
                        return false;
                    } 
                }
                return true;

            // other (error)
            } else {
                return PEAR::raiseError('Unknown type passed to languageExists()');
            }
        }
    }

    /**
     * Returns the list of detectable languages
     *
     * @access public
     * @return array        the names of the languages known to this object
     * @throws PEAR_Error
     */
    function getLanguages()
    {
        if (!$this->_setup_ok($err)) {
            return $err;
        } else {
            return array_keys($this->_lang_db);
        }
    }

    /**
     * Make this object behave like Language::Guess
     * 
     * @access    public
     * @param     bool     $setting     false to turn off perl compatibility
     */
    function setPerlCompatible($setting = true)
    {
        if (is_bool($setting)) { // input check
            $this->_perl_compatible = $setting;

            if ($setting == true) {
                $this->_max_score = $this->_threshold;
            } else {
                $this->_max_score = 0;
            }
        }

    }

    /**
     * Converts a piece of text into trigrams
     *
     * @access    private
     * @param     string    $text    text to convert
     * @return    array              array of trigram frequencies
     */
    function _trigram($text)
    {
        $text_length = strlen($text);

        // input check
        if ($text_length < 3) {
            return array();
        }

        // each unique trigram is a key in this associative array
        // number of times it appears in the string is the value
        $trigram_freqs = array();
        
        // $i keeps count of which byte in the string we're working in
        // not which character, since characters could take from 1 - 4 bytes
        $i = 0;

        // $a, $b and $c each contain a single character
        // with each iteration $b is set to $c, $a is set to $b and $c is set 
        // to the next character in $text
        $a = $this->_next_char($text, $i, true);
        $b = $this->_next_char($text, $i, true);

        // starts off with the first two characters plus a space
        if (!$this->_perl_compatible) {
            if ($a != ' ') { // exclude trigrams with 2 contiguous spaces
                $trigram_freqs[" $a$b"] = 1;
            }
        }

        while ($i < $text_length) {

            $c = $this->_next_char($text, $i, true);
            // $i is incremented by reference in the line above

            // exclude trigrams with 2 contiguous spaces
            if (!($b == ' ' && ($a == ' ' || $c == ' '))) {
                if (!isset($trigram_freqs[$a . $b . $c])) {
                   $trigram_freqs[$a . $b . $c] = 1;
                } else {
                    $trigram_freqs[$a . $b . $c]++;
                }
            }

            $a = $b;
            $b = $c;
        }

        // end with the last two characters plus a space
        if ($b != ' ') { // exclude trigrams with 2 contiguous spaces
            if (!isset($trigram_freqs["$a$b "])) {
                $trigram_freqs["$a$b "] = 1;
            } else {
                $trigram_freqs["$a$b "]++;
            }
        }

        return $trigram_freqs;
    }

    /**
     * Converts a set of trigrams from frequencies to ranks
     *
     * Thresholds (cuts off) the list at $this->_threshold
     *
     * @access    private
     * @param     array     $arr     array of trgram 
     * @return    array              ranks of trigrams
     */
    function _arr_rank(&$arr)
    {

        // sorts alphabetically first as a standard way of breaking rank ties
        $this->_bub_sort($arr);

        // below might also work, but seemed to introduce errors in testing
        //ksort($arr);
        //asort($arr);

        $rank = array();

        $i = 0;
        foreach ($arr as $key => $value) {
            $rank[$key] = $i++;

            // cut off at a standard threshold
            if ($i >= $this->_threshold) {
                break;
            }
        }

        return $rank;
    }

    /**
     * Sorts an array by value breaking ties alphabetically
     * 
     * @access   private
     * @param    array     &$arr     the array to sort
     */
    function _bub_sort(&$arr)
    {
        // should do the same as this perl statement:
        // sort { $trigrams{$b} == $trigrams{$a} ?  $a cmp $b : $trigrams{$b} <=> $trigrams{$a} }

        // needs to sort by both key and value at once
        // using the key to break ties for the value

        // converts array into an array of arrays of each key and value
        // may be a better way of doing this
        $combined = array();

        foreach ($arr as $key => $value) {
            $combined[] = array($key, $value);
        }

        usort($combined, array($this, '_sort_func'));

        $replacement = array();
        foreach ($combined as $key => $value) {
            list($new_key, $new_value) = $value;
            $replacement[$new_key] = $new_value;
        }

        $arr = $replacement;
    }

    /**
     * Sort function used by bubble sort
     *
     * Callback function for usort(). 
     *
     * @access   private
     * @param    array        first param passed by usort()
     * @param    array        second param passed by usort()
     * @return   int          1 if $a is greater, -1 if not
     * @see      _bub_sort()
     */
    function _sort_func($a, $b)
    {
        // each is actually a key/value pair, so that it can compare using both
        list($a_key, $a_value) = $a;
        list($b_key, $b_value) = $b;

        // if the values are the same, break ties using the key
        if ($a_value == $b_value) {
            return strcmp($a_key, $b_key);

        // if not, just sort normally
        } else {
            if ($a_value > $b_value) {
                return -1;
            } else {
                return 1;
            }
        }

        // 0 should not be possible because keys must be unique
    }

    /**
     * Calculates a statistical difference between two sets of ranked trigrams
     *
     * Sums the differences in rank for each trigram. If the trigram does not 
     * appear in both, consider it a difference of $this->_threshold.
     *
     * Based on the statistical method used by perl's Language::Guess.
     *
     * @access  private
     * @param   array    $arr1  the reference set of trigram ranks
     * @param   array    $arr2  the target set of trigram ranks
     * @return  int             the sum of the differences between the ranks of
     *                          the two trigram sets
     */
    function _distance(&$arr1, &$arr2)
    {
        $sumdist = 0;

        foreach ($arr2 as $key => $value) {
            if (isset($arr1[$key])) {
                $distance = abs($value - $arr1[$key]);
            } else {
                // $this->_threshold sets the maximum possible distance value
                // for any one pair of trigrams
                $distance = $this->_threshold;
            }
            $sumdist += $distance;
        }

        return $sumdist;
    }

    /**
     * Normalizes the score returned by _distance()
     * 
     * Different if perl compatible or not
     *
     * @access    private
     * @param     int    $score          the score from _distance()
     * @param     int    $base_count     the number of trigrams being considered
     * @return    float                  the normalized score
     * @see       _distance()
     */
    function _normalize_score($score, $base_count = null)
    {
        if ($base_count === null) {
            $base_count = $this->_threshold;
        }

        if (!$this->_perl_compatible) {
            return 1 - ($score / $base_count / $this->_threshold);
        } else {
            return floor($score / $base_count);
        }
    }


    /**
     * Detects the closeness of a sample of text to the known languages
     *
     * Calculates the statistical difference between the text and
     * the trigrams for each language, normalizes the score then
     * returns results for all languages in sorted order
     *
     * If perl compatible, the score is 300-0, 0 being most similar.
     * Otherwise, it's 0-1 with 1 being most similar.
     * 
     * The $sample text should be at least a few sentences in length;
     * should be ascii-7 or utf8 encoded, if other and the mbstring extension
     * is present it will try to detect and convert.
     *
     * @access  public
     * @param   string  $sample a sample of text to compare.
     * @param   int     $limit  if specified, return an array of the most likely
     *                           $limit languages and their scores.
     * @return  mixed       sorted array of language scores, blank array if no 
     *                      useable text was found, or PEAR_Error if error 
     *                      with the object setup
     * @see     _distance()
     * @throws  PEAR_Error
     */
    function detect($sample, $limit = 0)
    {
        if (!$this->_setup_ok($err)) {
            return $err;
        }

        // input check
        if ($sample == '' || !preg_match('/\S/', $sample)) {
            return array();
        }

        // check char encoding (only if mbstring extension is compiled)
        if (function_exists('mb_detect_encoding') 
            && function_exists('mb_convert_encoding')) {

            $encoding = mb_detect_encoding($sample);
            if ($encoding != 'ASCII' && $encoding != 'UTF-8') {
                $sample = mb_convert_encoding($sample, 'UTF-8');
            }
        }

        $trigram_freqs = $this->_arr_rank($this->_trigram($sample));
        $trigram_count = count($trigram_freqs);
 
        if ($trigram_count == 0) {
            return array();
        }

        // normalize the score
        // by dividing it by the number of trigrams present
        foreach ($this->_lang_db as $lang => $lang_arr) {
            $scores[$lang] =
                $this->_normalize_score(
                    $this->_distance($lang_arr, $trigram_freqs),
                    $trigram_count);

        }

        if ($this->_perl_compatible) {
            asort($scores);
        } else {
            arsort($scores);
        }

        // todo: drop languages with a score of $this->_max_score?

        // limit the number of returned scores
        if ($limit && is_numeric($limit)) {
            $limited_scores = array();

            $i = 0;

            foreach ($scores as $key => $value) {
                if ($i++ >= $limit) {
                    break;
                }

                $limited_scores[$key] = $value;
            }

            return $limited_scores;
        } else {
            return $scores;
        }
    }

    /**
     * Returns only the most similar language to the text sample
     *
     * Calls $this->detect() and returns only the top result
     * 
     * @access   public
     * @param    string    $sample    text to detect the language of
     * @return   string               the name of the most likely language
     *                                or null if no language is similar
     * @see      detect()
     * @throws   PEAR_Error
     */
    function detectSimple($sample)
    {
        $scores = $this->detect($sample, 1);

        if (PEAR::isError($scores)) {
            return $scores;
        }

        // if top language has the maximum possible score,
        // then the top score will have been picked at random
        if (    !is_array($scores) 
                || !count($scores) 
                || current($scores) == $this->_max_score) {

            return null;

        } else {
            return ucfirst(key($scores));
        }
    }

    /**
     * Returns an array containing the most similar language and a confidence
     * rating
     * 
     * Confidence is a simple measure calculated from the similarity score
     * minus the similarity score from the next most similar language
     * divided by the highest possible score. Languages that have closely
     * related cousins (e.g. Norwegian and Danish) should generally have lower
     * confidence scores.
     *
     * The similarity score answers the question "How likely is the text the
     * returned language regardless of the other languages considered?" The 
     * confidence score is one way of answering the question "how likely is the
     * text the detected language relative to the rest of the language model
     * set?"
     *
     * To see how similar languages are a priori, see languageSimilarity()
     * 
     * @access   public
     * @param    string    $sample    text for which language will be detected
     * @return   array     most similar language, score and confidence rating
     *                     or null if no language is similar
     * @see      detect()
     * @throws   PEAR_Error
     */
    function detectConfidence($sample)
    {
        $scores = $this->detect($sample, 2);

        if (PEAR::isError($scores)) {
            return $scores;
        }

        // if most similar language has the max score, it 
        // will have been picked at random
        if (    !is_array($scores) 
                || !count($scores) 
                || current($scores) == $this->_max_score) {

            return null;
        }

        $arr['language'] = ucfirst(key($scores));
        $arr['similarity'] = current($scores);
        if (next($scores) !== false) { // if false then no next element
            // the goal is to return a higher value if the distance between
            // the similarity of the first score and the second score is high

            if ($this->_perl_compatible) {

                $arr['confidence'] =
                    (current($scores) - $arr['similarity']) / $this->_max_score;

            } else {

                $arr['confidence'] = $arr['similarity'] - current($scores);

            }

        } else {
            $arr['confidence'] = null;
        }

        return $arr;
    }

    /**
     * Calculate the similarities between the language models
     * 
     * Use this function to see how similar languages are to each other.
     *
     * If passed 2 language names, will return just those languages compared.
     * If passed 1 language name, will return that language compared to
     * all others.
     * If passed none, will return an array of every language model compared 
     * to every other one.
     *
     * @access  public
     * @param   string   $lang1   the name of the first language to be compared
     * @param   string   $lang2   the name of the second language to be compared
     * @return  array    scores of every language compared
     *                   or the score of just the provided languages
     *                   or null if one of the supplied languages does not exist
     * @throws  PEAR_Error
     */
    function languageSimilarity($lang1 = null, $lang2 = null)
    {
        if (!$this->_setup_ok($err)) {
            return $err;
        }

        if ($lang1 != null) {
            $lang1 = strtolower($lang1);

            // check if language model exists
            if (!isset($this->_lang_db[$lang1])) {
                return null;
            }

            if ($lang2 != null) {

                // can't only set the second param
                if ($lang1 == null) {
                    return null;
                // check if language model exists
                } elseif (!isset($this->_lang_db[$lang2])) {
                    return null;
                }

                $lang2 = strtolower($lang2);

                // compare just these two languages
                return $this->_normalize_score(
                    $this->_distance(
                        $this->_lang_db[$lang1],
                        $this->_lang_db[$lang2]
                    )
                );


            // compare just $lang1 to all languages
            } else {
                $return_arr = array();
                foreach ($this->_lang_db as $key => $value) {
                    if ($key != $lang1) { // don't compare a language to itself
                        $return_arr[$key] = $this->_normalize_score(
                            $this->_distance($this->_lang_db[$lang1], $value));
                    }
                }
                asort($return_arr);

                return $return_arr;
            }


        // compare all languages to each other
        } else {
            $return_arr = array();
            foreach (array_keys($this->_lang_db) as $lang1) {
                foreach (array_keys($this->_lang_db) as $lang2) {

                    // skip comparing languages to themselves
                    if ($lang1 != $lang2) { 
                    
                        // don't re-calculate what's already been done
                        if (isset($return_arr[$lang2][$lang1])) {

                            $return_arr[$lang1][$lang2] =
                                $return_arr[$lang2][$lang1];

                        // calculate
                        } else {

                            $return_arr[$lang1][$lang2] = 
                                $this->_normalize_score(
                                        $this->_distance(
                                            $this->_lang_db[$lang1],
                                            $this->_lang_db[$lang2]
                                        )
                                );

                        }
                    }
                }
            }
            return $return_arr;
        }
    }

	/**
	 * Cluster known languages according to languageSimilarity()
	 *
	 * WARNING: this method is EXPERIMENTAL. It is not recommended for common
	 * use, and it may disappear or its functionality may change in future
	 * releases without notice.
	 *
	 * Uses a nearest neighbor technique to generate the maximum possible
	 * number of dendograms from the similarity data.
	 *
	 * @access public
	 * @return array language cluster data
	 * @throws PEAR_Error
	 * @see languageSimilarity()
	 */
	function clusterLanguages () {
		// todo: set the maximum number of clusters

		$langs = array_keys($this->_lang_db);

		$arr = $this->languageSimilarity();

		sort($langs);

		foreach ($langs as $lang) {
			if (!isset($this->_lang_db[$lang])) {
				return PEAR::raiseError("missing $lang!\n");
			}
		}

		// http://www.psychstat.missouristate.edu/multibook/mlt04m.html
		foreach ($langs as $old_key => $lang1) {
			$langs[$lang1] = $lang1;
			unset($langs[$old_key]);
		}
		
		$i = 0;
		while (count($langs) > 2 && $i++ < 200) {
			$highest_score = -1;
			$highest_key1 = '';
			$highest_key2 = '';
			foreach ($langs as $lang1) {
				foreach ($langs as $lang2) {
					if (	$lang1 != $lang2 
							&& $arr[$lang1][$lang2] > $highest_score) {
						$highest_score = $arr[$lang1][$lang2];
						$highest_key1 = $lang1;
						$highest_key2 = $lang2;
					}
				}
			}
			
			if (!$highest_key1) {
				return PEAR::raiseError("$i. no highest key?\n");
			}

			if ($highest_score == 0) {
				// languages are perfectly dissimilar
				break;
			}

			// $highest_key1 and $highest_key2 are most similar
			$sum1 = array_sum($arr[$highest_key1]);
			$sum2 = array_sum($arr[$highest_key2]);

			// use the score for the one that is most similar to the rest of 
			// the field as the score for the group
			// todo: could try averaging or "centroid" method instead
			// seems like that might make more sense
			// actually nearest neighbor may be better for binary searching


			// for "Complete Linkage"/"furthest neighbor"
			// sign should be <
			// for "Single Linkage"/"nearest neighbor" method
			// should should be >
			// results seem to be pretty much the same with either method

			// figure out which to delete and which to replace
			if ($sum1 > $sum2) {
				$replaceme = $highest_key1;
				$deleteme = $highest_key2;
			} else {
				$replaceme = $highest_key2;
				$deleteme = $highest_key1;
			}

			$newkey = $replaceme . ':' . $deleteme;

			// $replaceme is most similar to remaining languages
			// replace $replaceme with '$newkey', deleting $deleteme

			// keep a record of which fork is really which language
			$really_lang = $replaceme;
			while (isset($really_map[$really_lang])) {
				$really_lang = $really_map[$really_lang];
			} 
			$really_map[$newkey] = $really_lang;


			// replace the best fitting key, delete the other
			foreach ($arr as $key1 => $arr2) {
				foreach ($arr2 as $key2 => $value2) {
					if ($key2 == $replaceme) {
						$arr[$key1][$newkey] = $arr[$key1][$key2];
						unset($arr[$key1][$key2]);
						// replacing $arr[$key1][$key2] with $arr[$key1][$newkey]
					} 
					
					if ($key1 == $replaceme) {
						$arr[$newkey][$key2] = $arr[$key1][$key2];
						unset($arr[$key1][$key2]);
						// replacing $arr[$key1][$key2] with $arr[$newkey][$key2]
					}

					if ($key1 == $deleteme || $key2 == $deleteme) {
						// deleting $arr[$key1][$key2]
						unset($arr[$key1][$key2]);
					}
				}
			}
						

			unset($langs[$highest_key1]);
			unset($langs[$highest_key2]);
			$langs[$newkey] = $newkey;


			// some of these may be overkill
			$result_data[$newkey] = array(
								'newkey' => $newkey,
								'count' => $i,
								'diff' => abs($sum1 - $sum2),
								'score' => $highest_score,
								'bestfit' => $replaceme,
								'otherfit' => $deleteme,
								'really' => $really_lang,
							);
		}

		$return_val = array(
				'open_forks' => $langs, 
                    // the top level of clusters
                    // clusters that are mutually exclusive
                    // or specified by a specific maximum

				'fork_data' => $result_data,
                    // data for each split

				'name_map' => $really_map,
                    // which cluster is really which language
                    // using the nearest neighbor technique, the cluster
                    // inherits all of the properties of its most-similar member
                    // this keeps track
			);

		return $return_val;
	}


	/**
	 * Perform an intelligent detection based on clusterLanguages()
	 *
	 * WARNING: this method is EXPERIMENTAL. It is not recommended for common
	 * use, and it may disappear or its functionality may change in future
	 * releases without notice.
	 *
	 * This compares the sample text to top the top level of clusters. If the 
	 * sample is similar to the cluster it will drop down and compare it to the
	 * languages in the cluster, and so on until it hits a leaf node.
	 *
	 * this should find the language in considerably fewer compares 
	 * (the equivalent of a binary search), however clusterLanguages() is costly
     * and the loss of accuracy from this techniqueis significant.
     *
	 * This method may need to be 'fuzzier' in order to become more accurate.
     *
     * This function could be more useful if the universe of possible languages
     * was very large, however in such cases some method of Bayesian inference
     * might be more helpful.
	 *
	 * @see clusterLanguages()
	 * @access public
	 * @param string $str input string
	 * @return array language scores (only those compared)
	 */
	function clusteredSearch ($str) {

		// todo: this should be cached in the object, not calculated each time
        // otherwise it defeats the point
		$result = $this->clusterLanguages();

		$dendogram_start = $result['open_forks'];
		$dendogram_data = $result['fork_data'];
		$dendogram_alias = $result['name_map'];
		

		$sample_result = $this->_arr_rank($this->_trigram($str));
		$sample_count = count($sample_result);

		$i = 0; // counts the number of steps
		
		foreach ($dendogram_start as $lang) {
			if (isset($dendogram_alias[$lang])) {
				$lang_key = $dendogram_alias[$lang];
			} else {
				$lang_key = $lang;
			}

			$scores[$lang] = $this->_normalize_score(
				$this->_distance($this->_lang_db[$lang_key], $sample_result),
				$sample_count);

			$i++;
		}

		if ($this->_perl_compatible) {
			asort($scores);
		} else {
			arsort($scores);
		}

		$top_score = current($scores);
		$top_key = key($scores);

		// of starting forks, $top_key is the most similar to the sample

		$cur_key = $top_key;
		while (isset($dendogram_data[$cur_key])) {
			$lang1 = $dendogram_data[$cur_key]['bestfit'];
			$lang2 = $dendogram_data[$cur_key]['otherfit'];
			foreach (array($lang1, $lang2) as $lang) {
				if (isset($dendogram_alias[$lang])) {
					$lang_key = $dendogram_alias[$lang];
				} else {
					$lang_key = $lang;
				}

				$scores[$lang] = $this->_normalize_score(
					$this->_distance($this->_lang_db[$lang_key], $sample_result),
					$sample_count);

				//todo: does not need to do same comparison again
			}

			$i++;

			if ($scores[$lang1] > $scores[$lang2]) {
				$cur_key = $lang1;
				$loser_key = $lang2;
			} else {
				$cur_key = $lang2;
				$loser_key = $lang1;
			}

			$diff = $scores[$cur_key] - $scores[$loser_key];

			// $cur_key ({$dendogram_alias[$cur_key]}) wins 
			// over $loser_key ({$dendogram_alias[$loser_key]}) 
			// with a difference of $diff
		}

		// found result in $i compares

        if ($this->_perl_compatible) {
            asort($scores);
        } else {
            arsort($scores);
        }

		return $scores;
	}

    /**
     * utf8-safe fast character iterator
     *
     * Will get the next character starting from $counter, which will then be
     * incremented. If a multi-byte char the bytes will be concatenated and 
     * $counter will be incremeted by the number of bytes in the char.
     *
     * @access  private
     * @param   string  &$str        the string being iterated over
     * @param   int     &$counter    the iterator, will increment by reference
     * @param   bool    $special_convert whether to do special conversions
     * @return  char    the next (possibly multi-byte) char from $counter
     */
    function _next_char(&$str, &$counter, $special_convert = false)
    {

        $char = $str{$counter++};
        $ord = ord($char);

        // for a description of the utf8 system see
        // http://www.phpclasses.org/browse/file/5131.html

        // normal ascii one byte char
        if ($ord <= 127) {

            // special conversions needed for this package
            // (that only apply to regular ascii characters)
            // lower case, and convert all non-alphanumeric characters
            // other than "'" to space
            if ($special_convert && $char != ' ' && $char != "'") {
                if ($ord >= 65 && $ord <= 90) { // A-Z
                    $char = chr($ord + 32); // lower case
                } elseif ($ord < 97 || $ord > 122) { // NOT a-z
                    $char = ' '; // convert to space
                }
            }

        // multi-byte chars
        } elseif ($ord >> 5 == 6) { // two-byte char
            $nextchar = $str{$counter++}; // get next byte

            // lower case latin accented characters
            if ($special_convert && $ord == 195) {
                $nextord = ord($nextchar);
                $nextord_adj = $nextord + 64;
                // for a reference, see 
                // http://www.ramsch.org/martin/uni/fmi-hp/iso8859-1.html

                // &Agrave; - &THORN; but not &times;
                if (    $nextord_adj >= 192
                        && $nextord_adj <= 222 
                        && $nextord_adj != 215) {

                    // lower case
                    $nextchar = chr($nextord + 32); 
                }
            }

            // tag on next byte
            $char .= $nextchar; 

        } elseif ($ord >> 4  == 14) { // three-byte char
            
            // tag on next 2 bytes
            $char .= $str{$counter++} . $str{$counter++}; 

        } elseif ($ord >> 3 == 30) { // four-byte char

            // tag on next 3 bytes
            $char .= $str{$counter++} . $str{$counter++} . $str{$counter++};

        } else {
            // error?
        }

        return $char;
    }
    
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

?>
