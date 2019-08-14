<?php
class Pixlee_Base_Helper_Data extends Mage_Core_Helper_Abstract {

  protected $_unexportedProducts;
  protected $_pixleeAPI;
  protected $_pixleeProductAlbumModel;
  protected $_isTesting;

  /**
   * Used to initialize the Pixlee API with a stub for testing purposes.
   */
  public function _initTesting($pixleeAPI = null, $pixleeProductAlbum = null) {
    if(!empty($pixleeAPI)) {
      $this->_pixleeAPI = $pixleeAPI;
    }
    if(!empty($pixleeProductAlbum)) {
      $this->_pixleeProductAlbumModel = $pixleeProductAlbum;
    }

    $this->_isTesting = true;
  }

  public function isActive() {
    if($this->_isTesting) {
      return true;
    }

    $pixleeAccountId = Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
    $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
    if(!empty($pixleeAccountId) && !empty($pixleeAccountApiKey)) {
      return true;
    } else {
      return false;
    }
  }

  public function isInactive() {
    return !$this->isActive();
  }

  public function getNewPixlee() {
    if(!empty($this->_pixleeAPI)) {
      return $this->_pixleeAPI;

    } elseif($this->isActive()) {
      $pixleeAccountId = Mage::getStoreConfig('pixlee/pixlee/account_id', Mage::app()->getStore());
      $pixleeAccountApiKey = Mage::getStoreConfig('pixlee/pixlee/account_api_key', Mage::app()->getStore());
      try {
        $this->_pixleeAPI = new Pixlee_Pixlee($pixleeAccountApiKey);
        return $this->_pixleeAPI;
      }
      catch (Exception $e) {
        Mage::log("PIXLEE ERROR: " . $e->getMessage());
      }

    } else {
      return null;
    }
  }

  public function getPixleeAlbum() {
    if(empty($this->_pixleeProductAlbumModel)) {
      return Mage::getModel('pixlee/product_album');
    }
    return $this->_pixleeProductAlbumModel;
  }

  public function getUnexportedProducts($useCached = true) {
    // NEVERMIND I'm dumb
    // Tee Ming was totally justified in doing what he did. I should never have doubted Tee Ming.
    // My problem is not that I need getUnexportedProducts to return a cached result, but that
    // having converted to distillery, I wasn't correctly parsing out the created album ID
    /*
    if($this->_unexportedProducts && $useCached) {
      return $this->_unexportedProducts;
    }
    */
    $albumTable = Mage::getSingleton('core/resource')->getTableName('pixlee/product_album');
    $collection = Mage::getModel('catalog/product')->getCollection()
      ->addAttributeToFilter('visibility', array('neq' => 1)); // Only grab products that are visible in catalog and/or search
      $collection->getSelect()->joinLeft(
        array('albums' => $albumTable),
        'e.entity_id = albums.product_id'
        )->where(
        'albums.product_id IS NULL'
        );
        $collection->addAttributeToSelect('*');
        $this->_unexportedProducts = $collection;
        return $collection;
      }

      public function getPixleeRemainingText() {
        $c = $this->getUnexportedProducts()->count();
        if($this->isInactive()) {
          return "Save your Pixlee API access information before exporting your products.";
        } elseif($c > 0) {
          return "Export your products to Pixlee and start collecting photos. There ". (($c > 1) ? 'are' : 'is') ." <strong>". $c ." ". (($c > 1) ? 'products' : 'product') ."</strong> to export to Pixlee.";
        } else {
          return "All your products have been exported to Pixlee. Congratulations!";
        }
      }

      public function _extractActualProduct($product) {
        Mage::log("*** Before _extractActualProduct");
        Mage::log("Name: {$product->getName()}");
        Mage::log("ID: {$product->getId()}");
        Mage::log("SKU: {$product->getSku()}");
        Mage::log("Type: {$product->getTypeId()}");
        $mainProduct = $product;
        $temp_product_id = Mage::getModel('catalog/product')->getIdBySku($product->getSku());
        $parent_ids = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($temp_product_id);
        Mage::log("Parent IDs are");
        Mage::log($parent_ids);
        if($parent_ids) {
          $mainProduct = Mage::getModel('catalog/product')->load($parent_ids[0]);
        } else if($product->getTypeId() == "bundle") {
          $mainProduct = Mage::getModel('catalog/product')->load($product->getId()); // Get original sku as stated in product catalog
        }
        Mage::log("*** After _extractActualProduct");
        Mage::log("Name: {$mainProduct->getName()}");
        Mage::log("ID: {$mainProduct->getId()}");
        Mage::log("SKU: {$mainProduct->getSku()}");
        Mage::log("Type: {$mainProduct->getTypeId()}");
        return $mainProduct;
      }

