<?php

namespace Drupal\strata_base\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure restricted content types for anonymous users.
 */
class RestrictedContentForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'strata_base_restricted_content_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['strata_base.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('strata_base.settings');
    $restricted_types = $config->get('restricted_content_types') ?? [];

    // Get all content types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $type) {
      $options[$type->id()] = $type->label();
    }

    $form['restricted_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Restricted Content Types'),
      '#description' => $this->t('Select content types that should be restricted from anonymous users. Anonymous users attempting to access these content types will be redirected to the homepage.'),
      '#options' => $options,
      '#default_value' => $restricted_types,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('restricted_content_types');
    // Filter out unchecked values and re-index.
    $restricted_types = array_values(array_filter($values));

    $this->config('strata_base.settings')
      ->set('restricted_content_types', $restricted_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
