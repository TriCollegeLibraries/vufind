<?php
namespace trico\View\Helper\Tricobootstrap;

/**
 * Helper class for displaying search-related HTML chunks.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Search extends \VuFind\View\Helper\Bootstrap\Search
{
    /**
     * Support function to display spelling suggestions.
     *
     * @param string                          $msg     HTML to display at the top of
     * the spelling section.
     * @param \VuFind\Search\Base\Results     $results Results object
     * @param \Zend\View\Renderer\PhpRenderer $view    View renderer object
     *
     * @return string
     */
    public function renderSpellingSuggestions($msg, $results, $view)
    {
        $spellingSuggestions = $results->getSpellingSuggestions();
        if (empty($spellingSuggestions)) {
            return '';
        }

        // trico edit 2014.04.02 ah - added an id so we can restyle
        $html = '<div class="' . $this->getContainerClass() . '" id="spellingSuggestions">';
        $html .= $msg;
        foreach ($spellingSuggestions as $term => $details) {
            // trico edit 2014.04.02 ah - changed br to space
            $html .= ' ' . $view->escapeHtml($term) . ' &raquo; ';
            $i = 0;
            foreach ($details['suggestions'] as $word => $data) {
                if ($i++ > 0) {
                    $html .= ', ';
                }
                $html .= '<a href="'
                    . $results->getUrlQuery()
                        ->replaceTerm($term, $data['new_term'])
                    . '">' . $view->escapeHtml($word) . '</a>';
                if (isset($data['expand_term']) && !empty($data['expand_term'])) {
                    $url = $results->getUrlQuery()
                        ->replaceTerm($term, $data['expand_term']);
                    $html .= $this->renderExpandLink($url, $view);
                }
            }
        }
        $html .= '</div>';
        return $html;
    }
}
