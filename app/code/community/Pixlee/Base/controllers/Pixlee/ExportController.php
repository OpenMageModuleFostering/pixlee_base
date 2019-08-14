<?php
class Pixlee_Base_Pixlee_ExportController extends Mage_Adminhtml_Controller_Action {

  public function exportAction() {

    $helper = Mage::helper('pixlee');
    $json = array();

    if($helper->isActive()) {
      $products = $helper->getUnexportedProducts();
      $products->getSelect()->limit(10); // Set the limit to 10 to prevent lagging out the store or Pixlee's API.
      foreach($products as $product) {
        $ids = $product->getStoreIds();
        if(isset($ids[0])) {
          $product->setStoreId($ids[0]);
        }
        $helper->exportProductToPixlee($product);
      }

      $count = $helper->getUnexportedProducts(false)->count(); // Find out how many products are left to export
      if($count) {
        $json = array(
          'action' => 'continue',
          'url' => Mage::helper('adminhtml')->getUrl('*/pixlee_export/export'),
          'remaining' => $count
        );
      } else {
        $json = array('action' => 'success');
      }
    }

    $json['pixlee_remaining_text'] = $helper->getPixleeRemainingText();
    $this->getResponse()->setHeader('Content-type', 'application/json');
    $this->getResponse()->setBody(json_encode($this->utf8_converter($json)));
  }

  protected function _isAllowed() {
    return Mage::getSingleton('admin/session')->isAllowed('pixlee');
  }

  public function utf8_converter($array)
  {
      array_walk_recursive($array, function(&$item, $key){
          if(!mb_detect_encoding($item, 'utf-8', true)){
                  $item = utf8_encode($item);
          }
      });
   
      return $array;
  }

}
