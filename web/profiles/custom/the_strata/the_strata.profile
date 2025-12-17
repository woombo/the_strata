<?php

/**
 * @file
 * Enables modules and site configuration for The Strata profile.
 */

use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function the_strata_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  // Add a placeholder as example that one can choose an arbitrary site name.
  $form['site_information']['site_name']['#attributes']['placeholder'] = t('The Strata');
}

/**
 * Implements hook_install_tasks().
 */
function the_strata_install_tasks(&$install_state) {
  return [
    'the_strata_install_profile_modules' => [
      'display_name' => t('Install additional modules'),
      'type' => 'batch',
    ],
  ];
}

/**
 * Installs additional modules for The Strata profile.
 *
 * @param array $install_state
 *   The current install state.
 *
 * @return array
 *   The batch definition.
 */
function the_strata_install_profile_modules(array &$install_state) {
  $modules = [
    // Add any additional modules to install here.
  ];

  $operations = [];
  foreach ($modules as $module) {
    $operations[] = ['_the_strata_install_module', [$module]];
  }

  return [
    'operations' => $operations,
    'title' => t('Installing additional modules'),
    'error_message' => t('The installation has encountered an error.'),
  ];
}

/**
 * Installs a single module.
 *
 * @param string $module
 *   The module to install.
 * @param array $context
 *   The batch context.
 */
function _the_strata_install_module($module, array &$context) {
  \Drupal::service('module_installer')->install([$module], TRUE);
  $context['message'] = t('Installed %module module.', ['%module' => $module]);
}
