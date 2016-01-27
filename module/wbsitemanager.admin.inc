<?php

/**
 * [wbsitemanager_api_keys_settings description]
 * @param  [type] $form        [description]
 * @param  [type] &$form_state [description]
 * @return [type]              [description]
 */
  function wbsitemanager_api_keys_settings($form, &$form_state) {

    $form = array();

    $form['wbsitemanager_ipfilter'] = array(
      '#type'          => 'textarea',
      '#required'      => false,
      '#title'         => t('IP Filter'),
      '#default_value' => variable_get('wbsitemanager_ipfilter', NULL),
      '#description'   => t('IP Filter for Web API requests.'),
    );

    $form['wbsitemanager_userfilter'] = array(
      '#type'          => 'textarea',
      '#required'      => false,
      '#title'         => t('User Filter'),
      '#default_value' => variable_get('wbsitemanager_userfilter', NULL),
      '#description'   => t('User Filter for Web API requests.'),
    );

    return system_settings_form($form);

  }
