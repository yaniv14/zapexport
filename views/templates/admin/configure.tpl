{*
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="alert alert-info">
	<p><strong>{l s='This module allows you to export xml to zap.' mod='zapexport'}</strong></p>
	<p>{l s='Brought to you free by Yaniv Mirel' mod='zapexport'}</p>
	<p>{l s='Link to submit to ZAP' mod='zapexport'} "{$base_shop_url}{$module_dir|escape:'htmlall':'UTF-8'}zapxml.php"</p>
</div>

<!-- Nav tabs -->
<ul class="nav nav-tabs" role="tablist">
	<li class="active"><a href="#general" role="tab" data-toggle="tab">{l s='General' mod='zapexport'}</a></li>
	<li><a href="#categories" role="tab" data-toggle="tab">{l s='Categories' mod='zapexport'}</a></li>
	<li><a href="#products" role="tab" data-toggle="tab">{l s='Products' mod='zapexport'}</a></li>
</ul>

<!-- Tab panes -->
<div class="tab-content">
	<div class="tab-pane active" id="general">{include file='./general.tpl'}</div>
	<div class="tab-pane" id="categories">{include file='./categories.tpl'}</div>
	<div class="tab-pane" id="products">{include file='./products.tpl'}</div>
</div>

<script type="text/javascript" src="{$module_dir|escape:'htmlall':'UTF-8'}views/js/zapexport.js"></script>
