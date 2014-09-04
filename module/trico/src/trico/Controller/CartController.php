<?php
namespace trico\Controller;

/**
 * Book Bag / Bulk Action Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class CartController extends \VuFind\Controller\CartController
{
    /**
     * Process bulk actions from the MyResearch area; most of this is only necessary
     * when Javascript is disabled.
     *
     * @return mixed
     */
    public function myresearchbulkAction()
    {
        // We came in from the MyResearch section -- let's remember which list (if
        // any) we came from so we can redirect there when we're done:
        $listID = $this->params()->fromPost('listID');
        $this->session->url = empty($listID)
            ? $this->url()->fromRoute('myresearch-favorites')
            : $this->url()->fromRoute('userList', array('id' => $listID));

        // Now forward to the requested controller/action:
        $controller = 'Cart';   // assume Cart unless overridden below.
        if (strlen($this->params()->fromPost('email', '')) > 0) {
            $action = 'Email';
        } else if (strlen($this->params()->fromPost('print', '')) > 0) {
            $action = 'PrintCart';
        // trico edit 2014.05.02 ah - add zotero, just a call to printcart
        } else if (strlen($this->params()->fromPost('zotero', '')) > 0) {
            $action = 'Zotero';
        } else if (strlen($this->params()->fromPost('delete', '')) > 0) {
            $controller = 'MyResearch';
            $action = 'Delete';
        } else if (strlen($this->params()->fromPost('add', '')) > 0) {
            $action = 'Cart';
        } else if (strlen($this->params()->fromPost('export', '')) > 0) {
            $action = 'Export';
        } else {
            throw new \Exception('Unrecognized bulk action.');
        }
        return $this->forwardTo($controller, $action);
    }

    /**
     * present print page, but with print dialogue disabled,
     * for a batch of records (for zotero)
     * note: almost exactly the same as printcartAction
     *
     * @return mixed
     */
    public function zoteroAction()
    {
        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $callback = function ($i) {
            return 'id[]=' . urlencode($i);
        };
        $query = '?print=false&' . implode('&', array_map($callback, $ids));
        $url = $this->url()->fromRoute('records-home') . $query;
        return $this->redirect()->toUrl($url);
    }
}
