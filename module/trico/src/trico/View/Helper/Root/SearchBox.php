<?php
/**
 * Search box view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace trico\View\Helper\Root;
//use VuFind\Search\Options\PluginManager as OptionsManager;

/**
 * Search box view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchBox extends \VuFind\View\Helper\Root\SearchBox 
{
    /**
     * Support method for getHandlers() -- load combined settings.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
        // Build settings:
        $handlers = array();
        $selectedFound = false;
        $backupSelectedIndex = false;
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];
            $label = $settings['label'][$i];

            if ($type == 'VuFind') {
                $options = $this->optionsManager->get($target);
                $j = 0;
                $basic = $options->getBasicHandlers();
                if (empty($basic)) {
                    $basic = array('' => '');
                }
                foreach ($basic as $searchVal => $searchDesc) {
                    $j++;
                    $selected = $target == $activeSearchClass
                        && $activeHandler == $searchVal;
                    if ($selected) {
                        $selectedFound = true;
                    } else if ($backupSelectedIndex === false
                        && $target == $activeSearchClass
                    ) {
                        $backupSelectedIndex = count($handlers);
                    }
                    if($j == 1) {
                        $handlers[] = array(
                            'value' => $type . ':' . $target . '|' . $searchVal,
                            'label' => $label,
                            'indent' => false,
                            'selected' => $selected
                        );
                        if($label != 'All Resources'){
                            $handlers[] = array(
                                'value' => $type . ':' . $target . '|' . $searchVal,
                                'label' => $searchDesc,
                                'indent' => true,
                                'selected' => $selected
                            );
                        }
                    } else {
                        $handlers[] = array(
                            'value' => $type . ':' . $target . '|' . $searchVal,
                            'label' => $searchDesc,
                            'indent' => true,
                            'selected' => $selected
                        );
                    }
                }
            } else if ($type == 'External') {
                $handlers[] = array(
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false
                );
            }
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match:
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }
}