      // Sum up the stock numbers of all the children products
      // EXPECTS A 'configurable' TYPE PRODUCT!
      // If we wanted to be more robust, we could pass the argument to the _extractActualProduct
      // function, but as of 2016/03/11, getAggregateStock is only called after _extractActualProduct
      // has already been called
      public function getAggregateStock($actualProduct) {
        Mage::log("*** In getAggregateStock");
        $aggregateStock = NULL;
        // If after calling _extractActualProduct, there is no 'configurable' product, and only
        // a 'simple' product, we won't get anything back from
        // getModel('catalog/product_type_configurable')
        if ($actualProduct->getTypeId() == "simple") {
          // If the product's not keeping track of inventory, we'll error out when we try
          // to call the getQty() function on the output of getStockItem()
          if (is_null($actualProduct->getStockItem())) {
            $aggregateStock = NULL;
          } else {
            $aggregateStock = max(0, $actualProduct->getStockItem()->getQty());
          }
        } else {
          // 'grouped' type products have 'associated products,' which presumably
          // point to simple products
          if ($actualProduct->getTypeId() == "grouped") {
            $childProducts = $actualProduct->getTypeInstance(true)->getAssociatedProducts($actualProduct);
          // And finally, my original assumption that all 'simple' products are
          // under the umbrella of some 'configurable' product
          } else if ($actualProduct->getTypeId() == "configurable") {
            $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$actualProduct);
          }
          foreach ($childProducts as $child) {
            // Sometimes Magento gives a negative inventory quantity
            // I don't want that to affect the overall count
            // TODO: There is probably a good reason why it goes negative
            Mage::log("Child Name: {$child->getName()}");
            Mage::log("Child SKU: {$child->getSku()}");
            if (is_null($child->getStockItem())) {
              Mage::log("Child product not tracking stock, setting to NULL");
            } else {
              Mage::log("Child Stock: {$child->getStockItem()->getQty()}");
              if (is_null($aggregateStock)) {
                $aggregateStock = 0;
              }
              $aggregateStock += max(0, $child->getStockItem()->getQty());
            }
          }
        }
        Mage::log("Returning aggregateStock: {$aggregateStock}");
        return $aggregateStock;
      }

      public function getVariantsDict($actualProduct) {
        $variantsDict = array();

        // If after calling _extractActualProduct, there is no 'configurable' product, and only
        // a 'simple' product, we won't get anything back from
        // getModel('catalog/product_type_configurable')
        if ($actualProduct->getTypeId() == "simple") {
          if (is_null($actualProduct->getStockItem())) {
            $variantStock = NULL;
          } else {
            $variantStock = max(0, $actualProduct->getStockItem()->getQty());
          }
          $variantsDict[$actualProduct->getId()] = array(
            'variant_stock' => $variantStock,
            'variant_sku' => $actualProduct->getSku(),
          );
        } else {
          // 'grouped' type products have 'associated products,' which presumably
          // point to simple products
          if ($actualProduct->getTypeId() == "grouped") {
            $childProducts = $actualProduct->getTypeInstance(true)->getAssociatedProducts($actualProduct);
          // And finally, my original assumption that all 'simple' products are
          // under the umbrella of some 'configurable' product
          } else if ($actualProduct->getTypeId() == "configurable") {
            $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$actualProduct);
          }
          foreach ($childProducts as $child) {
            // Sometimes Magento gives a negative inventory quantity
            // I don't want that to affect the overall count
            // TODO: There is probably a good reason why it goes negative
            $variantId = $child->getId();

            if (is_null($child->getStockItem())) {
              $variantStock = NULL;
            } else {
              $variantStock = max(0, $child->getStockItem()->getQty());
            }

            $variantsDict[$variantId] = array(
              'variant_stock' => $variantStock,
              'variant_sku' => $child->getSku(),
            );
          }
        }
        return $variantsDict;
      }

      // Whether creating a product, updating a product, or just exporting a product,
      // this function gets called
      public function exportProductToPixlee($product) {
        Mage::log("*** In exportProductToPixlee");
        $product = $this->_extractActualProduct($product);
        $productName = $product->getName();
        if($this->isInactive() || !isset($productName)) {
          return false;
        }

        $pixlee = $this->getNewPixlee();
        if($product->getVisibility() != 1) { // Make sure the product is visible in search or catalog
          try {
            $aggregateStock = $this->getAggregateStock($product);
            Mage::log("Total stock is: {$aggregateStock}");
            $variantsDict = $this->getVariantsDict($product);
            Mage::log("Variants dict is");
            Mage::log($variantsDict);

            $product_mediaurl = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getImage());
            $response = $pixlee->createProduct($product->getName(), $product->getSku(), $product->getProductUrl(), $product_mediaurl, $product->getId(), $aggregateStock, $variantsDict);
            $albumId = 0;

            if(isset($response->data->album->id)) {
              $albumId = $response->data->album->id;
            } else if(isset($response->data->product->album_id)) {
              $albumId = $response->data->product->album_id;
            // Distillery returns the product album on the 'create' verb
            } else if(isset($response->id)) {
              $albumId = $response->id;
            }

            if($albumId) {
              $album = $this->getPixleeAlbum();
              $album->setProductId($product->getId())->setPixleeAlbumId($albumId);
              $album->save();
            } else {
              return false;
            }
          } catch (Exception $e) {
            Mage::log("PIXLEE ERROR: " . $e->getMessage());
            return false;
          }
        }

        return true;
      }
}
