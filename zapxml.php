<?php
    /**
     * 2007-2015 PrestaShop
     *
     * NOTICE OF LICENSE
     *
     * This source file is subject to the Academic Free License (AFL 3.0)
     * that is bundled with this package in the file LICENSE.txt.
     * It is also available through the world-wide-web at this URL:
     * http://opensource.org/licenses/afl-3.0.php
     * If you did not receive a copy of the license and are unable to
     * obtain it through the world-wide-web, please send an email
     * to license@prestashop.com so we can send you a copy immediately.
     *
     * DISCLAIMER
     *
     * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
     * versions in the future. If you wish to customize PrestaShop for your
     * needs please refer to http://www.prestashop.com for more information.
     *
     * @author    PrestaShop SA <contact@prestashop.com>
     * @copyright 2007-2015 PrestaShop SA
     * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
     *  International Registered Trademark & Property of PrestaShop SA
     */

    /**
     * @since 1.5.0
     */
    include(dirname(__FILE__) . '/../../config/config.inc.php');
    include(dirname(__FILE__) . '/../../init.php');
    include(dirname(__FILE__) . '/zapexport.php');

    $catID = (int)Tools::getValue('catID', 0);
    if (isset($catID) && (int)$catID > 0) {
        header("Content-Type:text/xml; charset=utf-8;");

        $context = Context::getContext();

        function getExtraFeatures($productID, $context, $product)
        {
            $features = Product::getFrontFeaturesStatic($context->language->id, (int)$productID);

            $html = '';

            $warranty = Configuration::get('ZAPEXPORT_WARRANTY');
            $model = $product['reference'];

            foreach ($features as $feature) {
                if (Tools::strtolower($feature['name']) == 'warranty' && Configuration::get('ZAPEXPORT_WARRANTY') == '') {
                    $warranty = $feature['value'];
                }
                if (Tools::strtolower($feature['name']) == 'model') {
                    $model = $feature['value'];
                }
            }

            $html .= '<WARRANTY><![CDATA[ ' . $warranty . ' ]]></WARRANTY>' . "\t\n";
            $html .= '<MODEL><![CDATA[ ' . $model . ' ]]></MODEL>' . "\t\n";

            return $html;
        }

        function getAttributeCombinationsById($id_product, $id_lang)
        {
            if (!Combination::isFeatureActive()) {
                return array();
            }
            $sql = 'SELECT pa.*, product_attribute_shop.*, ag.`id_attribute_group`, ag.`is_color_group`, agl.`name` AS group_name, al.`name` AS attribute_name,
					a.`id_attribute`
				FROM `' . _DB_PREFIX_ . 'product_attribute` pa
				' . Shop::addSqlAssociation('product_attribute', 'pa') . '
				LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int)$id_lang . ')
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int)$id_lang . ')
				WHERE pa.`id_product` = ' . (int)$id_product . '
				GROUP BY pa.`id_product_attribute`, ag.`id_attribute_group`
				ORDER BY pa.`id_product_attribute`';

            $res = Db::getInstance()->executeS($sql);

            foreach ($res as $key => $row) {
                $cache_key = $row['id_product'] . '_' . $row['id_product_attribute'] . '_quantity';

                if (!Cache::isStored($cache_key)) {
                    $result = StockAvailable::getQuantityAvailableByProduct($row['id_product'], $row['id_product_attribute']);
                    Cache::store(
                        $cache_key,
                        $result
                    );
                    $res[$key]['quantity'] = $result;
                } else {
                    $res[$key]['quantity'] = Cache::retrieve($cache_key);
                }
            }

            return $res;
        }

        function getAnchor($id_product, $id_product_attribute, $with_id = false)
        {
            $attributes = Product::getAttributesParams($id_product, $id_product_attribute);
            $anchor = '#';
            $sep = Configuration::get('PS_ATTRIBUTE_ANCHOR_SEPARATOR');
            foreach ($attributes as &$a) {
                foreach ($a as &$b) {
                    $b = str_replace($sep, '_', Tools::link_rewrite($b));
                }
                $anchor .= '/' . ($with_id && isset($a['id_attribute']) && $a['id_attribute'] ? (int)$a['id_attribute'] . $sep : '') . $a['group'] . $sep . $a['name'];
            }

            return $anchor;
        }

        function _getAttributeImageAssociations($id_product_attribute)
        {
            $combination_images = array();
            $data = Db::getInstance()->executeS(
                '
			SELECT `id_image`
			FROM `' . _DB_PREFIX_ . 'product_attribute_image`
			WHERE `id_product_attribute` = ' . (int)$id_product_attribute
            );
            foreach ($data as $row) {
                $combination_images[] = (int)$row['id_image'];
            }

            return $combination_images;
        }

        function getCombinationImageById($id_product_attribute, $id_lang)
        {
            if (!Combination::isFeatureActive() || !$id_product_attribute) {
                return false;
            }

            $result = Db::getInstance()->executeS(
                '
			SELECT pai.`id_image`, pai.`id_product_attribute`, il.`legend`
			FROM `' . _DB_PREFIX_ . 'product_attribute_image` pai
			LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (il.`id_image` = pai.`id_image`)
			LEFT JOIN `' . _DB_PREFIX_ . 'image` i ON (i.`id_image` = pai.`id_image`)
			WHERE pai.`id_product_attribute` = ' . (int)$id_product_attribute . ' AND il.`id_lang` = ' . (int)$id_lang . ' ORDER by i.`position` LIMIT 1'
            );

            if (!$result) {
                return false;
            }

            return $result[0];
        }

        function getBaseLink($id_shop = null, $ssl = null, $relative_protocol = false)
        {
            static $force_ssl = null;
            $ssl_enable = Configuration::get('PS_SSL_ENABLED');

            if ($ssl === null) {
                if ($force_ssl === null) {
                    $force_ssl = (Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
                }
                $ssl = $force_ssl;
            }

            if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') && $id_shop !== null) {
                $shop = new Shop($id_shop);
            } else {
                $shop = Context::getContext()->shop;
            }

            if ($relative_protocol) {
                $base = '//' . ($ssl && $ssl_enable ? $shop->domain_ssl : $shop->domain);
            } else {
                $base = (($ssl && $ssl_enable) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain);
            }

            return $base . $shop->getBaseURI();
        }

        function getLangLink($id_lang = null, Context $context = null, $id_shop = null)
        {
            $allow = (int)Configuration::get('PS_REWRITING_SETTINGS');
            if (!$context) {
                $context = Context::getContext();
            }

            if ((!$allow && in_array($id_shop, array($context->shop->id, null))) || !Language::isMultiLanguageActivated($id_shop) || !(int)Configuration::get('PS_REWRITING_SETTINGS', null, null, $id_shop)) {
                return '';
            }

            if (!$id_lang) {
                $id_lang = $context->language->id;
            }

            return Language::getIsoById($id_lang) . '/';
        }

        function getProductLink($product, $category = null, $id_lang = null, $ipa = 0, $relative_protocol = false)
        {
            $dispatcher = Dispatcher::getInstance();

            if (!$id_lang) {
                $id_lang = Context::getContext()->language->id;
            }

            $url = getBaseLink(null, null, $relative_protocol) . getLangLink($id_lang, null, null);

            if (!is_object($product)) {
                if (is_array($product) && isset($product['id_product'])) {
                    $product = new Product($product['id_product'], false, $id_lang, null);
                } elseif ((int)$product) {
                    $product = new Product((int)$product, false, $id_lang, null);
                } else {
                    throw new PrestaShopException('Invalid product vars');
                }
            }

            $params = array();
            $params['id'] = $product->id;
            $params['rewrite'] = $product->getFieldByLang('link_rewrite');

            $params['ean13'] = $product->ean13;
            $params['meta_keywords'] = Tools::str2url($product->getFieldByLang('meta_keywords'));
            $params['meta_title'] = Tools::str2url($product->getFieldByLang('meta_title'));

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'manufacturer', null)) {
                $params['manufacturer'] = Tools::str2url($product->isFullyLoaded ? $product->manufacturer_name : Manufacturer::getNameById($product->id_manufacturer));
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'supplier', null)) {
                $params['supplier'] = Tools::str2url($product->isFullyLoaded ? $product->supplier_name : Supplier::getNameById($product->id_supplier));
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'price', null)) {
                $params['price'] = $product->isFullyLoaded ? $product->price : Product::getPriceStatic($product->id, false, null, 6, null, false, true, 1, false, null, null, null, $product->specificPrice);
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'tags', null)) {
                $params['tags'] = Tools::str2url($product->getTags($id_lang));
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'category', null)) {
                $params['category'] = (!is_null($product->category) && !empty($product->category)) ? Tools::str2url($product->category) : Tools::str2url($category);
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'reference', null)) {
                $params['reference'] = Tools::str2url($product->reference);
            }

            if ($dispatcher->hasKeyword('product_rule', $id_lang, 'categories', null)) {
                $params['category'] = (!$category) ? $product->category : $category;
                $cats = array();
                foreach ($product->getParentCategories($id_lang) as $cat) {
                    if (!in_array($cat['id_category'], Link::$category_disable_rewrite)) {
                        $cats[] = $cat['link_rewrite'];
                    }
                }
                $params['categories'] = implode('/', $cats);
            }
            $anchor = $ipa ? $product->getAnchor((int)$ipa, true) : '';

            return $url . $dispatcher->createUrl('product_rule', $id_lang, $params, false, $anchor, null);
        }

        function getImageLink($name, $ids, $type = null)
        {
            $not_default = false;
            $allow = (int)Configuration::get('PS_REWRITING_SETTINGS');

            if (Configuration::get('WATERMARK_LOGGED') && (Module::isInstalled('watermark') && Module::isEnabled('watermark')) && isset(Context::getContext()->customer->id)) {
                $type .= '-' . Configuration::get('WATERMARK_HASH');
            }

            $theme = ((Shop::isFeatureActive() && file_exists(_PS_PROD_IMG_DIR_ . $ids . ($type ? '-' . $type : '') . '-' . (int)Context::getContext()->shop->id_theme . '.jpg')) ? '-' . Context::getContext()->shop->id_theme : '');
            if ((Configuration::get('PS_LEGACY_IMAGES')
                    && (file_exists(_PS_PROD_IMG_DIR_ . $ids . ($type ? '-' . $type : '') . $theme . '.jpg')))
                || ($not_default = strpos($ids, 'default') !== false)
            ) {
                if ($allow == 1 && !$not_default) {
                    $uri_path = __PS_BASE_URI__ . $ids . ($type ? '-' . $type : '') . $theme . '/' . $name . '.jpg';
                } else {
                    $uri_path = _THEME_PROD_DIR_ . $ids . ($type ? '-' . $type : '') . $theme . '.jpg';
                }
            } else {
                $split_ids = explode('-', $ids);
                $id_image = (isset($split_ids[1]) ? $split_ids[1] : $split_ids[0]);
                $theme = ((Shop::isFeatureActive() && file_exists(_PS_PROD_IMG_DIR_ . Image::getImgFolderStatic($id_image) . $id_image . ($type ? '-' . $type : '') . '-' . (int)Context::getContext()->shop->id_theme . '.jpg')) ? '-' . Context::getContext()->shop->id_theme : '');
                if ($allow == 1) {
                    $uri_path = __PS_BASE_URI__ . $id_image . ($type ? '-' . $type : '') . $theme . '/' . $name . '.jpg';
                } else {
                    $uri_path = _THEME_PROD_DIR_ . Image::getImgFolderStatic($id_image) . $id_image . ($type ? '-' . $type : '') . $theme . '.jpg';
                }
            }

            return Tools::getMediaServer($uri_path) . $uri_path;
        }

        $zapProducts = explode(',', Configuration::get('ZAPEXPORT_PRODUCTS'));
        $category = new Category($catID, (int)$context->language->id);
        $catProducts = $category->getProducts((int)$context->language->id, 1, 1000);
        $carrier = new Carrier((int)Configuration::get('PS_CARRIER_DEFAULT'));
        $shipping = Configuration::get('PS_SHIPPING_METHOD');
        $freeShippingPrice = Configuration::get('PS_SHIPPING_FREE_PRICE');
        $include_all_products = (int)Configuration::get('ZAPEXPORT_INCLUDE_ALL_PRODUCTS');

        $xml = '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
        $xml .= '<STORE url="http://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . '" date="' . date('d/m/Y') . '">' . "\n";
        $xml .= '<PRODUCTS>' . "\n";
        foreach ($catProducts as $product) {
            if ((in_array($product['id_product'], $zapProducts) || (int)$include_all_products == 1) && (int)$product['quantity'] > 0 && (int)$product['available_for_order'] == 1) {
                if ($product['id_product_attribute'] != 0) {
                    $product_all_attributes = getAttributeCombinationsById($product['id_product'], (int)$context->language->id);
                    $pro = new Product((int)$product['id_product'], (int)$context->language->id);
                    foreach ($product_all_attributes as $pro_attr) {
                        $product_attribute_id = (int)$pro_attr['id_product_attribute'];
                        $reference = ($pro_attr['reference'] != null) ? $pro_attr['reference'] : $product['reference'];
                        $product_image_comb = getCombinationImageById($product_attribute_id, (int)$context->language->id);
                        if ($product_image_comb) {
                            $imageID = $product_image_comb['id_image'];
                        } else {
                            $imageID = Image::getCover($product['id_product']);
                            $imageID = $imageID['id_image'];
                        }
                        $price = Product::getPriceStatic((int)$product['id_product'], true, $product_attribute_id, 6);
                        $shipping_cost = ($shipping == 1) ? $carrier->getDeliveryPriceByWeight($product['weight'], 3) : $carrier->getDeliveryPriceByPrice((int)$product['price'], 3);
                        $shipment_cost = (Configuration::get('ZAPEXPORT_SHIPMENT_COST') ? Configuration::get('ZAPEXPORT_SHIPMENT_COST') : ($price >= $freeShippingPrice ? '0' : (float)$shipping_cost));
                        switch (Configuration::get('ZAPEXPORT_DESC_TYPE')) {
                            case 'short':
                                $desc = strip_tags($product['description_short']);
                                break;
                            case 'long':
                                $desc = strip_tags($product['description']);
                                break;
                            case 'meta':
                                $desc = strip_tags($product['meta_description']);
                                break;
                        }
                        $description = (Tools::strlen($desc) > 255) ? mb_substr($desc, 0, 255, 'utf-8') : $desc;
                        $name = htmlspecialchars($product['name'] . ' - ' . $pro_attr['attribute_name']);
                        $xml .= '<PRODUCT>' . "\n";
                        $xml .= '<PRODUCT_URL><![CDATA[ ' . getProductLink($product, null, null, $product_attribute_id, false) . ' ]]></PRODUCT_URL>' . "\t\n";
                        $xml .= '<PRODUCT_NAME><![CDATA[ ' . $name . ' ]]></PRODUCT_NAME>' . "\t\n";
                        $xml .= '<DETAILS><![CDATA[ ' . $description . ' ]]></DETAILS>' . "\t\n";
                        $xml .= '<CATALOG_NUMBER><![CDATA[ ' . $reference . ' ]]></CATALOG_NUMBER>' . "\t\n";
                        $xml .= '<CURRENCY><![CDATA[ ' . Configuration::get('ZAPEXPORT_CURRENCY') . ' ]]></CURRENCY>' . "\t\n";
                        $xml .= '<PRICE><![CDATA[ ' . $price . ' ]]></PRICE>' . "\t\n";
                        $xml .= '<SHIPMENT_COST><![CDATA[ ' . $shipment_cost . ' ]]></SHIPMENT_COST>' . "\t\n";
                        $xml .= '<DELIVERY_TIME><![CDATA[ ' . (Configuration::get('ZAPEXPORT_DELIVERY_TIME') ? Configuration::get('ZAPEXPORT_DELIVERY_TIME') : $carrier->delay[(int)$context->language->id]) . ' ]]></DELIVERY_TIME>' . "\t\n";;
                        $xml .= '<MANUFACTURER><![CDATA[ ' . htmlspecialchars(Manufacturer::getNameById((int)$product['id_manufacturer']), null, 'UTF-8', false) . ' ]]></MANUFACTURER>' . "\t\n";;
                        $xml .= getExtraFeatures($product['id_product'], $context, $product);
                        $xml .= '<IMAGE><![CDATA[ ' . getImageLink($product['link_rewrite'], $imageID, Configuration::get('ZAPEXPORT_IMAGE_TYPE')) . ' ]]></IMAGE>' . "\t\n";
                        $xml .= '<TAX></TAX>';
                        $xml .= '</PRODUCT>' . "\n";
                    }
                } else {
                    $price = Product::getPriceStatic((int)$product['id_product'], true, null, 6);
                    $shipping_cost = ($shipping == 1) ? $carrier->getDeliveryPriceByWeight($product['weight'], 3) : $carrier->getDeliveryPriceByPrice((int)$product['price'], 3);
                    $shipment_cost = (Configuration::get('ZAPEXPORT_SHIPMENT_COST') ? Configuration::get('ZAPEXPORT_SHIPMENT_COST') : ($price >= $freeShippingPrice ? '0' : (float)$shipping_cost));
                    switch (Configuration::get('ZAPEXPORT_DESC_TYPE')) {
                        case 'short':
                            $desc = strip_tags($product['description_short']);
                            break;
                        case 'long':
                            $desc = strip_tags($product['description']);
                            break;
                        case 'meta':
                            $desc = strip_tags($product['meta_description']);
                            break;
                    }
                    $description = (Tools::strlen($desc) > 255) ? mb_substr($desc, 0, 255, 'utf-8') : $desc;
                    $name = htmlspecialchars($product['name']);
                    $imageID = Image::getCover($product['id_product']);
                    $imageID = $imageID['id_image'];
                    $image = $context->link->getImageLink($product['link_rewrite'], $product['id_product'] . '-' . $imageID, Configuration::get('ZAPEXPORT_IMAGE_TYPE'));
                    $xml .= '<PRODUCT>' . "\n";
                    $xml .= '<PRODUCT_URL><![CDATA[ ' . $product['link'] . ' ]]></PRODUCT_URL>' . "\t\n";
                    $xml .= '<PRODUCT_NAME><![CDATA[ ' . $name . ' ]]></PRODUCT_NAME>' . "\t\n";
                    $xml .= '<DETAILS><![CDATA[ ' . $description . ' ]]></DETAILS>' . "\t\n";
                    $xml .= '<CATALOG_NUMBER><![CDATA[ ' . $product['reference'] . ' ]]></CATALOG_NUMBER>' . "\t\n";
                    $xml .= '<CURRENCY><![CDATA[ ' . Configuration::get('ZAPEXPORT_CURRENCY') . ' ]]></CURRENCY>' . "\t\n";
                    $xml .= '<PRICE><![CDATA[ ' . $price . ' ]]></PRICE>' . "\t\n";
                    $xml .= '<SHIPMENT_COST><![CDATA[ ' . $shipment_cost . ' ]]></SHIPMENT_COST>' . "\t\n";
                    $xml .= '<DELIVERY_TIME><![CDATA[ ' . (Configuration::get('ZAPEXPORT_DELIVERY_TIME') ? Configuration::get('ZAPEXPORT_DELIVERY_TIME') : $carrier->delay[(int)$context->language->id]) . ' ]]></DELIVERY_TIME>' . "\t\n";;
                    $xml .= '<MANUFACTURER><![CDATA[ ' . htmlspecialchars(Manufacturer::getNameById((int)$product['id_manufacturer']), null, 'UTF-8', false) . ' ]]></MANUFACTURER>' . "\t\n";;
                    $xml .= getExtraFeatures($product['id_product'], $context, $product);
                    $xml .= '<IMAGE><![CDATA[ ' . $image . ' ]]></IMAGE>' . "\t\n";
                    $xml .= '<TAX></TAX>';
                    $xml .= '</PRODUCT>' . "\n";
                }
            }
        }

        $xml .= '</PRODUCTS>' . "\n";
        $xml .= '</STORE>' . "\n";
        echo $xml;
    } else {
        $path = _MODULE_DIR_ . 'zapexport/zapxml.php';
        $zapCategories = explode(',', Configuration::get('ZAPEXPORT_CATEGORIES'));
        $dbCategories = Category::getCategories();
        $categories = array();

        foreach ($dbCategories as $category) {
            $ids = array_values($category);
            foreach ($ids as $id) {
                if (in_array($id['infos']['id_category'], $zapCategories)) $categories[$id['infos']['id_category']] = $id['infos']['name'];
            }
        } ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8"/>
            <title><?php echo Configuration::get('PS_SHOP_NAME') . ' - ZAP XML' ?></title>
        </head>
        <body>
        <ul>
            <?php foreach ($categories as $categoryID => $categoryName) : ?>
                <li>
                    <a href="<?php echo $path . '?catID=' . $categoryID; ?>"><?php echo $categoryName; ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        </body>
        </html>
        <?php
    }
