<?php

namespace Drupal\the_strata_theme;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

include_once __DIR__ . '/../the_strata_theme.theme';
_the_strata_theme_include_theme_includes();

/**
 * After build callbacks for the the_strata_theme theme.
 */
class TheStrataThemeAfterBuild implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * After build callback to modify the description if a setting is overwritten.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array containing the new description.
   */
  public static function overriddenSettingByUser(array $element) {

    /** @var \Drupal\the_strata_theme\TheStrataThemeSettings $settings */
    $settings = \Drupal::classResolver(TheStrataThemeSettings::class);

    // Check if this is overridden by the logged in user.
    if ($element && isset($element['#name']) && $settings->overridden($element['#name'])) {
      $userEditUrl = Url::fromRoute('entity.user.edit_form', ['user' => \Drupal::currentUser()->id()])->toString();

      $value = $settings->get($element['#name']);
      if ($element['#type'] === 'radios' || $element['#type'] === 'select') {
        $value = $element['#options'][$value];
      }
      if ($element['#type'] === 'checkbox') {
        $value = $value ? t('Enabled') : t('Disabled');
      }

      $element += ['#description' => ''];
      $element['#description'] .= '<span class="form-item__warning">' .
        t('This setting is overridden by the <a href=":editUrl">current user</a>. @title: %value',
          [
            '@title' => $element['#title'],
            '%value' => $value,
            ':editUrl' => $userEditUrl,
          ]) . '</span>';
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['overriddenSettingByUser'];
  }

}
