<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    protected $table = 'configurations';

    protected $fillable = [
        'agency_id',
        'email',
        'code_agency',
        'brandLogoDefault',
        'brandLogoHeightDefault',
        'faviconDefault',
        'brandLogo',
        'brandLogoHeight',
        'primaryColor',
        'infoColor',

        'web_headTitle',
        'web_headDescription',
        'web_headKeywords',
        'web_headOpTitle',
        'web_headOpDescription',
        'web_headXTitle',
        'web_headXDescription',

        'web_sectionOne_title',
        'web_icons_redSocial',
        'web_headerLogo',
        'web_nosotros',

        'web_mision',
        'web_imageMision',

        'web_vision',
        'web_imageVision',

        'web_namePlan_1',
        'web_pricePlan_1',
        'web_descriptionPlan_1',
        'web_descriptionBottonPlan_1',

        'web_namePlan_2',
        'web_pricePlan_2',
        'web_descriptionPlan_2',
        'web_descriptionBottonPlan_2',

        'web_namePlan_3',
        'web_pricePlan_3',
        'web_descriptionPlan_3',
        'web_descriptionBottonPlan_3',
        'web_formaPagoPlan_1',
        'web_descriptionPricePlan_1',
        'web_formaPagoPlan_2',
        'web_descriptionPricePlan_2',
        'web_formaPagoPlan_3',
        'web_descriptionPricePlan_3',

        'web_footerPlans',
        'web_footerBottonPlans',

        'web_footerLogo',
        'web_footerLogoText',
        'web_footerContactEmail',
        'web_footerContactPhone',
        'web_footerContactAddress',

        'web_nosotrosTitle_parteIzquierda',
        'web_nosotrosTitle_parteDerecha',
        'web_nosotros',

        'web_plansTitle',
        'web_plansSubTitle',

        'table_af_corp_table_title',
        'table_af_corp_table_description',

        'table_af_ind_table_title',
        'table_af_ind_table_description',

        'table_quote_corp_table_title',
        'table_quote_corp_table_description',

        'table_quote_ind_table_title',
        'table_quote_ind_table_description',

        'table_request_table_title',
        'table_request_table_description',

        'table_agency_title',
        'table_agency_description',

        'menu_top',
        'duplicatedSession',

    ];

    protected $casts = [
        'web_icons_redSocial' => 'array',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}