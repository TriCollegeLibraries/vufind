<?php

namespace trico\RecordDriver;
use VuFind\Exception\ILS as ILSException, VuFind\XSLT\Processor as XSLTProcessor;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{

    /**
     * Get the full title of the record.
     *
     * trico edit 2014.06.04 ah - changed to get the right punctuation in there
     * the field 'title' is indexed as 245ab with 
     * punctuation stripped from the end, but colon still has spaces
     * on either side. So we don't really use that for display.
     *
     * @return string
     */
    public function getTitle()
    {
    /* TRICO edit 2012-05 sl 
     * - if only 245a, no trailing punctuation (removed at indexing)
     * - if 245ab, but no 245h, insert colon
     * - if 245ab and h, get punct from 245h
     */
        $short = isset($this->fields['title_short']) ?
            $this->fields['title_short'] : '';
        $sub = isset($this->fields['title_sub']) ?
            $this->fields['title_sub'] : '';
        $punc = ':';
        // trico TODO:
        // we were indexing a field title_medium which grabbed just the
        // punctuation from 245h. At this time, the regex on that field does
        // not work. therefore, I moved the regex here. We may wish to explore
        // moving this regex back at some future time.
        $gmd = $this->getFirstFieldValue('245', array('h'));
        if (isset($gmd)) {
            if (preg_match('/\[.*\]\s*(.*)/', $gmd, $matches)) {
                if ($matches[1] == "=") {
                    // equals looks weird without a space in front
                    $punc = ' ' . $matches[1];
                } else {
                    $punc = $matches[1];
                }
            }
        }

        if ($short) {
            $title = $short;
            if ($sub) $title .= "$punc $sub";
        } else {
            $title = isset($this->fields['title']) ?
                $this->fields['title'] : '';
        }
        return $title;
    }

    //TRICO edit 2011-10 sl - function to process new solr index
    /**
    * Get the uniform title of the record.
    *
    * @return array
    * @access public
    */
    public function getUniformTitles()
    {
        return isset($this->fields['title_uniform']) ?
            $this->fields['title_uniform'] : array();
    }

    //TRICO edit 2011-10 sl - function to process new solr index
    //TRICO edit 2014.06.05 ah - note there are 502 records with 247 fields.
    /**
     * Get an array of former titles for the record.
     * The index is generated with '|' between each subfield. The function removes those and places a '|' after the title/subfield 'a'
     *
     * @return array
     * @access public
     */
    public function getFormerTitles()
    {
        $formertitles = array();
        $count = 0;
        if (isset($this->fields['title_former']) && is_array($this->fields['title_former'])) {
            foreach ($this->fields['title_former'] as $title_former) {
                $subs= explode('|', $title_former, 2);
                $subs[0] = $subs[0] . '|';
                if (isset($subs[1])) {
                    $subs[1] = str_replace("|", "", $subs[1]);
                }
                else {
                    $subs[1] = " ";
                }
                $formertitles[$count] = $subs[0] . $subs[1];
                $count++;
           }
        }
        return $formertitles;
    }

    //TRICO edit 2011-10 sl - an edition function that can process multiple fields on a record as an array
    //because getEdition needs to return a string for OpenUrl functions
    /**
     * Get multiple editions for the current record.
     *
     * @return array
     * @access public
     */
    public function getEditions()
    {
        return isset($this->fields['tricoedition']) ?
            $this->fields['tricoedition'] : array();
    }

    /**
     * Get the text of the part/section portion of the title.
     * Strip punctuation from the end.
     *
     * @return string
     */
    public function getTitleSection()
    {
        $title_sec = $this->getFirstFieldValue('245', array('n', 'p'));
        return preg_replace('/\s\W$/', "", $title_sec);
    }

    // TRICO edit 2014.06.09 ah - add the 810; move some subfields out of link
    /**
     * Get an array of all series names containing the record.  Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        $matches = array();

        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = array(
            '440' => array('a', 'p'),
            '800' => array('a', 'b', 'p', 't'),
            '810' => array('a', 'b', 'p', 't'),
            '830' => array('a', 'p'));
        $matches = $this->getSeriesFromMARC($primaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Now check 490 and display it only if 440/800/830 were empty:
        $secondaryFields = array('490' => array('a'));
        $matches = $this->getSeriesFromMARC($secondaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Still no results found?  Resort to the Solr-based method just in case!
        return parent::getSeries();
    }


    // TRICO edit 2014.06.09 ah - add subfields to 'number' (non-linked) field.
    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        $matches = array();

        // Loop through the field specification....
        foreach ($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->marcRecord->getFields($field);
            if (is_array($series)) {
                foreach ($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    $name = $this->getSubfieldArray($currentField, $subfields);
                    if (isset($name[0])) {
                        $currentArray = array('name' => $name[0]);

                        // Can we find a number in subfield v?  (Note that number is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        $number
                          = $this->getSubfieldArray($currentField, array(
                            'c', 'd', 'e', 'f', 'g', 'h', 'k', 'l',
                            'm', 'n', 'o', 'r', 's', 'q', 'u', 'v'));
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return $matches;
    }

    //TRICO edit 2011-10 sl - added additional subfields
    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return $this->getFieldArray('520', array('a', 'b', 'c','3'), true);
    }

    // TRICO edit 2014.06.10 ah - we had a lot more kinds of notes in the
    // template; just moving that work here since it already loops through
    // the array.
    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        // GeneralNotes
        $notes = $this->getFieldArray('500');
        // CartographicNotes
        $notes = array_merge($notes, $this->getFieldArray('255', array('a','b','c','d','e','f','g'), true));
        // WithNotes
        $notes = array_merge($notes, $this->getFieldArray('501', array('a'), false));
        // DissertationNotes
        $notes = array_merge($notes, $this->getFieldArray('502', array('a'), false));
        // EventNotes
        $notes = array_merge($notes, $this->getFieldArray('518', array('a'), false));
        // LanguageNotes
        $notes = array_merge($notes, $this->getFieldArray('546', array('a', '3'), true));
        // LocalNotes
        $notes = array_merge($notes, array_unique($this->getFieldArray('590')));

        return $notes;
    }

    // TRICO edit 2014.06.10 ah - special trico display stuff
    /**
     * Get background notes on the record.
     *
     * @return array
     */
    public function getBackgroundNotes()
    {
        // OriginalVersion
        $notes = $this->getFieldArray('534', array('a', 'b', 'c', 'e', 'f', 'k', 'l', 'm', 'n', 'o', 'p', 't', 'x', 'z', '3'), true);
        // AcquisitionSource
        $notes = array_merge($notes, $this->getFieldArray('541', array('a', 'b', 'c', 'e', 'f', 'h', 'n', 'o', '3', '5', '8'), true));
        // BiographicalNote
        $notes = array_merge($notes, $this->getFieldArray('545', array('a'), true));
        // OwnershipNote
        $notes = array_merge($notes, $this->getFieldArray('561', array('a', '3', '5'), true));
        // LocalProvenance
        $notes = array_merge($notes, $this->getFieldArray('799', array('a', 'd', 'f', 'g', 'h', 'i', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't'), true));

        return $notes;
    }

    // trico edit 2014.06.10 - adding another field that trico wants.
    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // access restrictions
        $notes = $this->getFieldArray('506');
        // getAccessTerms
        return array_merge($notes, $this->getFieldArray('540', array('a', 'b', 'c',  'd', 'u', '3', '5'), true));
    }

    //TRICO edit 2011-10 sl - function to process arrangement data
    /**
     * Get arrangement notes on the record.
     *
     * @return array
     * @access public
     */
    public function getArrangementNotes()
    {
        return $this->getFieldArray('351', array('a', 'b'), true);
    }

    //TRICO edit 2011-10 sl - function to process citation
    /**
     * Get citation on the record.
     *
     * @return array
     * @access public
     */
    public function getMARCCitation()
    {
        return $this->getFieldArray('524', array('a', '3'), true);
    }

    //TRICO edit 2011-10 sl - function to process reference data
    /**
     * Get references on the record.
     *
     * @return array
     * @access public
     */
    public function getReferences()
    {
        return $this->getFieldArray('510', array('a', 'c'), true);
    }

    //TRICO edit 2011-10 sl - function to process performer data
    /**
     * Get performer on the record.
     *
     * @return array
     * @access public
     */
    public function getPerformers()
    {
        return $this->getFieldArray('511', array('a'), true);
    }

    //TRICO edit 2011-10 sl - function for author notes from the MARC record
    /**
     * @return array
     * @access public
     */
    public function getMarcAuthorNotes()
    {
        return $this->getFieldArray('972');
    }

    //TRICO Edit 2011-10 sl - added function for processing alt titles
    /**
     * Get all alternate titles associated with this record.
     * To facilitate generating hyperlinks from titles
     * subfield codes and data are set as array keys and values
     * and the 'a' and 'b' subfields are combined.
     *
     * @return array
     * @access public
     */
    public function getAlternateTitles()
    {
        // Set array of alt title fields
        $fieldsToCheck = array('212', '242', '246', '730', '740');

        $titles = array();
        foreach ($fieldsToCheck as $field) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->marcRecord->getFields($field);
            if (is_array($results)) {
              foreach ($results as $result) {
                  // Start an array for holding the chunks of the current field:
                  $current = array();

                  // Get all the chunks and collect them together:
                  $subfields = $result->getSubfields();
                  if ($subfields) {
                      foreach ($subfields as $subfield) {
                          // Numeric subfields are for control purposes and should not
                          // be displayed:
                          if (!is_numeric($subfield->getCode())) {
                              // Set array key as subfield code
                              $code = $subfield->getCode();
                              // Set array value as subfield data
                              $current[$code] = $subfield->getData();
                          }
                      }
                      // test for 'a' and 'b subfields and combine them.
                      if (isset($current['a']) && isset($current['b'])) {
                          $current['a'] = $current['a'] . $current['b'];
                          $current['b'] = "";
                      }

                      if (!empty($current)) {
                          $titles[] = $current;
                      }
                  }
              }
            }
        }
        return $titles;
    }


    // trico edit 2014.06.11 ah - adding trico's archival notes
    /**
     * Get an array of strings describing relationships to other items.
     *
     * @return array
     */
    public function getRelationshipNotes()
    {
        $related = $this->getFieldArray('580');
        return array_merge($related, $this->getFieldArray('544', array('a','b','c','d','e','n','3'), true));
    }

    // TRICO edit 2013-09 ah - add subfield 3 as valid source for url display text
    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $retVal = array();

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = array(
            '856' => array('y', 'z', '3'),   // Standard URL
            '555' => array('a')         // Cumulative index/finding aids
        );

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->marcRecord->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $url->getSubfield('u');
                    if ($address) {
                        $address = $address->getData();

                        // Is there a description?  If not, just use the URL itself.
                        foreach ($subfields as $current) {
                            $desc = $url->getSubfield($current);
                            if ($desc) {
                                break;
                            }
                        }
                        if ($desc) {
                            $desc = $desc->getData();
                        } else {
                            $desc = $address;
                        }

                        $retVal[] = array('url' => $address, 'desc' => $desc);
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        // Get the contents of the 505 and 970 MARC fields
        $fields505 = $this->marcRecord->getFields('505');
        $fields970 = $this->marcRecord->getFields('970');

        // Return empty array if we have no table of contents:
        if (!$fields505 && !$fields970) {
            return array();
        }

        // If we have a 505, collect it as a string:
        if(count($fields505) > 1) {
            $toc505arr = array();
            $toc505str = '';
            foreach ($fields505 as $field) {
                $subfields = $field->getSubfields();
                if(count($subfields) > 1){
                    foreach ($subfields as $subfield) {
                        $toc505str .= $subfield->getData();
                    }
                    $toc505 = $toc505str;
                } else {
                    foreach ($subfields as $subfield) {
                        $toc505arr[] = $subfield->getData();
                    }
                    $toc505 = $toc505arr;
                }
            }         
        } else {
            $toc505 = '';
           foreach ($fields505 as $field) {
                $subfields = $field->getSubfields();
                foreach ($subfields as $subfield) {
                    $toc505 .= $subfield->getData();
                }
            }
        }

        $toc['505'] = $toc505;

        // If we have a 970, collect it as an array
        $toc970 = array(); $toctester = array();
        $count = 0;
        foreach ($fields970 as $field) {
            $toc970[$count] = array();
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                 if ($subfield->getCode() != 'l' && $subfield->getCode() != 'c') {
                     if ($subfield->getCode() == 'p') {
                         $toc970[$count][] = 'p. ' . $subfield->getData();
                     }else{
                         $toc970[$count][] = $subfield->getData();
                     }
                 }
            }
            $count++;
        }

        $toc['970'] = $toc970;

        return $toc;
    }
    //TRICO edit 2011-10 sl - added additional fields for subject headings
    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        // These are the fields that may contain subject headings:
        $fields = array('600', '610', '611', '630', '650', '651', '655','690', '691', '755');

        // This is all the collected data:
        $retval = array();

        // Try each MARC field one at a time:
        foreach ($fields as $field) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->marcRecord->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = array();

                // Get all the chunks and collect them together:
                $subfields = $result->getSubfields();
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        // Numeric subfields are for control purposes and should not
                        // be displayed:
                        if (!is_numeric($subfield->getCode())) {
                            $current[] = $subfield->getData();
                        }
                    }
                    // If we found at least one chunk, add a heading to our result:
                    if (!empty($current)) {
                        $retval[] = $current;
                    }
                }
            }
        }

        // Send back everything we collected:
        return $retval;
    }

    //TRICO edit 2011-10 sl - commented out. Should only display in staff view
    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
       //return $this->getFieldArray('555');
       return array();
    }

    /**
     * Get the item's publication information
     *
     * @param string $subfield The subfield to retrieve ('a' = location, 'c' = date)
     *
     * @return array
     */
    protected function getPublicationInfo($subfield = 'a')
    {
        // First check old-style 260 field:
        $results = $this->getFieldArray('260', array($subfield));

        // Now track down relevant RDA-style 264 fields; we only care about
        // copyright and publication places (and ignore copyright places if
        // publication places are present).  This behavior is designed to be
        // consistent with default SolrMarc handling of names/dates.
        $pubResults = $copyResults = array();

        $fields = $this->marcRecord->getFields('264');
        if (is_array($fields)) {
            foreach ($fields as $currentField) {
                $currentVal = $currentField->getSubfield($subfield);
                $currentVal = is_object($currentVal)
                    ? $currentVal->getData() : null;
                if (!empty($currentVal)) {
                    switch ($currentField->getIndicator('2')) {
                    case '1':
                        $pubResults[] = $currentVal;
                        break;
                    case '4':
                        $copyResults[] = $currentVal;
                        break;
                    }
                }
            }
        }

        // TODO : VuFind 2 merges an array of 260 fields with 264. 
        // We should investigate if this would
        // work for us or not (StrikeForce question). 
        // Currently, behavior is mimicking our 1.3 implementation of 
        // displaying 264 if it exists else display 260
        if (count($pubResults) > 0) {
            $results = $pubResults;
            //$results = array_merge($results, $pubResults);
        } //else if (count($copyResults) > 0) {
            //$results = array_merge($results, $copyResults);
        //}

        return $results;
    }

    // trico edit 2014.08.01 ah - turn off snippets for callnumber searches
    // they bring out all kinds of weird stuff.
    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // hack alert (ah)! I wanted to use query->getHandler() but I can't
        // get a search query object or a search result object from this class.
        $type = $_GET['type'];
        if ($type == "CallNumber") {
            return false;
        }
        return parent::getHighlightedSnippet();
    }

}
