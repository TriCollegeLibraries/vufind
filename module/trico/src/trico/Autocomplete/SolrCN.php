<?php
namespace trico\Autocomplete;

/**
 * Solr Call Number Autocomplete Module
 */
class SolrCN extends \VuFind\Autocomplete\SolrCN
{
    // trico edit 2014.07.09 ah - changed name of lccn sort field
    /**
     * mungeQuery
     *
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query Incoming user query
     *
     * @return string       Processed query
     */
    protected function mungeQuery($query)
    {
        // Modify the query so it makes a nice, truncated autocomplete query:
        $forbidden = array(':', '(', ')', '*', '+', '"');
        $query = str_replace($forbidden, " ", $query);

        // Assign display fields and sort order based on the query -- if the
        // first character is a number, give Dewey priority; otherwise, give
        // LC priority:
        if (is_numeric(substr(trim($query), 0, 1))) {
            $this->setDisplayField(array('dewey-full', 'callnumber-a'));
            $this->setSortField("dewey-sort,callnumber-lc-sort");
        } else {
            $this->setDisplayField(array('callnumber-a', 'dewey-full'));
            $this->setSortField("callnumber-lc-sort,dewey-sort");
        }

        return $query;
    }
}
