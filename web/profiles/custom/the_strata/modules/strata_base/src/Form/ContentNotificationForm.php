<?php

namespace Drupal\strata_base\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure content notification settings.
 */
class ContentNotificationForm extends ConfigFormBase {

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
    return 'strata_base_content_notification_form';
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

    // Get all roles.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $role) {
      // Skip anonymous role.
      if ($role->id() !== 'anonymous') {
        $role_options[$role->id()] = $role->label();
      }
    }

    // Get all content types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $content_type_options = [];
    foreach ($node_types as $type) {
      $content_type_options[$type->id()] = $type->label();
    }

    $form['roles_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Select roles'),
      '#open' => TRUE,
    ];

    $form['roles_group']['notification_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#description' => $this->t('Select which roles should receive notifications.'),
      '#options' => $role_options,
      '#default_value' => $config->get('notification_roles') ?? [],
      '#parents' => ['notification_roles'],
    ];

    $form['content_types_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Select content types'),
      '#open' => TRUE,
    ];

    $form['content_types_group']['notification_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#description' => $this->t('Select which content types should trigger notifications.'),
      '#options' => $content_type_options,
      '#default_value' => $config->get('notification_content_types') ?? [],
      '#parents' => ['notification_content_types'],
    ];

    $form['content_triggers_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Trigger notification when a content is'),
      '#open' => TRUE,
    ];

    $form['content_triggers_group']['notification_content_triggers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content triggers'),
      '#description' => $this->t('Select when content notifications should be triggered.'),
      '#options' => [
        'created' => $this->t('Created'),
        'updated' => $this->t('Updated'),
      ],
      '#default_value' => $config->get('notification_content_triggers') ?? [],
      '#parents' => ['notification_content_triggers'],
    ];

    $form['comment_triggers_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Trigger notification when a new comment is'),
      '#open' => TRUE,
    ];

    $form['comment_triggers_group']['notification_comment_triggers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Comment triggers'),
      '#description' => $this->t('Select when comment notifications should be triggered.'),
      '#options' => [
        'created' => $this->t('Created'),
        'updated' => $this->t('Updated'),
      ],
      '#default_value' => $config->get('notification_comment_triggers') ?? [],
      '#parents' => ['notification_comment_triggers'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('strata_base.settings')
      ->set('notification_roles', array_values(array_filter($form_state->getValue('notification_roles') ?? [])))
      ->set('notification_content_types', array_values(array_filter($form_state->getValue('notification_content_types') ?? [])))
      ->set('notification_content_triggers', array_values(array_filter($form_state->getValue('notification_content_triggers') ?? [])))
      ->set('notification_comment_triggers', array_values(array_filter($form_state->getValue('notification_comment_triggers') ?? [])))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
