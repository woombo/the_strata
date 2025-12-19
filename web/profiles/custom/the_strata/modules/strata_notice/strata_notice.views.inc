<?php

/**
 * @file
 * Views integration for the Strata Notice module.
 */

/**
 * Implements hook_views_data_alter().
 */
function strata_notice_views_data_alter(array &$data) {
  // Add custom month filter for notice nodes.
  $data['node_field_data']['notice_month'] = [
    'title' => t('Notice Month'),
    'help' => t('Filter notices by month with count display.'),
    'filter' => [
      'id' => 'notice_month_filter',
      'field' => 'created',
    ],
  ];
}
