<?php
class Pixlee_Base_Block_Gallery extends Mage_Core_Block_Template {

  const DEFAULT_RECIPE_ID = 155;
  const DEFAULT_DISPLAY_OPTIONS_ID = 2012;

  public function _prepareLayout() {
    $product = Mage::registry("current_product");
    if(!empty($product)) {
      $this->setProductSku($product->getSku());
    }
    $this->setTemplate("pixlee/gallery.phtml");
    return parent::_prepareLayout();
  }

  public function getAccountId() {
    return Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
  }

  public function getAccountApiKey() {
    return Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
  }

  public function getRecipeId() {
    $pixleeRecipeId = Mage::getStoreConfig('pixlee/pixlee/recipe_id', Mage::app()->getStore());
    return ($pixleeRecipeId) ? $pixleeRecipeId : self::DEFAULT_RECIPE_ID;
  }

  public function getDisplayOptionsId() {
    $pixleeDisplayOptionsId = Mage::getStoreConfig('pixlee/pixlee/display_options_id', Mage::app()->getStore());
    return ($pixleeDisplayOptionsId) ? $pixleeDisplayOptionsId : self::DEFAULT_DISPLAY_OPTIONS_ID;
  }

  public function getApiKey() {
    $pixleeApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    return ($pixleeApiKey) ? $pixleeApiKey : self::DEFAULT_DISPLAY_OPTIONS_ID;
  }

}
