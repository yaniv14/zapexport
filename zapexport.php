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

    if (!defined('_PS_VERSION_'))
        exit;

    class Zapexport extends Module
    {
        protected $config_form = false;
        private $_html = '';

        public function __construct()
        {
            $this->name = 'zapexport';
            $this->tab = 'administration';
            $this->version = '1.2.0';
            $this->author = 'Yaniv Mirel';
            $this->need_instance = 0;
            $this->bootstrap = true;

            parent::__construct();

            $this->displayName = $this->l('Zap export');
            $this->description = $this->l('XML Export for Zap price comparison');
        }

        static public function getExtraFeatures($productID)
        {
            $context = Context::getContext();
            $features = Product::getFrontFeaturesStatic($context->language->id, (int)$productID);

            $html = '';

            foreach ($features as $feature) {
                if (Tools::strtolower($feature['name']) == 'warranty')
                    $html .= '<WARRANTY>' . $feature['value'] . '</WARRANTY>\n';
                if (Tools::strtolower($feature['name']) == 'model')
                    $html .= '<MODEL>' . $feature['value'] . '</MODEL>\n';
            }

            return $html;
        }

        public function install()
        {
            return parent::install() &&
            $this->registerHook('backOfficeHeader') &&
            Configuration::updateValue('ZAPEXPORT_PRODUCTS', '') &&
            Configuration::updateValue('ZAPEXPORT_CATEGORIES', '') &&
            Configuration::updateValue('ZAPEXPORT_IMAGE_TYPE', 'large_default') &&
            Configuration::updateValue('ZAPEXPORT_DESC_TYPE', 'long') &&
            Configuration::updateValue('ZAPEXPORT_CURRENCY', 'ILS') &&
            Configuration::updateValue('ZAPEXPORT_SHIPMENT_COST', '') &&
            Configuration::updateValue('ZAPEXPORT_DELIVERY_TIME', '') &&
            Configuration::updateValue('ZAPEXPORT_INCLUDE_ALL_PRODUCTS', 1) &&
            Configuration::updateValue('ZAPEXPORT_WARRANTY', '');
        }

        public function uninstall()
        {
            Configuration::deleteByName('ZAPEXPORT_PRODUCTS') &&
            Configuration::deleteByName('ZAPEXPORT_CATEGORIES') &&
            Configuration::deleteByName('ZAPEXPORT_IMAGE_TYPE') &&
            Configuration::deleteByName('ZAPEXPORT_DESC_TYPE') &&
            Configuration::deleteByName('ZAPEXPORT_CURRENCY') &&
            Configuration::deleteByName('ZAPEXPORT_SHIPMENT_COST') &&
            Configuration::deleteByName('ZAPEXPORT_DELIVERY_TIME') &&
            Configuration::deleteByName('ZAPEXPORT_INCLUDE_ALL_PRODUCTS') &&
            Configuration::deleteByName('ZAPEXPORT_WARRANTY');

            return parent::uninstall();
        }

        public function getContent()
        {
            $this->_html = '';

            if (((bool)Tools::isSubmit('submitZapexportModule')) == true)
                $this->postProcess();

            $this->context->smarty->assign(
                array(
                    'module_dir'    => $this->_path,
                    'general_form'  => $this->renderForm(),
                    'categories'    => $this->getCategories(),
                    'products'      => $this->getProducts(),
                    'base_shop_url' => _PS_BASE_URL_
                )
            );

            $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

            return $output;
        }

        protected function postProcess()
        {
            $form_values = $this->getConfigFormValues();

            foreach (array_keys($form_values) as $key)
                Configuration::updateValue($key, Tools::getValue($key));

            $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
        }

        protected function getConfigFormValues()
        {
            return array(
                'ZAPEXPORT_CATEGORIES'           => Tools::getValue('ZAPEXPORT_CATEGORIES', Configuration::get('ZAPEXPORT_CATEGORIES')),
                'ZAPEXPORT_PRODUCTS'             => Tools::getValue('ZAPEXPORT_PRODUCTS', Configuration::get('ZAPEXPORT_PRODUCTS')),
                'ZAPEXPORT_IMAGE_TYPE'           => Tools::getValue('ZAPEXPORT_IMAGE_TYPE', Configuration::get('ZAPEXPORT_IMAGE_TYPE')),
                'ZAPEXPORT_DESC_TYPE'            => Tools::getValue('ZAPEXPORT_DESC_TYPE', Configuration::get('ZAPEXPORT_DESC_TYPE')),
                'ZAPEXPORT_CURRENCY'             => Tools::getValue('ZAPEXPORT_CURRENCY', Configuration::get('ZAPEXPORT_CURRENCY')),
                'ZAPEXPORT_SHIPMENT_COST'        => Tools::getValue('ZAPEXPORT_SHIPMENT_COST', Configuration::get('ZAPEXPORT_SHIPMENT_COST')),
                'ZAPEXPORT_DELIVERY_TIME'        => Tools::getValue('ZAPEXPORT_DELIVERY_TIME', Configuration::get('ZAPEXPORT_DELIVERY_TIME')),
                'ZAPEXPORT_INCLUDE_ALL_PRODUCTS' => Tools::getValue('ZAPEXPORT_INCLUDE_ALL_PRODUCTS', Configuration::get('ZAPEXPORT_INCLUDE_ALL_PRODUCTS')),
                'ZAPEXPORT_WARRANTY'             => Tools::getValue('ZAPEXPORT_WARRANTY', Configuration::get('ZAPEXPORT_WARRANTY')),
            );
        }

        protected function renderForm()
        {
            $helper = new HelperForm();

            $helper->show_toolbar = false;
            $helper->table = $this->table;
            $helper->module = $this;
            $helper->default_form_language = $this->context->language->id;
            $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

            $helper->identifier = $this->identifier;
            $helper->submit_action = 'submitZapexportModule';
            $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');

            $helper->tpl_vars = array(
                'fields_value' => $this->getConfigFormValues(),
                'languages'    => $this->context->controller->getLanguages(),
                'id_language'  => $this->context->language->id,
            );

            return $helper->generateForm(array($this->getConfigForm()));
        }

        protected function getConfigForm()
        {
            $descriptions = array(
                array('id_desc' => 'short', 'name' => $this->l('Short description')),
                array('id_desc' => 'long', 'name' => $this->l('Long description')),
                array('id_desc' => 'meta', 'name' => $this->l('Meta description'))
            );

            return array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Settings'),
                        'icon'  => 'icon-cogs',
                    ),
                    'input'  => array(
                        array(
                            'type'  => 'hidden',
                            'label' => $this->l('Categories'),
                            'name'  => 'ZAPEXPORT_CATEGORIES',
                        ),
                        array(
                            'type'  => 'hidden',
                            'label' => $this->l('Products'),
                            'name'  => 'ZAPEXPORT_PRODUCTS',
                        ),
                        array(
                            'type'    => 'switch',
                            'is_bool' => true,
                            'label'   => $this->l('Include all products'),
                            'name'    => 'ZAPEXPORT_INCLUDE_ALL_PRODUCTS',
                            'desc'    => $this->l('By turning this to yes, all active products will show on mirror page'),
                            'values'  => array(
                                array(
                                    'id'    => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Yes')
                                ),
                                array(
                                    'id'    => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('No')
                                )
                            ),
                        ),
                        array(
                            'type'   => 'text',
                            'label'  => $this->l('Shipment cost'),
                            'name'   => 'ZAPEXPORT_SHIPMENT_COST',
                            'desc'   => $this->l('Leaving this empty will get the cost from the default carrier'),
                            'prefix' => $this->context->currency->sign,
                        ),
                        array(
                            'type'    => 'select',
                            'label'   => $this->l('Image type'),
                            'name'    => 'ZAPEXPORT_IMAGE_TYPE',
                            'desc'    => $this->l('Image type to display in XML'),
                            'options' => array(
                                'query' => ImageType::getImagesTypes('products'),
                                'id'    => 'name',
                                'name'  => 'name'
                            )
                        ),
                        array(
                            'type'    => 'select',
                            'label'   => $this->l('Description type'),
                            'name'    => 'ZAPEXPORT_DESC_TYPE',
                            'desc'    => $this->l('Description type to display in XML'),
                            'options' => array(
                                'query' => $descriptions,
                                'id'    => 'id_desc',
                                'name'  => 'name'
                            )
                        ),
                        array(
                            'type'  => 'text',
                            'label' => $this->l('Delivery time'),
                            'name'  => 'ZAPEXPORT_DELIVERY_TIME',
                            'desc'  => $this->l('Leaving this empty will get the delivery time from the default carrier')
                        ),
                        array(
                            'type'  => 'text',
                            'label' => $this->l('Warranty'),
                            'name'  => 'ZAPEXPORT_WARRANTY',
                            'desc'  => $this->l('Leaving this empty will get the warranty from the product feature if exists')
                        ),
                        array(
                            'type'    => 'select',
                            'label'   => $this->l('Currency'),
                            'name'    => 'ZAPEXPORT_CURRENCY',
                            'options' => array(
                                'query' => Currency::getCurrencies(),
                                'id'    => 'iso_code',
                                'name'  => 'name'
                            )
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                    ),
                ),
            );
        }

        public function getCategories()
        {
            $category = new Category((int)Configuration::get('PS_HOME_CATEGORY'), $this->context->language->id);
            $range = '';
            $maxdepth = 5;
            if (Validate::isLoadedObject($category)) {
                if ($maxdepth > 0)
                    $maxdepth += $category->level_depth;
                $range = 'AND nleft >= ' . (int)$category->nleft . ' AND nright <= ' . (int)$category->nright;
            }
            $resultIds = array();
            $resultParents = array();
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                '
        SELECT c.id_parent, c.id_category, cl.name, cl.description, cl.link_rewrite
        FROM `' . _DB_PREFIX_ . 'category` c
        INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.`id_lang` = ' . (int)$this->context->language->id . Shop::addSqlRestrictionOnLang('cl') . ')
        INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON (cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . (int)$this->context->shop->id . ')
        WHERE (c.`active` = 1 OR c.`id_category` = ' . (int)Configuration::get('PS_HOME_CATEGORY') . ')
        AND c.`id_category` != ' . (int)Configuration::get('PS_ROOT_CATEGORY') . '
        ' . ((int)$maxdepth != 0 ? ' AND `level_depth` <= ' . (int)$maxdepth : '') . '
        ' . $range . '
        ORDER BY `level_depth` ASC, cl.`name` DESC'
            );

            foreach ($result as &$row) {
                $resultParents[$row['id_parent']][] = &$row;
                $resultIds[$row['id_category']] = &$row;
            }

            $html = '';
            $blockCategTree = $this->getTree($resultParents, $resultIds, $maxdepth, ($category ? $category->id : null));
            $existingCategories = explode(',', Configuration::get('ZAPEXPORT_CATEGORIES'));

            $html .= '<div id="zapCategories">';

            foreach ($blockCategTree['children'] as $node) {
                $checked = (in_array($node['id'], $existingCategories)) ? 'checked="checked"' : '';

                $html .= '<div class="checkbox">';
                $html .= '<label for="categories_' . $node['id'] . '">' . "\n";
                $html .= '<input name="categories[]" type="checkbox" id="categories_' . $node['id'] . '" value="' . $node['id'] . '" ' . $checked . ' />' . $node['name'] . "\n";
                $html .= '</label></div>';

                if (isset($node['children'])) {

                    foreach ($node['children'] as $child) {
                        $checked = (in_array($child['id'], $existingCategories)) ? 'checked="checked"' : '';

                        $html .= '<div class="checkbox child">';
                        $html .= '<label for="categories_' . $child['id'] . '">' . "\n";
                        $html .= '<input name="categories[]" type="checkbox" id="categories_' . $child['id'] . '" value="' . $child['id'] . '" ' . $checked . ' />' . $child['name'] . "\n";
                        $html .= '</label></div>';

                        if (isset($child['children'])) {

                            foreach ($child['children'] as $grandchild) {
                                $checked = (in_array($grandchild['id'], $existingCategories)) ? 'checked="checked"' : '';

                                $html .= '<div class="checkbox grandchild">';
                                $html .= '<label for="categories_' . $grandchild['id'] . '">' . "\n";
                                $html .= '<input name="categories[]" type="checkbox" id="categories_' . $grandchild['id'] . '" value="' . $grandchild['id'] . '" ' . $checked . ' />' . $grandchild['name'] . "\n";
                                $html .= '</label></div>';
                            }

                        }

                    }

                }
            }

            $html .= '<div class="panel-footer">';
            $html .= '<button type="button" class="btn btn-default pull-right submit-form-btn">';
            $html .= '<i class="process-icon-save"></i> ' . $this->l('Save');
            $html .= '</button>';
            $html .= '</div>';

            $html .= '</div>';

            return $html;
        }

        public function getTree($resultParents, $resultIds, $maxDepth, $id_category = null, $currentDepth = 0)
        {
            if (is_null($id_category))
                $id_category = $this->context->shop->getCategory();
            $children = array();
            if (isset($resultParents[$id_category]) && count($resultParents[$id_category]) && ($maxDepth == 0 || $currentDepth < $maxDepth))
                foreach ($resultParents[$id_category] as $subcat)
                    $children[] = $this->getTree($resultParents, $resultIds, $maxDepth, $subcat['id_category'], $currentDepth + 1);
            if (isset($resultIds[$id_category])) {
                $link = $this->context->link->getCategoryLink($id_category, $resultIds[$id_category]['link_rewrite']);
                $name = $resultIds[$id_category]['name'];
                $desc = $resultIds[$id_category]['description'];
            } else
                $link = $name = $desc = '';

            $return = array(
                'id'       => $id_category,
                'link'     => $link,
                'name'     => $name,
                'desc'     => $desc,
                'children' => $children
            );

            return $return;
        }

        public function getProducts()
        {
            $products = Product::getProducts((int)$this->context->language->id, 0, 0, 'id_product', 'DESC');
            $current_currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
            $zapProducts = (Configuration::get('ZAPEXPORT_PRODUCTS')) ? explode(',', Configuration::get('ZAPEXPORT_PRODUCTS')) : false;
            $html = '';
            $html .= '
		<div id="zapproducts">
		<table class="table table-bordered table-hover" id="zapTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="mainSelector" /></th>
                    <th>' . $this->l('Name') . '</th>
                    <th>' . $this->l('Category') . '</th>
                    <th>' . $this->l('Price') . '</th>
                </tr>
            </thead>
		    <tbody>';
            foreach ($products as $product) {
                $default_category = new Category((int)$product['id_category_default'], (int)$this->context->cookie->id_lang);
                $html .= '
                <tr data-category="' . $default_category->id . '">
                    <td><input type="checkbox" name="ids[]" value="' . $product['id_product'] . '"';

                if (is_array($zapProducts) && in_array($product['id_product'], $zapProducts)) $html .= 'checked="checked"';

                $html .= '/></td>
                    <td>' . $product['name'] . '</td>
                    <td>' . $default_category->name . '</td>
                    <td>' . number_format($product['price'], 2) . ' ' . $current_currency->sign . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<div class="panel-footer">';
            $html .= '<button type="button" class="btn btn-default pull-right submit-form-btn">';
            $html .= '<i class="process-icon-save"></i> ' . $this->l('Save');
            $html .= '</button>';
            $html .= '</div>';

            return $html;
        }

        public function hookBackOfficeHeader()
        {
            if (Tools::getValue('configure') == $this->name) {
                if ($this->context->language->is_rtl) {
                    $this->context->controller->addCSS($this->_path . 'views/css/zapexport-rtl.css');
                } else {
                    $this->context->controller->addCSS($this->_path . 'views/css/zapexport.css');
                }
            }
        }

    }
