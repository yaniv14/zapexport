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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
$(document).ready(function () {

    // Submit forms from Products & Categories
    $('body').on('click', '.submit-form-btn', function (e) {
        e.preventDefault();
        $('#module_form').submit();
    });

    var products_list = ( $('#ZAPEXPORT_PRODUCTS').val() != '' ) ? $('#ZAPEXPORT_PRODUCTS').val() : new Array();
    var categories_list = ( $('#ZAPEXPORT_CATEGORIES').val() != '' ) ? $('#ZAPEXPORT_CATEGORIES').val() : new Array();
    var mainSelector = $('#mainSelector');
    var p_inputs = $('#zapTable tbody input[type="checkbox"]');
    var c_inputs = $('#zapCategories input[type="checkbox"]');

    if (products_list.length)
        products_list = products_list.split(',');

    if (categories_list.length)
        categories_list = categories_list.split(',');


    p_inputs.on('click', function () {
        updateProductsList($(this).val());
    });

    c_inputs.on('click', function () {
        var clickedCategory = $(this).val();
        updateCategoriesList($(this).val());
        if ($(this).is(':checked')) {
            p_inputs.each(function () {
                if ($(this).parents('tr').data('category') == clickedCategory) {
                    $(this).prop('checked', true);
                    updateProductsList($(this).val());
                }
            });
        } else {
            p_inputs.each(function () {
                if ($(this).parents('tr').data('category') == clickedCategory) {
                    $(this).prop('checked', false);
                    updateProductsList($(this).val());
                }
            });
        }
    });

    mainSelector.click(function () {
        if (mainSelector.is(':checked')) {
            p_inputs.each(function () {
                $(this).attr('checked', mainSelector.is(':checked'));
                if (!checkInProductsList($(this).val())) {
                    products_list.push($(this).val());
                }
            });
        } else {
            p_inputs.attr('checked', false);
            products_list = [];
        }

        $('#ZAPEXPORT_PRODUCTS').val(products_list);
    });


    function updateProductsList(v) {
        if (!checkInProductsList(v)) {
            products_list.push(v);
        } else {
            var idx = products_list.indexOf(v);
            if (idx != -1)
                products_list.splice(idx, 1);
        }
        $('#ZAPEXPORT_PRODUCTS').val(products_list);

    }

    function updateCategoriesList(v) {
        if (!checkInCategoriesList(v)) {
            categories_list.push(v);
        } else {
            var idx = categories_list.indexOf(v);
            if (idx != -1)
                categories_list.splice(idx, 1);
        }
        $('#ZAPEXPORT_CATEGORIES').val(categories_list);

    }

    function checkInProductsList(v) {
        return ($.inArray(v, products_list) != -1 || $.inArray(v, products_list) > -1) ? true : false;
    }

    function checkInCategoriesList(v) {
        return ($.inArray(v, categories_list) != -1 || $.inArray(v, categories_list) > -1) ? true : false;
    }

});
