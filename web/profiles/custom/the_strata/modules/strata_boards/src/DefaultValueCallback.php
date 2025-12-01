<?php

namespace Drupal\strata_boards;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides default value callbacks for fields.
 */
class DefaultValueCallback {

  /**
   * Returns the current user as default value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being created.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field definition.
   *
   * @return array
   *   An array of default values.
   */
  public static function currentUser($entity, FieldDefinitionInterface $definition): array {
    $current_user = \Drupal::currentUser();
    if ($current_user->isAuthenticated()) {
      return [['target_id' => $current_user->id()]];
    }
    return [];
  }

}
